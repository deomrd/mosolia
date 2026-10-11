<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Cotisations (carnets a parts) et depots (carnets libres).
 * Une saisie est validee d'office si son auteur a le droit de valider ; sinon elle
 * attend le controle et ne compte ni dans le carnet ni dans la caisse.
 */
final class ServiceCotisations
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCarnets $carnets,
        private readonly DepotOperations $operations,
        private readonly DepotCaisse $caisse,
        private readonly RecalculCarnet $recalcul,
        private readonly Audit $audit,
        private readonly ?ServiceNotifications $notifications = null,
    ) {
    }

    /**
     * @param string $valeur nombre de parts (carnet a parts) ou montant (carnet libre)
     * @return array{id:int,statut:string,montant:int}
     */
    public function saisir(Contexte $c, int $carnetId, string $valeur, string $date, string $commentaire = ''): array
    {
        $date = Dates::passee($date);
        return $this->bd->transaction(function () use ($c, $carnetId, $valeur, $date, $commentaire): array {
            $org = $c->organisationId();
            $carnet = $this->carnetOuvert($c, $carnetId);
            if ($date < $carnet['date_ouverture']) {
                throw new ErreurMetier('cotisations.avant_ouverture');
            }
            if ($carnet['fermeture'] === 'ECHEANCE' && $carnet['date_echeance'] !== null && $date > $carnet['date_echeance']) {
                throw new ErreurMetier('cotisations.carnet_echu');
            }
            // compte entierement bloque : seul le versement d'ouverture est accepte, jusqu'a l'echeance
            if (($carnet['blocage'] ?? null) === 'TOUT' && $date > $carnet['date_ouverture'] && $carnet['date_echeance'] !== null && $date < $carnet['date_echeance']) {
                throw new ErreurMetier('cotisations.compte_bloque', ['date' => jour((string) $carnet['date_echeance'])]);
            }
            [$parts, $montant] = $this->lire($c, $carnet, $valeur, 0);
            // sur un carnet dont la vente attend le controle, la cotisation attend aussi
            $statut = $c->peut('cotisations.valider') && $carnet['validation'] === 'VALIDE' ? 'VALIDE' : 'EN_ATTENTE';
            $maintenant = $this->bd->maintenant();
            $id = $this->operations->creerCotisation([
                'organisation_id' => $org, 'carnet_id' => $carnetId, 'client_id' => $carnet['client_id'], 'agent_id' => $carnet['agent_id'],
                'date_cotisation' => $date, 'nombre_parts' => $parts, 'mise' => $carnet['mode'] === 'PARTS' ? $carnet['mise'] : null,
                'montant_total' => Montant::depuisCentimes($montant), 'commentaire' => $commentaire !== '' ? $commentaire : null,
                'statut' => $statut, 'cree_par' => $c->utilisateurId(),
                'traite_par' => $statut === 'VALIDE' ? $c->utilisateurId() : null, 'traite_le' => $statut === 'VALIDE' ? $maintenant : null,
                'cree_le' => $maintenant,
            ]);
            if ($statut === 'VALIDE') {
                $this->recalcul->recalculer($org, $carnetId);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'cotisation.saisir', 'cotisation', $id, null, ['carnet_id' => $carnetId, 'parts' => $parts, 'montant' => Montant::depuisCentimes($montant), 'date' => $date, 'statut' => $statut]);
            // le client est prevenu des la saisie : il voit tout de suite son argent, valide ou en attente
            $this->notifications?->client($org, (int) $carnet['client_id'], $statut === 'VALIDE' ? 'client_cotisation_saisie_valide' : 'client_cotisation_saisie',
                ['montant' => argent($montant / 100), 'reference' => (string) $carnet['numero'], 'date' => jour($date), 'par' => (string) $c->utilisateur['nom']],
                '/espace-client/carnets/' . $carnetId);
            return ['id' => $id, 'statut' => $statut, 'montant' => $montant];
        });
    }

    /**
     * Collecte du jour : plusieurs carnets d'un recolteur en une fois. Chaque ligne est
     * independante ; une ligne refusee n'empeche pas les autres.
     * @param array<int|string,string> $lignes id du carnet => parts ou montant
     * @param ?string $argentDeclare argent que le recolteur dit avoir en main (controle)
     * @return array{nombre:int,total:int,erreurs:string[],ecart:?int}
     */
    public function collecter(Contexte $c, int $agentId, string $date, array $lignes, ?string $argentDeclare): array
    {
        $date = Dates::passee($date);
        $restreint = $c->recolteurRestreint();
        if ($restreint !== null && $restreint !== $agentId) {
            throw new ErreurMetier('erreur.403.texte');
        }
        $declare = $argentDeclare === null || trim($argentDeclare) === '' ? null : Montant::enCentimes(Montant::lire($argentDeclare, $c->decimales()));
        $nombre = 0;
        $total = 0;
        $erreurs = [];
        foreach ($lignes as $carnetId => $valeur) {
            $valeur = is_string($valeur) ? trim($valeur) : '';
            if ($valeur === '' || $valeur === '0') {
                continue;
            }
            try {
                $carnet = $this->carnets->parId($c->organisationId(), (int) $carnetId, $restreint);
                if ($carnet === null || (int) $carnet['agent_id'] !== $agentId) {
                    throw new ErreurMetier('carnets.carnet_introuvable');
                }
                $r = $this->saisir($c, (int) $carnetId, $valeur, $date);
                $nombre++;
                $total += $r['montant'];
            } catch (ErreurMetier $e) {
                $erreurs[] = ($carnet['client_code'] ?? '#' . (int) $carnetId) . ' : ' . t($e->cle, $e->valeurs);
            }
        }
        $ecart = null;
        if ($declare !== null && $nombre > 0) {
            $ecart = $declare - $total;
            $this->caisse->creerRemise([
                'organisation_id' => $c->organisationId(), 'agent_id' => $agentId, 'date_remise' => $date, 'periode' => substr($date, 0, 7),
                'origine' => 'CONTROLE_COLLECTE', 'montant_attendu' => Montant::depuisCentimes($total), 'montant_verse' => Montant::depuisCentimes($declare),
                'manquant' => Montant::depuisCentimes(max(0, -$ecart)), 'excedent' => Montant::depuisCentimes(max(0, $ecart)),
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
        }
        return ['nombre' => $nombre, 'total' => $total, 'erreurs' => $erreurs, 'ecart' => $ecart];
    }

    public function valider(Contexte $c, int $id): void
    {
        $this->bd->transaction(function () use ($c, $id): void {
            $org = $c->organisationId();
            $o = $this->operations->cotisation($org, $id) ?? throw new ErreurMetier('cotisations.introuvable');
            if ($o['statut'] !== 'EN_ATTENTE') {
                throw new ErreurMetier('cotisations.deja_traitee');
            }
            $carnet = $this->carnets->verrouiller($org, (int) $o['carnet_id']) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($carnet['statut'] !== 'ACTIF') {
                throw new ErreurMetier('carnets.pas_actif');
            }
            if ($carnet['validation'] !== 'VALIDE') {
                throw new ErreurMetier('carnets.vente_non_validee');
            }
            if ($carnet['mode'] === 'PARTS' && (int) $carnet['parts_validees'] + (int) $o['nombre_parts'] > (int) $carnet['nombre_parts']) {
                throw new ErreurMetier('cotisations.depasse', ['n' => (int) $carnet['nombre_parts'] - (int) $carnet['parts_validees']]);
            }
            $this->operations->modifierCotisation($id, ['statut' => 'VALIDE', 'traite_par' => $c->utilisateurId(), 'traite_le' => $this->bd->maintenant()]);
            $this->recalcul->recalculer($org, (int) $o['carnet_id']);
            $this->audit->noter($org, $c->utilisateurId(), 'cotisation.valider', 'cotisation', $id);
        });
    }

    /** @return array{validees:int,erreurs:string[]} */
    public function validerTout(Contexte $c, int $agentId): array
    {
        $validees = 0;
        $erreurs = [];
        foreach ($this->operations->enAttente($c->organisationId(), $agentId) as $o) {
            try {
                $this->valider($c, (int) $o['id']);
                $validees++;
            } catch (ErreurMetier $e) {
                $erreurs[] = $o['client_code'] . ' : ' . t($e->cle, $e->valeurs);
            }
        }
        return ['validees' => $validees, 'erreurs' => $erreurs];
    }

    public function rejeter(Contexte $c, int $id, string $motif): void
    {
        $org = $c->organisationId();
        $o = $this->operations->cotisation($org, $id) ?? throw new ErreurMetier('cotisations.introuvable');
        if ($o['statut'] !== 'EN_ATTENTE') {
            throw new ErreurMetier('cotisations.deja_traitee');
        }
        $this->exigerCarnetOuvert($org, (int) $o['carnet_id']);
        // rejeter = supprimer : la saisie part a la corbeille, d'ou elle peut revenir en attente
        $this->operations->modifierCotisation($id, [
            'supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(),
            'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : t('controle.rejete'),
        ]);
        $this->audit->noter($org, $c->utilisateurId(), 'cotisation.rejeter', 'cotisation', $id, null, null, $motif ?: null);
        $this->notifications?->client($org, (int) $o['client_id'], 'client_cotisation_rejetee', ['montant' => argent($o['montant_total']), 'date' => jour($o['date_cotisation'])], '/espace-client/carnets/' . $o['carnet_id']);
    }

    /** Sort une cotisation de la corbeille (son carnet doit exister). */
    public function restaurer(Contexte $c, int $id): string
    {
        return $this->bd->transaction(function () use ($c, $id): string {
            $org = $c->organisationId();
            $o = $this->bd->un('SELECT * FROM avecxy_cotisations WHERE organisation_id = ? AND id = ? AND supprime_le IS NOT NULL', [$org, $id]) ?? throw new ErreurMetier('cotisations.introuvable');
            $carnet = $this->exigerCarnetOuvert($org, (int) $o['carnet_id']);
            $this->operations->modifierCotisation($id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            if ($o['statut'] === 'VALIDE') {
                $this->recalcul->recalculer($org, (int) $o['carnet_id']);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'cotisation.restaurer', 'cotisation', $id);
            return t('corbeille.section.cotisations') . ' #' . $id;
        });
    }

    /** Corrige le nombre de parts (ou le montant) et la date d'une saisie. */
    public function corriger(Contexte $c, int $id, string $valeur, string $date): void
    {
        $date = Dates::passee($date);
        $this->bd->transaction(function () use ($c, $id, $valeur, $date): void {
            $org = $c->organisationId();
            $o = $this->modifiable($c, $id);
            $carnet = $this->exigerCarnetOuvert($org, (int) $o['carnet_id']);
            if ($date < $carnet['date_ouverture']) {
                throw new ErreurMetier('cotisations.avant_ouverture');
            }
            [$parts, $montant] = $this->lire($c, $carnet, $valeur, $o['statut'] === 'VALIDE' ? (int) $o['nombre_parts'] : 0, $id);
            $this->operations->modifierCotisation($id, ['date_cotisation' => $date, 'nombre_parts' => $parts, 'montant_total' => Montant::depuisCentimes($montant)]);
            if ($o['statut'] === 'VALIDE') {
                $this->recalcul->recalculer($org, (int) $o['carnet_id']);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'cotisation.corriger', 'cotisation', $id,
                ['parts' => $o['nombre_parts'], 'montant' => $o['montant_total'], 'date' => $o['date_cotisation']],
                ['parts' => $parts, 'montant' => Montant::depuisCentimes($montant), 'date' => $date]);
        });
    }

    public function supprimer(Contexte $c, int $id, string $motif): void
    {
        $this->bd->transaction(function () use ($c, $id, $motif): void {
            $org = $c->organisationId();
            $o = $this->modifiable($c, $id);
            $this->exigerCarnetOuvert($org, (int) $o['carnet_id']);
            $this->operations->modifierCotisation($id, [
                'supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
            ]);
            if ($o['statut'] === 'VALIDE') {
                $this->recalcul->recalculer($org, (int) $o['carnet_id']);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'cotisation.supprimer', 'cotisation', $id, $o, null, $motif ?: null);
        });
    }

    /** Un carnet cloture est verrouille : plus aucune operation, pas meme la correction d'un montant. */
    private function exigerCarnetOuvert(int $org, int $carnetId): array
    {
        $carnet = $this->carnets->verrouiller($org, $carnetId) ?? throw new ErreurMetier('carnets.carnet_introuvable');
        if ($carnet['statut'] !== 'ACTIF') {
            throw new ErreurMetier('carnets.verrouille');
        }
        return $carnet;
    }

    /** Qui peut corriger ou supprimer : une saisie validee demande le droit de correction. */
    public function peutModifier(Contexte $c, array $o): bool
    {
        if (($o['carnet_statut'] ?? 'ACTIF') !== 'ACTIF') {
            return false;   // carnet cloture : verrouille
        }
        if ($o['statut'] === 'VALIDE') {
            return $c->peut('cotisations.corriger');
        }
        if ($o['statut'] !== 'EN_ATTENTE') {
            return false;
        }
        return $c->peut('cotisations.corriger') || $c->peut('cotisations.valider') || (int) $o['cree_par'] === $c->utilisateurId();
    }

    private function modifiable(Contexte $c, int $id): array
    {
        $o = $this->operations->cotisation($c->organisationId(), $id, $c->recolteurRestreint()) ?? throw new ErreurMetier('cotisations.introuvable');
        if (!$this->peutModifier($c, $o)) {
            throw new ErreurMetier('cotisations.verrouillee');
        }
        return $o;
    }

    private function carnetOuvert(Contexte $c, int $carnetId): array
    {
        $carnet = $this->carnets->verrouiller($c->organisationId(), $carnetId) ?? throw new ErreurMetier('carnets.carnet_introuvable');
        $restreint = $c->recolteurRestreint();
        if ($restreint !== null && (int) $carnet['agent_id'] !== $restreint) {
            throw new ErreurMetier('carnets.carnet_introuvable');
        }
        if ($carnet['statut'] !== 'ACTIF') {
            throw new ErreurMetier('carnets.pas_actif');
        }
        return $carnet;
    }

    /**
     * Lit une saisie selon le mode du carnet.
     * @param int $dejaComptees parts de cette meme cotisation deja comptees dans le carnet (correction)
     * @return array{0:?int,1:int} nombre de parts (null en mode libre) et montant en centimes
     */
    private function lire(Contexte $c, array $carnet, string $valeur, int $dejaComptees, int $saufId = 0): array
    {
        $valeur = trim($valeur);
        if ($carnet['mode'] === 'LIBRE') {
            $montant = Montant::enCentimes(Montant::lire($valeur, $c->decimales(), 'cotisations.montant_invalide'));
            if ($montant <= 0) {
                throw new ErreurMetier('cotisations.montant_invalide');
            }
            $min = $this->bd->valeur('SELECT depot_minimum FROM avecxy_carnet_types WHERE id = ?', [$carnet['carnet_type_id']]);
            if ($min !== null && $montant < Montant::enCentimes($min)) {
                throw new ErreurMetier('cotisations.depot_trop_petit', ['min' => Montant::afficher($min, $c->decimales())]);
            }
            return [null, $montant];
        }
        if (!ctype_digit($valeur) || (int) $valeur < 1) {
            throw new ErreurMetier('cotisations.parts_invalides');
        }
        $parts = (int) $valeur;
        $restantes = (int) $carnet['nombre_parts'] - (int) $carnet['parts_validees'] + $dejaComptees
            - $this->operations->partsEnAttente((int) $carnet['id'], $saufId);
        if ($parts > $restantes) {
            throw new ErreurMetier($restantes <= 0 ? 'cotisations.carnet_plein' : 'cotisations.depasse', ['n' => max(0, $restantes)]);
        }
        return [$parts, $parts * Montant::enCentimes($carnet['mise'])];
    }
}

<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\InteretsEpargne;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotClients;
use AvecXY\Infrastructure\DepotTypesCarnet;
use AvecXY\Noyau\BaseDeDonnees;

/** Vente, fermeture et suppression des carnets. */
final class ServiceCarnets
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCarnets $carnets,
        private readonly DepotClients $clients,
        private readonly DepotTypesCarnet $types,
        private readonly RecalculCarnet $recalcul,
        private readonly ServiceCotisations $cotisations,
        private readonly ServiceRetraits $retraits,
        private readonly Audit $audit,
        private readonly ?ServiceNotifications $notifications = null,
    ) {
    }

    /**
     * Vend un carnet a un client. Le carnet copie les regles de son type : les
     * modifier ensuite ne le changera pas.
     * @param string $premierVersement parts (ou montant en mode libre) versees le jour meme ; vide = aucune
     * @param string $optionBlocage compte bloque : duree choisie en mois, parmi celles du type
     */
    public function vendre(Contexte $c, int $clientId, int $typeId, string $miseSaisie, string $date, string $premierVersement = '', string $optionBlocage = ''): int
    {
        $date = Dates::passee($date);
        return $this->bd->transaction(function () use ($c, $clientId, $typeId, $miseSaisie, $date, $premierVersement, $optionBlocage): int {
            $org = $c->organisationId();
            $client = $this->clients->parId($org, $clientId, $c->recolteurRestreint()) ?? throw new ErreurMetier('clients.introuvable');
            if ($client['statut'] !== 'ACTIF') {
                throw new ErreurMetier('clients.inactif');
            }
            $type = $this->types->parId($org, $typeId);
            if ($type === null || (int) $type['actif'] !== 1) {
                throw new ErreurMetier('carnets.type_indisponible');
            }
            $mise = null;
            if ($type['mode'] === 'PARTS') {
                $mise = Montant::lire($miseSaisie, $c->decimales(), 'carnets.mise_invalide');
                if (Montant::estNul($mise) || Montant::enCentimes($mise) < Montant::enCentimes($type['mise_minimum'])) {
                    throw new ErreurMetier('carnets.mise_trop_petite', ['min' => Montant::afficher($type['mise_minimum'], $c->decimales())]);
                }
                if ($type['mise_maximum'] !== null && Montant::enCentimes($mise) > Montant::enCentimes($type['mise_maximum'])) {
                    throw new ErreurMetier('carnets.mise_trop_grande', ['max' => Montant::afficher($type['mise_maximum'], $c->decimales())]);
                }
            }
            // duree : celle du type (0 = sans duree), ou celle choisie par le client pour un compte bloque
            $echeance = (int) $type['duree_jours'] > 0 ? Dates::ajouterJours($date, (int) $type['duree_jours']) : null;
            $blocage = ['blocage' => null, 'duree_mois' => null, 'taux_interet' => null];
            if (($type['blocage'] ?? null) !== null) {
                $options = TypeCarnet::options($type);
                $choix = null;
                foreach ($options as $o) {
                    if ((string) $o['mois'] === trim($optionBlocage) || (count($options) === 1 && trim($optionBlocage) === '')) {
                        $choix = $o;
                    }
                }
                if ($choix === null) {
                    throw new ErreurMetier('carnets.option_obligatoire');
                }
                $echeance = InteretsEpargne::echeance($date, (int) $choix['mois']);
                $blocage = ['blocage' => $type['blocage'], 'duree_mois' => (int) $choix['mois'], 'taux_interet' => $choix['taux'],
                    'renouvellement' => $type['renouvellement'] ?? 'AUCUN', 'debut_blocage' => $date, 'cycle' => 1];
            }
            $id = $this->carnets->creer($blocage + [
                'taux_base' => $type['taux_base'] ?? 'AN', 'interets_versement' => $type['interets_versement'] ?? 'FIN',
                'retrait_anticipe' => $type['retrait_anticipe'] ?? 'INTERDIT', 'penalite_anticipe' => $type['penalite_anticipe'] ?? '0.00',
                'solde_minimum' => $type['solde_minimum'] ?? null, 'parts_reserve' => $type['parts_reserve'] ?? null,
                'organisation_id' => $org, 'client_id' => $clientId, 'agent_id' => $client['agent_id'], 'carnet_type_id' => $typeId,
                'numero' => $this->carnets->prochainNumero($org), 'prix' => $type['prix'], 'mode' => $type['mode'], 'mise' => $mise,
                'nombre_parts' => $type['nombre_parts'], 'taille_tranche' => $type['taille_tranche'],
                'parts_benefice_par_tranche' => $type['parts_benefice_par_tranche'], 'prelevement_benefice' => $type['prelevement_benefice'],
                'retrait' => $type['retrait'], 'fermeture' => $type['fermeture'], 'frais_tenue_type' => $type['frais_tenue_type'],
                'frais_tenue_valeur' => $type['frais_tenue_valeur'], 'frais_tenue_frequence' => $type['frais_tenue_frequence'],
                'date_echeance' => $echeance, 'statut' => 'ACTIF', 'date_ouverture' => $date,
                // une vente faite sans le droit de valider attend le controle : ni caisse ni benefice d'ici la
                'validation' => $c->peut('carnets.valider') ? 'VALIDE' : 'EN_ATTENTE',
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->recalcul->recalculer($org, $id);   // enregistre le benefice de la vente
            $this->audit->noter($org, $c->utilisateurId(), 'carnet.vendre', 'carnet', $id, null, ['client' => $client['code'], 'type' => $type['nom'], 'mise' => $mise, 'prix' => $type['prix'], 'duree_mois' => $blocage['duree_mois'], 'taux' => $blocage['taux_interet']]);
            $this->notifications?->client($org, $clientId, $c->peut('carnets.valider') ? 'client_carnet_vendu_valide' : 'client_carnet_vendu',
                ['type' => (string) $type['nom'], 'montant' => argent($type['prix']), 'par' => (string) $c->utilisateur['nom']], '/espace-client/carnets/' . $id);
            if (trim($premierVersement) !== '' && trim($premierVersement) !== '0') {
                $this->cotisations->saisir($c, $id, $premierVersement, $date);
            }
            return $id;
        });
    }

    /** Controle : la vente du carnet est confirmee, son prix entre en caisse et dans les benefices. */
    public function validerVente(Contexte $c, int $id): void
    {
        $this->bd->transaction(function () use ($c, $id): void {
            $org = $c->organisationId();
            $carnet = $this->carnets->verrouiller($org, $id) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($carnet['validation'] !== 'EN_ATTENTE') {
                throw new ErreurMetier('cotisations.deja_traitee');
            }
            $this->carnets->modifier($id, ['validation' => 'VALIDE']);
            $this->recalcul->recalculer($org, $id);
            $this->audit->noter($org, $c->utilisateurId(), 'carnet.valider_vente', 'carnet', $id);
        });
    }

    /** Ferme un carnet a la main : l'epargne restante est remise au client. */
    public function fermer(Contexte $c, int $id, string $date): void
    {
        $date = Dates::passee($date);
        $this->bd->transaction(function () use ($c, $id, $date): void {
            $org = $c->organisationId();
            $carnet = $this->carnets->verrouiller($org, $id) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($carnet['statut'] !== 'ACTIF') {
                throw new ErreurMetier('carnets.pas_actif');
            }
            if (Montant::enCentimes($carnet['epargne']) > 0) {
                $this->retraits->faire($c, $id, $date, '', t('carnets.cloture_commentaire'), true);
            } else {
                $this->carnets->modifier($id, ['statut' => 'FERME', 'date_fermeture' => $date]);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'carnet.fermer', 'carnet', $id);
        });
    }

    /** Rouvre un carnet cloture a la main alors qu'il etait vide (aucun retrait ne l'a cloture). */
    public function rouvrir(Contexte $c, int $id): void
    {
        $this->bd->transaction(function () use ($c, $id): void {
            $org = $c->organisationId();
            $carnet = $this->carnets->verrouiller($org, $id) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($carnet['statut'] === 'ACTIF') {
                throw new ErreurMetier('carnets.deja_ouvert');
            }
            if ((int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_retraits WHERE carnet_id = ? AND ferme_carnet = 1 AND supprime_le IS NULL', [$id]) > 0) {
                throw new ErreurMetier('carnets.rouvrir_par_retrait');
            }
            $this->carnets->modifier($id, ['statut' => 'ACTIF', 'date_fermeture' => null]);
            $this->audit->noter($org, $c->utilisateurId(), 'carnet.rouvrir', 'carnet', $id);
        });
    }

    /** Envoie a la corbeille le carnet et toutes ses operations ; ses benefices disparaissent des comptes. */
    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        return $this->bd->transaction(function () use ($c, $id, $motif): string {
            $org = $c->organisationId();
            $carnet = $this->carnets->parId($org, $id) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            $this->carnets->verrouiller($org, $id);
            if ($carnet['statut'] !== 'ACTIF') {
                throw new ErreurMetier('carnets.verrouille');   // carnet cloture : il faut d'abord le rouvrir
            }
            $quand = $this->bd->maintenant();
            $par = $c->utilisateurId();
            foreach (['avecxy_cotisations', 'avecxy_retraits', 'avecxy_frais_tenue'] as $table) {
                $this->bd->executer("UPDATE $table SET supprime_le = ?, supprime_par = ? WHERE carnet_id = ? AND supprime_le IS NULL", [$quand, $par, $id]);
            }
            $this->bd->executer('DELETE FROM avecxy_benefices WHERE carnet_id = ?', [$id]);
            $this->carnets->modifier($id, ['supprime_le' => $quand, 'supprime_par' => $par, 'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null]);
            $this->audit->noter($org, $par, 'carnet.supprimer', 'carnet', $id, $carnet, null, $motif ?: null);
            return '#' . $carnet['numero'] . ' ' . $carnet['client_code'];
        });
    }

    /** Sort le carnet de la corbeille avec les operations parties en meme temps que lui. */
    public function restaurer(Contexte $c, int $id): string
    {
        return $this->bd->transaction(function () use ($c, $id): string {
            $org = $c->organisationId();
            $carnet = $this->carnets->parId($org, $id, null, true);
            if ($carnet === null || $carnet['supprime_le'] === null) {
                throw new ErreurMetier('carnets.carnet_introuvable');
            }
            if ($this->clients->parId($org, (int) $carnet['client_id']) === null) {
                throw new ErreurMetier('carnets.client_supprime');
            }
            $quand = $carnet['supprime_le'];
            foreach (['avecxy_cotisations', 'avecxy_retraits', 'avecxy_frais_tenue'] as $table) {
                $this->bd->executer("UPDATE $table SET supprime_le = NULL, supprime_par = NULL WHERE carnet_id = ? AND supprime_le = ?", [$id, $quand]);
            }
            $this->carnets->modifier($id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            $this->recalcul->recalculer($org, $id);
            $this->audit->noter($org, $c->utilisateurId(), 'carnet.restaurer', 'carnet', $id);
            return '#' . $carnet['numero'] . ' ' . $carnet['client_code'];
        });
    }
}

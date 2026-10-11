<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Noyau\BaseDeDonnees;

/** Retraits des clients, selon les regles copiees sur chaque carnet. */
final class ServiceRetraits
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCarnets $carnets,
        private readonly DepotOperations $operations,
        private readonly RecalculCarnet $recalcul,
        private readonly Audit $audit,
        private readonly ?ServiceNotifications $notifications = null,
        private readonly ?ServiceInterets $interets = null,
    ) {
    }

    /** Previent le recolteur du carnet et le client ; pour une annulation, aussi la caisse, la comptabilite et l'administration. */
    private function prevenir(Contexte $c, string $type, array $carnet, string $montant, int $retraitId, string $motif = ''): void
    {
        if ($this->notifications === null) {
            return;
        }
        $org = $c->organisationId();
        $client = $this->bd->un('SELECT code, nom FROM avecxy_clients WHERE id = ?', [$carnet['client_id']]) ?? ['code' => '', 'nom' => ''];
        $d = ['client' => trim($client['code'] . ' ' . $client['nom']), 'carnet' => (string) $carnet['numero'], 'montant' => $montant, 'par' => (string) $c->utilisateur['nom'], 'motif' => $motif !== '' ? $motif : '—'];
        $comptes = $this->notifications->comptesDuRecolteur($org, (int) $carnet['agent_id']);
        if ($type === 'retrait_annule') {
            $comptes = array_merge($comptes, $this->notifications->comptesAyant($org, ['caisse.voir', 'rapports.voir', 'retraits.annuler']));
        }
        $this->notifications->utilisateurs($org, array_diff($comptes, [$c->utilisateurId()]), $type, $d, '/carnets/' . $carnet['id']);
        $this->notifications->client($org, (int) $carnet['client_id'], 'client_' . $type, $d, '/espace-client/carnets/' . $carnet['id']);
    }

    /**
     * Ce que le client peut retirer maintenant.
     * @param ?int $montantVoulu centimes, pour un retrait libre
     * @return array{forme:string,possible:bool,raison:?string,brut:int,frais:int,net:int,tranche:?int,ferme:bool}
     *   forme : TOTAL (toute l'epargne), TRANCHE (une tranche) ou LIBRE (montant au choix)
     */
    public function proposition(array $carnet, int $decimales, ?int $montantVoulu = null, bool $solder = false, ?string $jour = null): array
    {
        $jour ??= date('Y-m-d');
        $epargne = Montant::enCentimes($carnet['epargne']);
        $p = ['forme' => 'LIBRE', 'possible' => false, 'raison' => null, 'brut' => 0, 'frais' => 0, 'net' => 0, 'tranche' => null, 'ferme' => false, 'anticipe' => false];
        if ($carnet['statut'] !== 'ACTIF') {
            return ['raison' => 'carnets.pas_actif'] + $p;
        }
        if (self::bloqueLe($carnet, $jour)) {
            // compte bloque avant son echeance : retrait interdit, ou sortie anticipee de tout le compte
            if ($carnet['retrait_anticipe'] === 'INTERDIT') {
                return ['raison' => 'retraits.compte_bloque'] + $p;
            }
            if ($epargne <= 0) {
                return ['raison' => 'retraits.rien_a_retirer'] + $p;
            }
            $penalite = $carnet['retrait_anticipe'] === 'PENALITE'
                ? Montant::arrondirDevise(Montant::pourcentage($epargne, (string) $carnet['penalite_anticipe']), $decimales)
                : Montant::enCentimes($carnet['total_interets'] ?? 0);
            $penalite = min($epargne, $penalite);
            return ['forme' => 'TOTAL', 'possible' => true, 'brut' => $epargne, 'frais' => $penalite, 'net' => $epargne - $penalite, 'ferme' => true, 'anticipe' => true] + $p;
        }
        $aChaqueRetrait = $carnet['frais_tenue_type'] !== 'AUCUN' && $carnet['frais_tenue_frequence'] === 'A_CHAQUE_RETRAIT';
        $aLaCloture = $carnet['frais_tenue_type'] !== 'AUCUN' && $carnet['frais_tenue_frequence'] === 'A_LA_CLOTURE';
        $frais = fn (int $surMontant, bool $ferme): int => ($aChaqueRetrait || ($aLaCloture && $ferme))
            ? min($surMontant, CalculCarnet::frais($carnet, $carnet['frais_tenue_type'] === 'POURCENTAGE_SOLDE' ? $epargne : $surMontant, $decimales))
            : 0;

        if ($solder || ($carnet['mode'] === 'PARTS' && ($carnet['retrait'] === 'TOTAL' || $carnet['fermeture'] === 'PREMIER_RETRAIT'))) {
            $p['forme'] = 'TOTAL';
            $p['ferme'] = true;
            $p['brut'] = $epargne;
        } elseif ($carnet['mode'] === 'PARTS' && $carnet['retrait'] === 'PAR_TRANCHE') {
            $p['forme'] = 'TRANCHE';
            $tranche = (int) $carnet['tranches_retirees'] + 1;
            $nombre = TypeCarnet::nombreTranches((int) $carnet['nombre_parts'], (int) $carnet['taille_tranche']);
            if ($tranche > $nombre) {
                return ['raison' => 'retraits.tout_retire'] + $p;
            }
            if ($tranche > CalculCarnet::tranchesCompletes($carnet, (int) $carnet['parts_validees'])) {
                return ['raison' => 'retraits.tranche_incomplete', 'tranche' => $tranche] + $p;
            }
            $p['tranche'] = $tranche;
            $p['brut'] = min($epargne, CalculCarnet::epargneDeTranche($carnet, $tranche));
            $p['ferme'] = $tranche === $nombre && $carnet['fermeture'] === 'DERNIERE_TRANCHE_RETIREE';
        } else {
            // ce qui doit rester sur le compte : solde minimum (compte courant) ou reserve (compte annuel)
            [$garder, $raisonGarder, $nGarder] = $solder ? [0, null, 0] : self::aGarder($carnet);
            $p['brut'] = $montantVoulu ?? max(0, $epargne - $garder);
            if ($p['brut'] > $epargne) {
                return ['raison' => 'retraits.depasse_epargne'] + $p;
            }
            if ($montantVoulu === null && $garder > 0 && $p['brut'] <= 0) {
                return ['raison' => $raisonGarder, 'minimum' => $garder, 'n' => $nGarder] + $p;
            }
        }
        if ($p['brut'] <= 0) {
            return ['raison' => 'retraits.rien_a_retirer'] + $p;
        }
        $p['frais'] = $frais($p['brut'], $p['ferme']);
        if ($p['forme'] === 'LIBRE' && $montantVoulu !== null) {
            // retrait libre : le client recoit le montant demande, les frais s'ajoutent
            if ($p['brut'] + $p['frais'] > $epargne) {
                return ['raison' => 'retraits.depasse_epargne'] + $p;
            }
            $p['net'] = $p['brut'];
            $p['brut'] += $p['frais'];
        } else {
            $p['net'] = $p['brut'] - $p['frais'];
        }
        if ($p['forme'] === 'LIBRE' && !$solder && ($garder ?? 0) > 0 && $epargne - $p['brut'] < $garder) {
            return ['raison' => $raisonGarder, 'possible' => false, 'minimum' => $garder, 'n' => $nGarder] + $p;
        }
        if ($p['forme'] === 'LIBRE') {
            $p['max'] = max(0, $epargne - ($garder ?? 0));
        }
        $p['possible'] = true;
        return $p;
    }

    /**
     * Ce qui doit rester sur un compte apres un retrait libre.
     * @return array{0:int,1:?string,2:int} centimes, raison du refus, nombre de parts
     */
    public static function aGarder(array $carnet): array
    {
        if ($carnet['mode'] === 'LIBRE' && ($carnet['solde_minimum'] ?? null) !== null) {
            return [Montant::enCentimes($carnet['solde_minimum']), 'retraits.solde_minimum', 0];
        }
        $reserve = (int) ($carnet['parts_reserve'] ?? 0);
        if ($carnet['mode'] === 'PARTS' && $reserve > 0 && (int) $carnet['parts_validees'] < (int) $carnet['nombre_parts']) {
            return [$reserve * Montant::enCentimes($carnet['mise']), 'retraits.reserve', $reserve];
        }
        return [0, null, 0];
    }

    /** Le compte est-il bloque ce jour-la ? (compte bloque, avant son echeance) */
    public static function bloqueLe(array $carnet, string $jour): bool
    {
        return ($carnet['blocage'] ?? null) !== null && $carnet['date_echeance'] !== null && $jour < (string) $carnet['date_echeance'];
    }

    /**
     * Un credit avec garantie bloque une part de l'epargne du client jusqu'a son remboursement.
     */
    private function exigerGarantie(int $org, array $carnet, int $retire, int $decimales): void
    {
        $garantie = Montant::enCentimes($this->bd->valeur(
            "SELECT COALESCE(SUM(garantie),0) FROM avecxy_credits WHERE organisation_id = ? AND client_id = ? AND statut IN ('EN_COURS','EN_RETARD') AND supprime_le IS NULL",
            [$org, $carnet['client_id']]
        ) ?? 0);
        if ($garantie <= 0) {
            return;
        }
        $epargne = Montant::enCentimes($this->bd->valeur(
            "SELECT COALESCE(SUM(epargne),0) FROM avecxy_carnets WHERE organisation_id = ? AND client_id = ? AND statut = 'ACTIF' AND supprime_le IS NULL",
            [$org, $carnet['client_id']]
        ) ?? 0);
        if ($epargne - $retire < $garantie) {
            throw new ErreurMetier('retraits.garantie', ['montant' => argent($garantie / 100), 'disponible' => argent(max(0, $epargne - $garantie) / 100)]);
        }
    }

    /** @param bool $solder true = remettre toute l'epargne et fermer le carnet, quelle que soit sa regle */
    public function faire(Contexte $c, int $carnetId, string $date, string $montantSaisi, string $commentaire, bool $solder = false): array
    {
        $date = Dates::passee($date);
        return $this->bd->transaction(function () use ($c, $carnetId, $date, $montantSaisi, $commentaire, $solder): array {
            $org = $c->organisationId();
            $carnet = $this->carnets->verrouiller($org, $carnetId) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($date < $carnet['date_ouverture']) {
                throw new ErreurMetier('cotisations.avant_ouverture');
            }
            if (($carnet['blocage'] ?? null) !== null && $this->interets !== null) {
                // a l'echeance, les interets dus sont verses avant de remettre l'argent
                if ($this->interets->verser($org, $carnetId, $c->decimales()) > 0) {
                    $carnet = $this->carnets->verrouiller($org, $carnetId) ?? $carnet;
                }
            }
            $voulu = null;
            if (!$solder && !self::bloqueLe($carnet, $date) && ($carnet['mode'] === 'LIBRE' || ($carnet['retrait'] === 'LIBRE' && $carnet['fermeture'] !== 'PREMIER_RETRAIT'))) {
                $voulu = Montant::enCentimes(Montant::lire($montantSaisi, $c->decimales(), 'retraits.montant_invalide'));
            }
            $p = $this->proposition($carnet, $c->decimales(), $voulu, $solder, $date);
            if (!$p['possible']) {
                throw new ErreurMetier((string) $p['raison'], [
                    'n' => $p['n'] ?? $p['tranche'] ?? 0, 'date' => jour((string) ($carnet['date_echeance'] ?? '')), 'montant' => argent(($p['minimum'] ?? 0) / 100),
                ]);
            }
            $this->exigerGarantie($org, $carnet, $p['brut'], $c->decimales());
            if ($p['ferme'] && $this->operations->compterEnAttente($carnetId) > 0) {
                throw new ErreurMetier('retraits.cotisations_en_attente');
            }
            $maintenant = $this->bd->maintenant();
            $id = $this->operations->creerRetrait([
                'organisation_id' => $org, 'carnet_id' => $carnetId, 'client_id' => $carnet['client_id'], 'agent_id' => $carnet['agent_id'],
                'date_retrait' => $date, 'tranche' => $p['tranche'], 'solde_avant' => $carnet['epargne'],
                'montant_retire' => Montant::depuisCentimes($p['net']), 'frais' => Montant::depuisCentimes($p['frais']),
                'ferme_carnet' => $p['ferme'] ? 1 : 0, 'statut' => 'VALIDE', 'commentaire' => $commentaire !== '' ? $commentaire : null,
                'cree_par' => $c->utilisateurId(), 'cree_le' => $maintenant,
            ]);
            if ($p['frais'] > 0) {
                $this->operations->creerFrais([
                    'organisation_id' => $org, 'carnet_id' => $carnetId, 'client_id' => $carnet['client_id'], 'agent_id' => $carnet['agent_id'],
                    'date_frais' => $date, 'periode' => substr($date, 0, 7),
                    'origine' => $p['anticipe'] ? 'PENALITE' : ($p['ferme'] && $carnet['frais_tenue_frequence'] === 'A_LA_CLOTURE' ? 'CLOTURE' : 'RETRAIT'),
                    'retrait_id' => $id, 'base' => Montant::depuisCentimes($p['brut']), 'montant' => Montant::depuisCentimes($p['frais']), 'cree_le' => $maintenant,
                ]);
            }
            if ($p['ferme']) {
                $this->carnets->modifier($carnetId, ['statut' => 'FERME', 'date_fermeture' => $date]);
            }
            $this->recalcul->recalculer($org, $carnetId);
            $this->audit->noter($org, $c->utilisateurId(), 'retrait.faire', 'retrait', $id, null, ['carnet_id' => $carnetId, 'montant' => Montant::depuisCentimes($p['net']), 'frais' => Montant::depuisCentimes($p['frais']), 'tranche' => $p['tranche'], 'ferme' => $p['ferme']]);
            $this->prevenir($c, 'retrait_fait', $carnet, argent($p['net'] / 100), $id);
            return $p + ['id' => $id];
        });
    }

    /**
     * Annule un retrait (corbeille). Si ce retrait avait cloture le carnet, le carnet est rouvert avec toutes ses donnees.
     * Sur un carnet cloture, seul le retrait qui l'a cloture peut etre annule : tout le reste est verrouille.
     */
    public function supprimer(Contexte $c, int $id, string $motif): void
    {
        if (!$c->peut('retraits.annuler')) {
            throw new ErreurMetier('erreur.403.texte');
        }
        $this->bd->transaction(function () use ($c, $id, $motif): void {
            $org = $c->organisationId();
            $r = $this->operations->retrait($org, $id) ?? throw new ErreurMetier('retraits.introuvable');
            $carnet = $this->carnets->verrouiller($org, (int) $r['carnet_id']) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            if ($carnet['statut'] !== 'ACTIF' && (int) $r['ferme_carnet'] !== 1) {
                throw new ErreurMetier('carnets.verrouille');
            }
            $maintenant = $this->bd->maintenant();
            $this->operations->modifierRetrait($id, [
                'supprime_le' => $maintenant, 'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
            ]);
            $this->operations->supprimerFraisDuRetrait($id, $maintenant, $c->utilisateurId());
            if ((int) $r['ferme_carnet'] === 1) {
                $this->carnets->modifier((int) $r['carnet_id'], ['statut' => 'ACTIF', 'date_fermeture' => null]);
            }
            $this->recalcul->recalculer($org, (int) $r['carnet_id']);
            $this->audit->noter($org, $c->utilisateurId(), 'retrait.supprimer', 'retrait', $id, $r, null, $motif ?: null);
            $this->prevenir($c, 'retrait_annule', $carnet, argent($r['montant_retire']), $id, $motif);
        });
    }

    /** Sort un retrait de la corbeille : le carnet qu'il fermait est referme, ses frais reviennent. */
    public function restaurer(Contexte $c, int $id): string
    {
        return $this->bd->transaction(function () use ($c, $id): string {
            $org = $c->organisationId();
            $r = $this->bd->un('SELECT * FROM avecxy_retraits WHERE organisation_id = ? AND id = ? AND supprime_le IS NOT NULL', [$org, $id]) ?? throw new ErreurMetier('retraits.introuvable');
            $this->carnets->verrouiller($org, (int) $r['carnet_id']) ?? throw new ErreurMetier('carnets.carnet_introuvable');
            $this->operations->modifierRetrait($id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            $this->bd->executer('UPDATE avecxy_frais_tenue SET supprime_le = NULL, supprime_par = NULL WHERE retrait_id = ?', [$id]);
            if ((int) $r['ferme_carnet'] === 1) {
                $this->carnets->modifier((int) $r['carnet_id'], ['statut' => 'FERME', 'date_fermeture' => $r['date_retrait']]);
            }
            $this->recalcul->recalculer($org, (int) $r['carnet_id']);   // refuse si l'epargne deviendrait negative
            $this->audit->noter($org, $c->utilisateurId(), 'retrait.restaurer', 'retrait', $id);
            return t('corbeille.section.retraits') . ' #' . $id;
        });
    }
}

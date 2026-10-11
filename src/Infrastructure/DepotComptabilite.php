<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/**
 * Comptabilite en partie double, calculee a partir des operations (comme la caisse) :
 * chaque operation donne une ecriture (debit d'un compte, credit d'un autre), designee par le role
 * du compte (caisse, depots, credits...). L'organisation choisit quel compte de son plan joue chaque role.
 * S'y ajoutent les ecritures saisies a la main. Rien n'est recopie : corriger une operation corrige la comptabilite.
 */
final class DepotComptabilite
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    // ------------------------------------------------------------------
    // Plan de comptes
    // ------------------------------------------------------------------

    public function comptes(int $org, bool $tous = false): array
    {
        return $this->bd->tous(
            'SELECT cc.*, (SELECT COUNT(*) FROM avecxy_ecriture_lignes l JOIN avecxy_ecritures e ON e.id = l.ecriture_id
                           WHERE l.compte_id = cc.id AND e.supprime_le IS NULL) AS nb_lignes
             FROM avecxy_compta_comptes cc WHERE cc.organisation_id = ? AND cc.supprime_le IS NULL' . ($tous ? '' : ' AND cc.actif = 1') . ' ORDER BY cc.numero',
            [$org]
        );
    }

    public function compte(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_compta_comptes WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
    }

    public function creerCompte(array $d): int
    {
        return $this->bd->inserer('avecxy_compta_comptes', $d);
    }

    public function modifierCompte(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_compta_comptes', $d, ['id' => $id]);
    }

    public function numeroPris(int $org, string $numero, int $saufId): bool
    {
        return $this->bd->valeur('SELECT COUNT(*) FROM avecxy_compta_comptes WHERE organisation_id = ? AND numero = ? AND id <> ? AND supprime_le IS NULL', [$org, $numero, $saufId]) > 0;
    }

    /** Donne un role a un compte (et le retire au compte qui l'avait). */
    public function affecter(int $org, string $cle, int $compteId): void
    {
        $this->bd->executer('UPDATE avecxy_compta_comptes SET cle = NULL WHERE organisation_id = ? AND cle = ?', [$org, $cle]);
        $this->bd->executer('UPDATE avecxy_compta_comptes SET cle = ? WHERE organisation_id = ? AND id = ?', [$cle, $org, $compteId]);
    }

    // ------------------------------------------------------------------
    // Ecritures manuelles
    // ------------------------------------------------------------------

    /** @param array<int,array{compte_id:int,debit:string,credit:string}> $lignes */
    public function creerEcriture(array $d, array $lignes): int
    {
        $id = $this->bd->inserer('avecxy_ecritures', $d);
        foreach ($lignes as $l) {
            $this->bd->inserer('avecxy_ecriture_lignes', $l + ['ecriture_id' => $id]);
        }
        return $id;
    }

    public function ecritures(int $org, string $du, string $au, ?int $agence): array
    {
        [$filtre, $p] = self::filtreAgence('e.agence_id', $agence);
        $ecritures = $this->bd->tous(
            "SELECT e.*, u.nom AS auteur, ag.nom AS agence_nom FROM avecxy_ecritures e LEFT JOIN avecxy_utilisateurs u ON u.id = e.cree_par
             LEFT JOIN avecxy_agences ag ON ag.id = e.agence_id
             WHERE e.organisation_id = ? AND e.supprime_le IS NULL AND e.date_ecriture >= ? AND e.date_ecriture <= ?$filtre ORDER BY e.date_ecriture DESC, e.id DESC LIMIT 500",
            array_merge([$org, $du, $au], $p)
        );
        foreach ($ecritures as &$e) {
            $e['lignes'] = $this->bd->tous(
                'SELECT l.*, cc.numero, cc.libelle FROM avecxy_ecriture_lignes l JOIN avecxy_compta_comptes cc ON cc.id = l.compte_id WHERE l.ecriture_id = ? ORDER BY l.id',
                [$e['id']]
            );
        }
        return $ecritures;
    }

    public function marquerEcriture(int $org, int $id, ?string $quand, ?int $par, ?string $motif): int
    {
        return $this->bd->executer(
            'UPDATE avecxy_ecritures SET supprime_le = ?, supprime_par = ?, motif_suppression = ? WHERE organisation_id = ? AND id = ?',
            [$quand, $par, $motif, $org, $id]
        );
    }

    // ------------------------------------------------------------------
    // Lignes comptables (automatiques + manuelles)
    // ------------------------------------------------------------------

    /**
     * Ecritures automatiques : une ligne par operation, avec le role du compte debite et du compte credite.
     * @return array{0:string,1:array} SQL (jour, type, ref, ag_d, ag_c, cle_d, cle_c, montant) et parametres
     */
    private function requeteEcritures(int $org): array
    {
        $ag = static fn (string $agent): string => "(SELECT ga.agence_id FROM avecxy_agents ga WHERE ga.id = $agent)";
        $benefice = static fn (string $source): string => "(SELECT COALESCE(SUM(b.montant_total),0) FROM avecxy_benefices b WHERE b.source = '$source' AND b.source_id = rb.id AND b.credit_id = rb.credit_id)";
        $parties = [
            // vente de carnet : le prix entre en caisse, c'est un produit
            "SELECT k.date_ouverture AS jour, 'VENTE_CARNET' AS type, c.code AS ref, {$ag('k.agent_id')} AS ag_d, {$ag('k.agent_id')} AS ag_c, 'caisse' AS cle_d, 'produit_vente' AS cle_c, k.prix AS montant
               FROM avecxy_carnets k JOIN avecxy_clients c ON c.id = k.client_id WHERE k.organisation_id = ? AND k.supprime_le IS NULL AND k.prix > 0 AND k.validation = 'VALIDE'",
            // cotisation : la part d'epargne est due au client, la part de benefice est un produit
            "SELECT o.date_cotisation, 'COTISATION', c.code, {$ag('o.agent_id')}, {$ag('o.agent_id')}, 'caisse', 'depots', o.montant_epargne
               FROM avecxy_cotisations o JOIN avecxy_clients c ON c.id = o.client_id WHERE o.organisation_id = ? AND o.statut = 'VALIDE' AND o.supprime_le IS NULL",
            "SELECT o.date_cotisation, 'PART_BENEFICE', c.code, {$ag('o.agent_id')}, {$ag('o.agent_id')}, 'caisse', 'produit_part', o.montant_benefice
               FROM avecxy_cotisations o JOIN avecxy_clients c ON c.id = o.client_id WHERE o.organisation_id = ? AND o.statut = 'VALIDE' AND o.supprime_le IS NULL",
            "SELECT r.date_retrait, 'RETRAIT_CLIENT', c.code, {$ag('r.agent_id')}, {$ag('r.agent_id')}, 'depots', 'caisse', r.montant_retire
               FROM avecxy_retraits r JOIN avecxy_clients c ON c.id = r.client_id WHERE r.organisation_id = ? AND r.statut = 'VALIDE' AND r.supprime_le IS NULL",
            // frais de tenue, de retrait, de cloture et penalites de sortie anticipee : pris sur l'epargne
            "SELECT f.date_frais, 'FRAIS', c.code, {$ag('f.agent_id')}, {$ag('f.agent_id')}, 'depots', 'produit_frais', f.montant
               FROM avecxy_frais_tenue f JOIN avecxy_clients c ON c.id = f.client_id WHERE f.organisation_id = ? AND f.supprime_le IS NULL",
            "SELECT i.date_interet, 'INTERET_EPARGNE', c.code, {$ag('i.agent_id')}, {$ag('i.agent_id')}, 'charge_interet_epargne', 'depots', i.montant
               FROM avecxy_interets_epargne i JOIN avecxy_clients c ON c.id = i.client_id WHERE i.organisation_id = ? AND i.supprime_le IS NULL",
            // credit : le pret est une creance ; des frais retenus sont un produit, le reste sort de la caisse
            "SELECT cr.date_credit, 'CREDIT', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'credits', 'caisse', cr.montant - (CASE WHEN cr.frais_dossier_mode = 'RETENU' THEN cr.frais_dossier ELSE 0 END)
               FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id WHERE cr.organisation_id = ? AND cr.statut <> 'ANNULE' AND cr.supprime_le IS NULL AND cr.reprise_id IS NULL",
            "SELECT cr.date_credit, 'FRAIS_DOSSIER', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'credits', 'produit_dossier', cr.frais_dossier
               FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id WHERE cr.organisation_id = ? AND cr.statut <> 'ANNULE' AND cr.supprime_le IS NULL AND cr.reprise_id IS NULL AND cr.frais_dossier_mode = 'RETENU'",
            // remboursement : d'abord le capital, puis interets (frais ajoutes compris) et penalites, comme le partage des benefices
            "SELECT rb.date_paiement, 'REMBOURSEMENT', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'caisse', 'credits', rb.montant - {$benefice('INTERET_CREDIT')} - {$benefice('PENALITE_CREDIT')}
               FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
               WHERE rb.organisation_id = ? AND rb.statut = 'VALIDE' AND rb.supprime_le IS NULL AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'",
            "SELECT rb.date_paiement, 'INTERET_CREDIT', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'caisse', 'produit_interet', {$benefice('INTERET_CREDIT')}
               FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
               WHERE rb.organisation_id = ? AND rb.statut = 'VALIDE' AND rb.supprime_le IS NULL AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'",
            "SELECT rb.date_paiement, 'PENALITE_CREDIT', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'caisse', 'produit_penalite', {$benefice('PENALITE_CREDIT')}
               FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
               WHERE rb.organisation_id = ? AND rb.statut = 'VALIDE' AND rb.supprime_le IS NULL AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'",
            // fonds propres (le solde repris des cahiers va au report a nouveau)
            "SELECT m.date_mouvement, 'APPORT', COALESCE(m.source,''), m.agence_id, m.agence_id, 'caisse', CASE WHEN m.reprise_id IS NULL THEN 'capital' ELSE 'report' END, m.montant
               FROM avecxy_mouvements_fonds m WHERE m.organisation_id = ? AND m.type = 'APPORT' AND m.statut = 'VALIDE' AND m.supprime_le IS NULL",
            "SELECT m.date_mouvement, 'RETRAIT_FONDS', COALESCE(m.source,''), m.agence_id, m.agence_id, 'capital', 'caisse', m.montant
               FROM avecxy_mouvements_fonds m WHERE m.organisation_id = ? AND m.type = 'RETRAIT' AND m.statut = 'VALIDE' AND m.supprime_le IS NULL",
            "SELECT e.date_emprunt, 'EMPRUNT', e.preteur, e.agence_id, e.agence_id, 'caisse', 'emprunts', e.montant
               FROM avecxy_emprunts e WHERE e.organisation_id = ? AND e.supprime_le IS NULL",
            // remboursement d'emprunt : le capital d'abord, puis l'interet (une charge)
            "SELECT x.date_paiement, 'REMBOURSEMENT_EMPRUNT', x.preteur, x.agence_id, x.agence_id, 'emprunts', 'caisse',
                    CASE WHEN x.avant >= x.capital THEN 0 WHEN x.avant + x.montant <= x.capital THEN x.montant ELSE x.capital - x.avant END
               FROM (SELECT er.date_paiement, er.montant, e.preteur, e.agence_id, e.montant AS capital,
                       (SELECT COALESCE(SUM(r2.montant),0) FROM avecxy_emprunt_remboursements r2 WHERE r2.emprunt_id = er.emprunt_id AND r2.supprime_le IS NULL
                          AND (r2.date_paiement < er.date_paiement OR (r2.date_paiement = er.date_paiement AND r2.id < er.id))) AS avant
                     FROM avecxy_emprunt_remboursements er JOIN avecxy_emprunts e ON e.id = er.emprunt_id
                     WHERE er.organisation_id = ? AND er.supprime_le IS NULL AND e.supprime_le IS NULL) x",
            "SELECT x.date_paiement, 'INTERET_EMPRUNT', x.preteur, x.agence_id, x.agence_id, 'charge_interet_emprunt', 'caisse',
                    x.montant - (CASE WHEN x.avant >= x.capital THEN 0 WHEN x.avant + x.montant <= x.capital THEN x.montant ELSE x.capital - x.avant END)
               FROM (SELECT er.date_paiement, er.montant, e.preteur, e.agence_id, e.montant AS capital,
                       (SELECT COALESCE(SUM(r2.montant),0) FROM avecxy_emprunt_remboursements r2 WHERE r2.emprunt_id = er.emprunt_id AND r2.supprime_le IS NULL
                          AND (r2.date_paiement < er.date_paiement OR (r2.date_paiement = er.date_paiement AND r2.id < er.id))) AS avant
                     FROM avecxy_emprunt_remboursements er JOIN avecxy_emprunts e ON e.id = er.emprunt_id
                     WHERE er.organisation_id = ? AND er.supprime_le IS NULL AND e.supprime_le IS NULL) x",
            // recolteurs : leur part est une charge ; un manquant deduit de leur paie est recupere
            "SELECT pa.date_paiement, 'PAIEMENT_RECOLTEUR', a.code, a.agence_id, a.agence_id, 'charge_recolteurs', 'caisse', pa.montant_paye
               FROM avecxy_paiements_agents pa JOIN avecxy_agents a ON a.id = pa.agent_id WHERE pa.organisation_id = ? AND pa.supprime_le IS NULL",
            "SELECT pa.date_paiement, 'MANQUANT_DEDUIT', a.code, a.agence_id, a.agence_id, 'charge_recolteurs', 'produit_divers', pa.manquants_deduits
               FROM avecxy_paiements_agents pa JOIN avecxy_agents a ON a.id = pa.agent_id WHERE pa.organisation_id = ? AND pa.supprime_le IS NULL",
            "SELECT rf.date_remise, 'MANQUANT', a.code, a.agence_id, a.agence_id, 'charge_manquants', 'caisse', rf.manquant
               FROM avecxy_remises_fonds rf JOIN avecxy_agents a ON a.id = rf.agent_id WHERE rf.organisation_id = ? AND rf.supprime_le IS NULL",
            "SELECT rf.date_remise, 'EXCEDENT', a.code, a.agence_id, a.agence_id, 'caisse', 'produit_divers', rf.excedent
               FROM avecxy_remises_fonds rf JOIN avecxy_agents a ON a.id = rf.agent_id WHERE rf.organisation_id = ? AND rf.supprime_le IS NULL",
            "SELECT mr.date_paiement, 'REGLEMENT_MANQUANT', a.code, a.agence_id, a.agence_id, 'caisse', 'produit_divers', mr.montant
               FROM avecxy_manquant_reglements mr JOIN avecxy_agents a ON a.id = mr.agent_id WHERE mr.organisation_id = ? AND mr.supprime_le IS NULL",
            "SELECT rp.date_paiement, 'REPARTITION', rp.periode, NULL, NULL, 'repartitions', 'caisse',
                    (SELECT COALESCE(SUM(l.montant),0) FROM avecxy_repartition_lignes l WHERE l.repartition_id = rp.id)
               FROM avecxy_repartitions rp WHERE rp.organisation_id = ? AND rp.supprime_le IS NULL",
            // transfert : d'une caisse a l'autre (meme compte, deux agences)
            "SELECT t.date_transfert, 'TRANSFERT', COALESCE(t.motif,''), t.vers_agence_id, t.de_agence_id, 'caisse', 'caisse', t.montant
               FROM avecxy_transferts_fonds t WHERE t.organisation_id = ? AND t.supprime_le IS NULL",
            // ouverture d'une reprise de l'existant : epargne due et credits dehors au jour de la reprise
            "SELECT rp.date_demarrage, 'REPRISE_EPARGNE', c.code, {$ag('k.agent_id')}, {$ag('k.agent_id')}, 'report', 'depots', k.reprise_epargne
               FROM avecxy_carnets k JOIN avecxy_reprises rp ON rp.id = k.reprise_id JOIN avecxy_clients c ON c.id = k.client_id
               WHERE k.organisation_id = ? AND k.supprime_le IS NULL AND rp.annulee_le IS NULL",
            "SELECT rp.date_demarrage, 'REPRISE_CREDIT', c.code, {$ag('cr.agent_id')}, {$ag('cr.agent_id')}, 'credits', 'report',
                    CASE WHEN cr.montant > cr.reprise_rembourse THEN cr.montant - cr.reprise_rembourse ELSE 0 END
               FROM avecxy_credits cr JOIN avecxy_reprises rp ON rp.id = cr.reprise_id JOIN avecxy_clients c ON c.id = cr.client_id
               WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE' AND rp.annulee_le IS NULL",
        ];
        return [implode("\nUNION ALL\n", $parties), array_fill(0, count($parties), $org)];
    }

    /**
     * Toutes les lignes comptables (une par compte touche), automatiques et manuelles.
     * @return array{0:string,1:array} SQL (jour, type, ref, agence, compte_id, debit, credit, ecriture_id) et parametres
     */
    public function requeteLignes(int $org): array
    {
        [$ecritures, $p] = $this->requeteEcritures($org);
        $sql = "SELECT e.jour, e.type, e.ref, CASE s.n WHEN 1 THEN e.ag_d ELSE e.ag_c END AS agence, cc.id AS compte_id,
                       CASE s.n WHEN 1 THEN e.montant ELSE 0 END AS debit, CASE s.n WHEN 2 THEN e.montant ELSE 0 END AS credit, NULL AS ecriture_id
                  FROM ($ecritures) e CROSS JOIN (SELECT 1 AS n UNION ALL SELECT 2) s
                  JOIN avecxy_compta_comptes cc ON cc.organisation_id = ? AND cc.supprime_le IS NULL AND cc.cle = (CASE s.n WHEN 1 THEN e.cle_d ELSE e.cle_c END)
                 WHERE e.montant <> 0
                UNION ALL
                SELECT ec.date_ecriture, 'ECRITURE', COALESCE(ec.piece, ec.libelle), ec.agence_id, l.compte_id, l.debit, l.credit, ec.id
                  FROM avecxy_ecriture_lignes l JOIN avecxy_ecritures ec ON ec.id = l.ecriture_id
                 WHERE ec.organisation_id = ? AND ec.supprime_le IS NULL";
        return [$sql, array_merge($p, [$org, $org])];
    }

    /** @return array{0:string,1:array} */
    private static function filtreAgence(string $colonne, ?int $agence): array
    {
        if ($agence === null) {
            return ['', []];
        }
        return $agence === 0 ? [" AND $colonne IS NULL", []] : [" AND $colonne = ?", [$agence]];
    }

    /**
     * Totaux par compte : debit et credit avant la periode (report) et pendant la periode.
     * @param ?string $du null = depuis le debut
     * @return array<int,array{compte_id:int,avant_debit:mixed,avant_credit:mixed,debit:mixed,credit:mixed}> indexe par compte
     */
    public function totaux(int $org, ?string $du, string $au, ?int $agence): array
    {
        [$sql, $p] = $this->requeteLignes($org);
        [$filtre, $pa] = self::filtreAgence('x.agence', $agence);
        $du ??= '0000-01-01';
        $lignes = $this->bd->tous(
            "SELECT x.compte_id,
                    COALESCE(SUM(CASE WHEN x.jour < ? THEN x.debit ELSE 0 END),0) AS avant_debit,
                    COALESCE(SUM(CASE WHEN x.jour < ? THEN x.credit ELSE 0 END),0) AS avant_credit,
                    COALESCE(SUM(CASE WHEN x.jour >= ? THEN x.debit ELSE 0 END),0) AS debit,
                    COALESCE(SUM(CASE WHEN x.jour >= ? THEN x.credit ELSE 0 END),0) AS credit
               FROM ($sql) x WHERE x.jour <= ?$filtre GROUP BY x.compte_id",
            array_merge([$du, $du, $du, $du], $p, [$au], $pa)
        );
        return array_column($lignes, null, 'compte_id');
    }

    /** Lignes d'une periode (journal, ou grand livre d'un compte). */
    public function lignes(int $org, string $du, string $au, ?int $agence, int $compteId = 0, int $limite = 3000): array
    {
        [$sql, $p] = $this->requeteLignes($org);
        [$filtre, $pa] = self::filtreAgence('x.agence', $agence);
        $compte = '';
        if ($compteId > 0) {
            $compte = ' AND x.compte_id = ?';
            $pa[] = $compteId;
        }
        return $this->bd->tous(
            "SELECT x.*, cc.numero, cc.libelle AS compte_libelle, ag.nom AS agence_nom
               FROM ($sql) x JOIN avecxy_compta_comptes cc ON cc.id = x.compte_id LEFT JOIN avecxy_agences ag ON ag.id = x.agence
              WHERE x.jour >= ? AND x.jour <= ?$filtre$compte ORDER BY x.jour, x.type, x.ref, x.credit, cc.numero LIMIT " . (int) $limite,
            array_merge($p, [$du, $au], $pa)
        );
    }
}

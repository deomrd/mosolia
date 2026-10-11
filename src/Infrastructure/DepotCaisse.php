<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Mouvements d'argent hors carnets : remises des recolteurs, apports et retraits de fonds, paiements. */
final class DepotCaisse
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function creerRemise(array $d): int
    {
        return $this->bd->inserer('avecxy_remises_fonds', $d);
    }

    public function remises(int $org, string $periode, int $agentId = 0): array
    {
        $sql = 'SELECT r.*, a.code AS recolteur_code, a.nom AS recolteur_nom FROM avecxy_remises_fonds r JOIN avecxy_agents a ON a.id = r.agent_id
                WHERE r.organisation_id = ? AND r.periode = ? AND r.supprime_le IS NULL';
        $p = [$org, $periode];
        if ($agentId > 0) {
            $sql .= ' AND r.agent_id = ?';
            $p[] = $agentId;
        }
        return $this->bd->tous($sql . ' ORDER BY r.date_remise DESC, r.id DESC', $p);
    }

    /** Par recolteur pour un jour : ce qu'il a encaisse sur le terrain et ce qui a deja ete controle. */
    public function collecteDuJour(int $org, string $jour): array
    {
        return $this->bd->tous(
            "SELECT a.id, a.code, a.nom,
                (SELECT COALESCE(SUM(o.montant_total),0) FROM avecxy_cotisations o WHERE o.agent_id = a.id AND o.date_cotisation = ? AND o.statut <> 'REJETE' AND o.supprime_le IS NULL) AS cotise,
                (SELECT COALESCE(SUM(k.prix),0) FROM avecxy_carnets k WHERE k.agent_id = a.id AND k.date_ouverture = ? AND k.supprime_le IS NULL) AS ventes,
                (SELECT COALESCE(SUM(r.montant_attendu),0) FROM avecxy_remises_fonds r WHERE r.agent_id = a.id AND r.date_remise = ? AND r.supprime_le IS NULL) AS controle
             FROM avecxy_agents a WHERE a.organisation_id = ? AND a.supprime_le IS NULL ORDER BY a.nom",
            [$jour, $jour, $jour, $org]
        );
    }

    /** Par recolteur : manquants constates, deja deduits de ses paiements, deja regles, et ce qu'il doit encore. */
    public function manquantsDus(int $org, int $agentId = 0): array
    {
        $lignes = $this->bd->tous(
            'SELECT a.id, a.code, a.nom,
                (SELECT COALESCE(SUM(r.manquant),0) FROM avecxy_remises_fonds r WHERE r.agent_id = a.id AND r.supprime_le IS NULL) AS constates,
                (SELECT COALESCE(SUM(p.manquants_deduits),0) FROM avecxy_paiements_agents p WHERE p.agent_id = a.id AND p.supprime_le IS NULL) AS deduits,
                (SELECT COALESCE(SUM(m.montant),0) FROM avecxy_manquant_reglements m WHERE m.agent_id = a.id AND m.supprime_le IS NULL) AS regles
             FROM avecxy_agents a WHERE a.organisation_id = ?' . ($agentId > 0 ? ' AND a.id = ?' : ' AND a.supprime_le IS NULL') . ' ORDER BY a.nom',
            $agentId > 0 ? [$org, $agentId] : [$org]
        );
        foreach ($lignes as &$l) {
            $l['du'] = max(0, (int) round(((float) $l['constates'] - (float) $l['deduits'] - (float) $l['regles']) * 100));
        }
        return $lignes;
    }

    public function creerReglement(array $d): int
    {
        return $this->bd->inserer('avecxy_manquant_reglements', $d);
    }

    public function reglements(int $org, string $periode): array
    {
        return $this->bd->tous(
            'SELECT m.*, a.code AS recolteur_code, a.nom AS recolteur_nom FROM avecxy_manquant_reglements m JOIN avecxy_agents a ON a.id = m.agent_id
             WHERE m.organisation_id = ? AND m.date_paiement LIKE ? AND m.supprime_le IS NULL ORDER BY m.date_paiement DESC, m.id DESC',
            [$org, $periode . '%']
        );
    }

    // ---------------- emprunts de l'organisation ----------------

    public function emprunts(int $org): array
    {
        return $this->bd->tous(
            'SELECT e.*, (SELECT COALESCE(SUM(r.montant),0) FROM avecxy_emprunt_remboursements r WHERE r.emprunt_id = e.id AND r.supprime_le IS NULL) AS rembourse
             FROM avecxy_emprunts e WHERE e.organisation_id = ? AND e.supprime_le IS NULL ORDER BY e.id DESC LIMIT 200',
            [$org]
        );
    }

    public function emprunt(int $org, int $id): ?array
    {
        return $this->bd->un(
            'SELECT e.*, (SELECT COALESCE(SUM(r.montant),0) FROM avecxy_emprunt_remboursements r WHERE r.emprunt_id = e.id AND r.supprime_le IS NULL) AS rembourse
             FROM avecxy_emprunts e WHERE e.organisation_id = ? AND e.id = ? AND e.supprime_le IS NULL',
            [$org, $id]
        );
    }

    /**
     * Interets d'emprunts payes pendant un mois (AAAA-MM) : une charge de l'organisation.
     * Chaque remboursement couvre d'abord le capital emprunte, puis l'interet.
     */
    public function chargesEmprunts(int $org, string $periode): int
    {
        $lignes = $this->bd->tous(
            'SELECT r.emprunt_id, r.date_paiement, r.montant, e.montant AS capital FROM avecxy_emprunt_remboursements r JOIN avecxy_emprunts e ON e.id = r.emprunt_id
             WHERE r.organisation_id = ? AND r.supprime_le IS NULL AND e.supprime_le IS NULL ORDER BY r.emprunt_id, r.date_paiement, r.id',
            [$org]
        );
        $charges = 0;
        $deja = [];
        foreach ($lignes as $l) {
            $capital = (int) round((float) $l['capital'] * 100);
            $avant = $deja[$l['emprunt_id']] ?? 0;
            $apres = $avant + (int) round((float) $l['montant'] * 100);
            $deja[$l['emprunt_id']] = $apres;
            if (str_starts_with((string) $l['date_paiement'], $periode)) {
                $charges += max(0, $apres - $capital) - max(0, $avant - $capital);
            }
        }
        return $charges;
    }

    /** Premier mois (AAAA-MM) ou un emprunt a ete rembourse, ou null. */
    public function premierMoisDeCharge(int $org): ?string
    {
        $d = $this->bd->valeur('SELECT MIN(date_paiement) FROM avecxy_emprunt_remboursements WHERE organisation_id = ? AND supprime_le IS NULL', [$org]);
        return $d === null ? null : substr((string) $d, 0, 7);
    }

    public function creerEmprunt(array $d): int
    {
        return $this->bd->inserer('avecxy_emprunts', $d);
    }

    public function creerRemboursementEmprunt(array $d): int
    {
        return $this->bd->inserer('avecxy_emprunt_remboursements', $d);
    }

    public function remboursementsEmprunts(int $org): array
    {
        return $this->bd->tous(
            'SELECT r.*, e.preteur FROM avecxy_emprunt_remboursements r JOIN avecxy_emprunts e ON e.id = r.emprunt_id
             WHERE r.organisation_id = ? AND r.supprime_le IS NULL AND e.supprime_le IS NULL ORDER BY r.date_paiement DESC, r.id DESC LIMIT 50',
            [$org]
        );
    }

    public function creerMouvement(array $d): int
    {
        return $this->bd->inserer('avecxy_mouvements_fonds', $d);
    }

    public function mouvements(int $org, string $periode): array
    {
        return $this->bd->tous(
            "SELECT m.*, u.nom AS auteur_nom FROM avecxy_mouvements_fonds m LEFT JOIN avecxy_utilisateurs u ON u.id = m.cree_par
             WHERE m.organisation_id = ? AND m.date_mouvement LIKE ? AND m.supprime_le IS NULL AND m.statut = 'VALIDE'
             ORDER BY m.date_mouvement DESC, m.id DESC",
            [$org, $periode . '%']
        );
    }

    // ---------------- regles de partage et de repartition ----------------

    public function reglesPartage(int $org): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_regles_partage WHERE organisation_id = ? ORDER BY source, applicable_du DESC, id DESC', [$org]);
    }

    public function creerReglePartage(int $org, string $source, string $pctAgent, string $pctOrganisation, string $date, int $par): int
    {
        $this->bd->executer('DELETE FROM avecxy_regles_partage WHERE organisation_id = ? AND source = ? AND applicable_du = ?
            AND id NOT IN (SELECT regle_partage_id FROM avecxy_benefices WHERE regle_partage_id IS NOT NULL)', [$org, $source, $date]);
        $existante = $this->bd->un('SELECT id FROM avecxy_regles_partage WHERE organisation_id = ? AND source = ? AND applicable_du = ?', [$org, $source, $date]);
        if ($existante !== null) {
            $this->bd->modifier('avecxy_regles_partage', ['pourcentage_agent' => $pctAgent, 'pourcentage_organisation' => $pctOrganisation], ['id' => $existante['id']]);
            return (int) $existante['id'];
        }
        return $this->bd->inserer('avecxy_regles_partage', [
            'organisation_id' => $org, 'source' => $source, 'pourcentage_agent' => $pctAgent, 'pourcentage_organisation' => $pctOrganisation,
            'applicable_du' => $date, 'cree_par' => $par, 'cree_le' => $this->bd->maintenant(),
        ]);
    }

    /** Carnets dont un benefice de cette source est date d'apres la regle (a recalculer). */
    public function carnetsConcernes(int $org, string $source, string $depuis): array
    {
        return array_map('intval', array_column($this->bd->tous(
            'SELECT DISTINCT carnet_id FROM avecxy_benefices WHERE organisation_id = ? AND source = ? AND date_operation >= ? AND carnet_id IS NOT NULL',
            [$org, $source, $depuis]
        ), 'carnet_id'));
    }

    public function beneficesDeCredit(int $org, string $source, string $depuis): array
    {
        return $this->bd->tous(
            'SELECT id, date_operation, montant_total FROM avecxy_benefices WHERE organisation_id = ? AND source = ? AND date_operation >= ? AND credit_id IS NOT NULL',
            [$org, $source, $depuis]
        );
    }

    public function modifierBenefice(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_benefices', $d, ['id' => $id]);
    }

    public function reglesRepartition(int $org): array
    {
        $regles = $this->bd->tous('SELECT * FROM avecxy_regles_repartition WHERE organisation_id = ? ORDER BY applicable_du DESC, id DESC', [$org]);
        foreach ($regles as &$r) {
            $r['lignes'] = $this->bd->tous('SELECT * FROM avecxy_regles_repartition_lignes WHERE regle_id = ? ORDER BY ordre, id', [$r['id']]);
        }
        return $regles;
    }

    /** Regle de repartition en vigueur a la fin de la periode (AAAA-MM). */
    public function regleRepartition(int $org, string $date): ?array
    {
        $r = $this->bd->un('SELECT * FROM avecxy_regles_repartition WHERE organisation_id = ? AND applicable_du <= ? ORDER BY applicable_du DESC, id DESC LIMIT 1', [$org, $date]);
        if ($r !== null) {
            $r['lignes'] = $this->bd->tous('SELECT * FROM avecxy_regles_repartition_lignes WHERE regle_id = ? ORDER BY ordre, id', [$r['id']]);
        }
        return $r;
    }

    /** @param array<int,array{libelle:string,pourcentage:string}> $lignes */
    public function creerRegleRepartition(int $org, string $nom, string $date, array $lignes, int $par): int
    {
        $id = $this->bd->inserer('avecxy_regles_repartition', ['organisation_id' => $org, 'nom' => $nom, 'applicable_du' => $date, 'cree_par' => $par, 'cree_le' => $this->bd->maintenant()]);
        foreach (array_values($lignes) as $i => $l) {
            $this->bd->inserer('avecxy_regles_repartition_lignes', ['regle_id' => $id, 'libelle' => $l['libelle'], 'pourcentage' => $l['pourcentage'], 'ordre' => $i]);
        }
        return $id;
    }

    public function regleRepartitionExiste(int $org, string $date): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_regles_repartition WHERE organisation_id = ? AND applicable_du = ?', [$org, $date]);
    }

    // ---------------- paiement des recolteurs et repartition ----------------

    /** Par recolteur pour un mois : part gagnee, manquants, paiement deja fait. */
    public function syntheseRecolteurs(int $org, string $periode): array
    {
        return $this->bd->tous(
            "SELECT a.id, a.code, a.nom,
                (SELECT COALESCE(SUM(b.part_agent),0) FROM avecxy_benefices b WHERE b.agent_id = a.id AND b.periode = ?) AS part_agent,
                (SELECT COALESCE(SUM(r.manquant),0) FROM avecxy_remises_fonds r WHERE r.agent_id = a.id AND r.periode = ? AND r.supprime_le IS NULL) AS manquants,
                (SELECT COALESCE(SUM(r.excedent),0) FROM avecxy_remises_fonds r WHERE r.agent_id = a.id AND r.periode = ? AND r.supprime_le IS NULL) AS excedents,
                (SELECT p.montant_paye FROM avecxy_paiements_agents p WHERE p.agent_id = a.id AND p.periode = ? AND p.supprime_le IS NULL LIMIT 1) AS paye,
                (SELECT p.id FROM avecxy_paiements_agents p WHERE p.agent_id = a.id AND p.periode = ? AND p.supprime_le IS NULL LIMIT 1) AS paiement_id,
                (SELECT p.date_paiement FROM avecxy_paiements_agents p WHERE p.agent_id = a.id AND p.periode = ? AND p.supprime_le IS NULL LIMIT 1) AS paye_le
             FROM avecxy_agents a WHERE a.organisation_id = ? AND a.supprime_le IS NULL ORDER BY a.nom",
            [$periode, $periode, $periode, $periode, $periode, $periode, $org]
        );
    }

    public function creerPaiementRecolteur(array $d): int
    {
        return $this->bd->inserer('avecxy_paiements_agents', $d);
    }

    /** Part de l'organisation dans les benefices du mois : la base a repartir. */
    public function baseRepartition(int $org, string $periode): mixed
    {
        return $this->bd->valeur('SELECT COALESCE(SUM(part_organisation),0) FROM avecxy_benefices WHERE organisation_id = ? AND periode = ?', [$org, $periode]);
    }

    public function repartition(int $org, string $periode): ?array
    {
        $r = $this->bd->un('SELECT * FROM avecxy_repartitions WHERE organisation_id = ? AND periode = ? AND supprime_le IS NULL', [$org, $periode]);
        if ($r !== null) {
            $r['lignes'] = $this->bd->tous('SELECT * FROM avecxy_repartition_lignes WHERE repartition_id = ? ORDER BY id', [$r['id']]);
        }
        return $r;
    }

    public function repartitions(int $org): array
    {
        return $this->bd->tous(
            'SELECT r.*, (SELECT COALESCE(SUM(l.montant),0) FROM avecxy_repartition_lignes l WHERE l.repartition_id = r.id) AS total
             FROM avecxy_repartitions r WHERE r.organisation_id = ? AND r.supprime_le IS NULL ORDER BY r.periode DESC', [$org]);
    }

    public function creerRepartition(array $d, array $lignes): int
    {
        $id = $this->bd->inserer('avecxy_repartitions', $d);
        foreach ($lignes as $l) {
            $this->bd->inserer('avecxy_repartition_lignes', ['repartition_id' => $id] + $l);
        }
        return $id;
    }

    /** Envoie a la corbeille (ou en sort) une ligne d'une des tables de la caisse. */
    public function marquer(string $table, int $org, int $id, ?string $quand, ?int $par, ?string $motif): int
    {
        return $this->bd->executer(
            "UPDATE $table SET supprime_le = ?, supprime_par = ?, motif_suppression = ? WHERE organisation_id = ? AND id = ? AND supprime_le IS " . ($quand === null ? 'NOT NULL' : 'NULL'),
            [$quand, $par, $motif, $org, $id]
        );
    }

    public function supprimees(string $table, int $org): array
    {
        return $this->bd->tous("SELECT * FROM $table WHERE organisation_id = ? AND supprime_le IS NOT NULL ORDER BY supprime_le DESC LIMIT 200", [$org]);
    }

    /**
     * Toutes les entrees et sorties d'argent de l'organisation, une ligne par operation.
     * C'est la definition unique de la caisse : le solde et le journal en decoulent tous les deux.
     * Chaque ligne porte son agence (celle du recolteur, du mouvement ou du transfert ; NULL = siege).
     * @return array{0:string,1:array} SQL (colonnes jour, type, entree, sortie, libelle, agent_id, agence) et parametres
     */
    public function requeteFlux(int $org): array
    {
        $ag = static fn (string $agent): string => "(SELECT ga.agence_id FROM avecxy_agents ga WHERE ga.id = $agent)";
        $sql = "
            SELECT m.date_mouvement AS jour, 'APPORT' AS type, m.montant AS entree, 0 AS sortie, COALESCE(m.source,'') AS libelle, NULL AS agent_id, m.agence_id AS agence
              FROM avecxy_mouvements_fonds m WHERE m.organisation_id = ? AND m.type = 'APPORT' AND m.statut = 'VALIDE' AND m.supprime_le IS NULL
            UNION ALL
            SELECT m.date_mouvement, 'RETRAIT_FONDS', 0, m.montant, COALESCE(m.source,''), NULL, m.agence_id
              FROM avecxy_mouvements_fonds m WHERE m.organisation_id = ? AND m.type = 'RETRAIT' AND m.statut = 'VALIDE' AND m.supprime_le IS NULL
            UNION ALL
            SELECT k.date_ouverture, 'VENTE_CARNET', k.prix, 0, c.code, k.agent_id, " . $ag('k.agent_id') . "
              FROM avecxy_carnets k JOIN avecxy_clients c ON c.id = k.client_id WHERE k.organisation_id = ? AND k.supprime_le IS NULL AND k.prix > 0 AND k.validation = 'VALIDE'
            UNION ALL
            SELECT o.date_cotisation, 'COTISATION', o.montant_total, 0, c.code, o.agent_id, " . $ag('o.agent_id') . "
              FROM avecxy_cotisations o JOIN avecxy_clients c ON c.id = o.client_id WHERE o.organisation_id = ? AND o.statut = 'VALIDE' AND o.supprime_le IS NULL
            UNION ALL
            SELECT r.date_retrait, 'RETRAIT_CLIENT', 0, r.montant_retire, c.code, r.agent_id, " . $ag('r.agent_id') . "
              FROM avecxy_retraits r JOIN avecxy_clients c ON c.id = r.client_id WHERE r.organisation_id = ? AND r.statut = 'VALIDE' AND r.supprime_le IS NULL
            UNION ALL
            SELECT cr.date_credit, 'CREDIT', 0, cr.montant - (CASE WHEN cr.frais_dossier_mode = 'RETENU' THEN cr.frais_dossier ELSE 0 END), c.code, cr.agent_id, " . $ag('cr.agent_id') . "
              FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id WHERE cr.organisation_id = ? AND cr.statut <> 'ANNULE' AND cr.supprime_le IS NULL AND cr.reprise_id IS NULL
            UNION ALL
            SELECT rb.date_paiement, 'REMBOURSEMENT', rb.montant, 0, c.code, cr.agent_id, " . $ag('cr.agent_id') . "
              FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
              WHERE rb.organisation_id = ? AND rb.statut = 'VALIDE' AND rb.supprime_le IS NULL AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'
            UNION ALL
            SELECT pa.date_paiement, 'PAIEMENT_RECOLTEUR', 0, pa.montant_paye, a.code, pa.agent_id, a.agence_id
              FROM avecxy_paiements_agents pa JOIN avecxy_agents a ON a.id = pa.agent_id WHERE pa.organisation_id = ? AND pa.supprime_le IS NULL
            UNION ALL
            SELECT rf.date_remise, 'MANQUANT', 0, rf.manquant, a.code, rf.agent_id, a.agence_id
              FROM avecxy_remises_fonds rf JOIN avecxy_agents a ON a.id = rf.agent_id WHERE rf.organisation_id = ? AND rf.manquant > 0 AND rf.supprime_le IS NULL
            UNION ALL
            SELECT rf.date_remise, 'EXCEDENT', rf.excedent, 0, a.code, rf.agent_id, a.agence_id
              FROM avecxy_remises_fonds rf JOIN avecxy_agents a ON a.id = rf.agent_id WHERE rf.organisation_id = ? AND rf.excedent > 0 AND rf.supprime_le IS NULL
            UNION ALL
            SELECT mr.date_paiement, 'REGLEMENT_MANQUANT', mr.montant, 0, a.code, mr.agent_id, a.agence_id
              FROM avecxy_manquant_reglements mr JOIN avecxy_agents a ON a.id = mr.agent_id WHERE mr.organisation_id = ? AND mr.supprime_le IS NULL
            UNION ALL
            SELECT e.date_emprunt, 'EMPRUNT', e.montant, 0, e.preteur, NULL, e.agence_id
              FROM avecxy_emprunts e WHERE e.organisation_id = ? AND e.supprime_le IS NULL
            UNION ALL
            SELECT er.date_paiement, 'REMBOURSEMENT_EMPRUNT', 0, er.montant, e.preteur, NULL, e.agence_id
              FROM avecxy_emprunt_remboursements er JOIN avecxy_emprunts e ON e.id = er.emprunt_id
              WHERE er.organisation_id = ? AND er.supprime_le IS NULL AND e.supprime_le IS NULL
            UNION ALL
            SELECT rp.date_paiement, 'REPARTITION', 0, (SELECT COALESCE(SUM(l.montant),0) FROM avecxy_repartition_lignes l WHERE l.repartition_id = rp.id), rp.periode, NULL, NULL
              FROM avecxy_repartitions rp WHERE rp.organisation_id = ? AND rp.supprime_le IS NULL
            UNION ALL
            SELECT t.date_transfert, 'TRANSFERT_SORTIE', 0, t.montant, COALESCE(t.motif,''), NULL, t.de_agence_id
              FROM avecxy_transferts_fonds t WHERE t.organisation_id = ? AND t.supprime_le IS NULL
            UNION ALL
            SELECT t.date_transfert, 'TRANSFERT_ENTREE', t.montant, 0, COALESCE(t.motif,''), NULL, t.vers_agence_id
              FROM avecxy_transferts_fonds t WHERE t.organisation_id = ? AND t.supprime_le IS NULL
            UNION ALL
            SELECT ec.date_ecriture, 'ECRITURE', l.debit, l.credit, ec.libelle, NULL, ec.agence_id
              FROM avecxy_ecriture_lignes l JOIN avecxy_ecritures ec ON ec.id = l.ecriture_id JOIN avecxy_compta_comptes cc ON cc.id = l.compte_id
              WHERE ec.organisation_id = ? AND ec.supprime_le IS NULL AND cc.cle = 'caisse'";
        return [$sql, array_fill(0, 17, $org)];
    }

    /**
     * Filtre d'agence sur le flux : null = toute l'organisation, 0 = siege (lignes sans agence), N = l'agence N.
     * @return array{0:string,1:array}
     */
    private static function filtreAgence(?int $agence): array
    {
        if ($agence === null) {
            return ['', []];
        }
        return $agence === 0 ? [' AND f.agence IS NULL', []] : [' AND f.agence = ?', [$agence]];
    }

    /** Argent en main : toutes les entrees moins toutes les sorties, jusqu'a la date donnee (incluse). */
    public function solde(int $org, ?string $jusquAu = null, ?int $agence = null): mixed
    {
        [$sql, $p] = $this->requeteFlux($org);
        $ou = ' WHERE 1 = 1';
        if ($jusquAu !== null) {
            $ou .= ' AND f.jour <= ?';
            $p[] = $jusquAu;
        }
        [$filtre, $pa] = self::filtreAgence($agence);
        return $this->bd->valeur("SELECT COALESCE(SUM(f.entree),0) - COALESCE(SUM(f.sortie),0) FROM ($sql) f$ou$filtre", array_merge($p, $pa));
    }

    /** Totaux par jour et par type entre deux dates. */
    public function journal(int $org, string $du, string $au, ?int $agence = null): array
    {
        [$sql, $p] = $this->requeteFlux($org);
        array_push($p, $du, $au);
        [$filtre, $pa] = self::filtreAgence($agence);
        return $this->bd->tous(
            "SELECT f.jour, f.type, COUNT(*) AS nombre, COALESCE(SUM(f.entree),0) AS entree, COALESCE(SUM(f.sortie),0) AS sortie
             FROM ($sql) f WHERE f.jour >= ? AND f.jour <= ?$filtre GROUP BY f.jour, f.type ORDER BY f.jour, f.type",
            array_merge($p, $pa)
        );
    }

    /** Argent en main de chaque agence (et du siege), aujourd'hui. */
    public function soldesParAgence(int $org): array
    {
        [$sql, $p] = $this->requeteFlux($org);
        return $this->bd->tous(
            "SELECT f.agence, COALESCE(SUM(f.entree),0) - COALESCE(SUM(f.sortie),0) AS solde FROM ($sql) f GROUP BY f.agence",
            $p
        );
    }

    // --- transferts de fonds entre agences ---

    public function creerTransfert(array $d): int
    {
        return $this->bd->inserer('avecxy_transferts_fonds', $d);
    }

    public function transferts(int $org, string $du, string $au): array
    {
        return $this->bd->tous(
            'SELECT t.*, a1.nom AS de_nom, a2.nom AS vers_nom, u.nom AS auteur
             FROM avecxy_transferts_fonds t LEFT JOIN avecxy_agences a1 ON a1.id = t.de_agence_id LEFT JOIN avecxy_agences a2 ON a2.id = t.vers_agence_id
             LEFT JOIN avecxy_utilisateurs u ON u.id = t.cree_par
             WHERE t.organisation_id = ? AND t.supprime_le IS NULL AND t.date_transfert >= ? AND t.date_transfert <= ? ORDER BY t.date_transfert DESC, t.id DESC',
            [$org, $du, $au]
        );
    }
}

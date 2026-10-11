<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Produits de credit, demandes, credits, remboursements et penalites. */
final class DepotCredits
{
    public const TABLES = [
        'credits' => 'avecxy_credits', 'remboursements' => 'avecxy_credit_remboursements',
        'penalites' => 'avecxy_credit_penalites', 'produits-credit' => 'avecxy_credit_produits',
    ];

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    // ---------------- produits ----------------

    public function produits(int $org, bool $actifsSeulement = false): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_credit_produits WHERE organisation_id = ? AND supprime_le IS NULL' . ($actifsSeulement ? ' AND actif = 1' : '') . ' ORDER BY nom', [$org]);
    }

    public function produit(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_credit_produits WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
    }

    public function enregistrerProduit(int $org, int $id, array $d): int
    {
        if ($id > 0) {
            $this->bd->modifier('avecxy_credit_produits', $d + ['modifie_le' => $this->bd->maintenant()], ['organisation_id' => $org, 'id' => $id]);
            return $id;
        }
        return $this->bd->inserer('avecxy_credit_produits', $d + ['organisation_id' => $org, 'cree_le' => $this->bd->maintenant(), 'modifie_le' => $this->bd->maintenant()]);
    }

    // ---------------- clients ----------------

    public function clientParCode(int $org, string $code, ?int $recolteur): ?array
    {
        $sql = 'SELECT c.*, a.code AS recolteur_code, a.nom AS recolteur_nom FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id
                WHERE c.organisation_id = ? AND c.code = ? AND c.supprime_le IS NULL';
        $p = [$org, $code];
        $sql .= Portee::sql('c.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND c.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    /** Ce que l'on sait du client au moment du controle. Montants rendus tels que la base les donne. */
    public function faits(int $org, int $clientId): array
    {
        return $this->bd->un(
            "SELECT
                (SELECT COALESCE(SUM(k.epargne),0) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.statut = 'ACTIF' AND k.supprime_le IS NULL) AS epargne,
                (SELECT COUNT(*) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.statut = 'FERME' AND k.supprime_le IS NULL) AS carnets_fermes,
                (SELECT COUNT(*) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.statut = 'ACTIF' AND k.supprime_le IS NULL) AS carnets_ouverts,
                (SELECT COUNT(*) FROM avecxy_credits cr WHERE cr.client_id = c.id AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.supprime_le IS NULL) AS credits_en_cours,
                (SELECT COUNT(*) FROM avecxy_credits cr WHERE cr.client_id = c.id AND cr.supprime_le IS NULL AND (cr.statut = 'EN_RETARD' OR cr.total_penalites > 0)) AS retards,
                (SELECT COALESCE(SUM(cr.restant),0) FROM avecxy_credits cr WHERE cr.organisation_id = c.organisation_id AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.supprime_le IS NULL) AS encours,
                (SELECT COALESCE(SUM(cr.garantie),0) FROM avecxy_credits cr WHERE cr.client_id = c.id AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.supprime_le IS NULL) AS garanties,
                c.cree_le
             FROM avecxy_clients c WHERE c.organisation_id = ? AND c.id = ?",
            [$org, $clientId]
        ) ?? [];
    }

    // ---------------- demandes (eligibilite) ----------------

    public function creerDemande(array $d): int
    {
        return $this->bd->inserer('avecxy_credit_eligibilites', $d);
    }

    public function demande(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_credit_eligibilites WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL' . $this->bd->verrou(), [$org, $id]);
    }

    public function modifierDemande(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_credit_eligibilites', $d, ['id' => $id]);
    }

    /**
     * Liste des demandes. $etat : EN_ATTENTE, PARTIEL (accorde moins que demande), TOTAL, ou '' pour toutes.
     */
    public function demandes(int $org, ?int $recolteur, string $etat = '', string $q = ''): array
    {
        $sql = 'SELECT e.*, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, a.nom AS recolteur_nom, p.nom AS produit_nom, u.nom AS auteur_nom
                FROM avecxy_credit_eligibilites e JOIN avecxy_clients c ON c.id = e.client_id JOIN avecxy_agents a ON a.id = e.agent_id
                JOIN avecxy_credit_produits p ON p.id = e.credit_produit_id LEFT JOIN avecxy_utilisateurs u ON u.id = e.propose_par
                WHERE e.organisation_id = ? AND e.supprime_le IS NULL';
        $p = [$org];
        $sql .= Portee::sql('e.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND e.agent_id = ?';
            $p[] = $recolteur;
        }
        $sql .= match ($etat) {
            'EN_ATTENTE' => " AND e.statut = 'PROPOSE'",
            'PARTIEL' => " AND e.statut = 'VALIDE' AND e.plafond_accorde < e.montant_demande",
            'TOTAL' => " AND e.statut = 'VALIDE' AND e.plafond_accorde >= e.montant_demande",
            default => '',
        };
        if ($q !== '') {
            $sql .= ' AND (c.nom LIKE ? OR c.code LIKE ? OR a.nom LIKE ?)';
            array_push($p, '%' . $q . '%', $q . '%', '%' . $q . '%');
        }
        return $this->bd->tous($sql . ' ORDER BY e.id DESC LIMIT 300', $p);
    }

    public function demandeDetail(int $org, int $id, ?int $recolteur): ?array
    {
        $sql = 'SELECT e.*, c.code AS client_code, c.nom AS client_nom, c.telephone AS client_telephone, a.code AS recolteur_code, a.nom AS recolteur_nom,
                    p.nom AS produit_nom, u.nom AS auteur_nom, d.nom AS decideur_nom
                FROM avecxy_credit_eligibilites e JOIN avecxy_clients c ON c.id = e.client_id JOIN avecxy_agents a ON a.id = e.agent_id
                JOIN avecxy_credit_produits p ON p.id = e.credit_produit_id LEFT JOIN avecxy_utilisateurs u ON u.id = e.propose_par
                LEFT JOIN avecxy_utilisateurs d ON d.id = e.decide_par
                WHERE e.organisation_id = ? AND e.id = ? AND e.supprime_le IS NULL';
        $p = [$org, $id];
        $sql .= Portee::sql('e.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND e.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    /** Une demande attend-elle deja une reponse pour ce client ? */
    public function demandeEnAttente(int $org, int $clientId): bool
    {
        return (bool) $this->bd->valeur(
            "SELECT COUNT(*) FROM avecxy_credit_eligibilites WHERE organisation_id = ? AND client_id = ? AND statut = 'PROPOSE' AND supprime_le IS NULL",
            [$org, $clientId]
        );
    }

    // ---------------- credits ----------------

    private const SELECTION = 'SELECT cr.*, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, a.nom AS recolteur_nom, p.nom AS produit_nom,
            p.jours_tolerance, p.base_penalite, p.frequence_penalite
        FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id JOIN avecxy_agents a ON a.id = cr.agent_id
        JOIN avecxy_credit_produits p ON p.id = cr.credit_produit_id';

    public function credit(int $org, int $id, ?int $recolteur = null, bool $verrou = false): ?array
    {
        $sql = self::SELECTION . ' WHERE cr.organisation_id = ? AND cr.id = ? AND cr.supprime_le IS NULL';
        $p = [$org, $id];
        $sql .= Portee::sql('cr.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND cr.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql . ($verrou ? $this->bd->verrou() : ''), $p);
    }

    public function credits(int $org, ?int $recolteur, string $statut = '', string $q = '', int $clientId = 0): array
    {
        $sql = self::SELECTION . ' WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL';
        $p = [$org];
        $sql .= Portee::sql('cr.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND cr.agent_id = ?';
            $p[] = $recolteur;
        }
        if ($statut === 'OUVERTS') {
            $sql .= " AND cr.statut IN ('EN_COURS','EN_RETARD')";
        } elseif ($statut !== '') {
            $sql .= ' AND cr.statut = ?';
            $p[] = $statut;
        }
        if ($q !== '') {
            $sql .= ' AND (c.nom LIKE ? OR c.code LIKE ?)';
            array_push($p, '%' . $q . '%', $q . '%');
        }
        if ($clientId > 0) {
            $sql .= ' AND cr.client_id = ?';
            $p[] = $clientId;
        }
        return $this->bd->tous($sql . ' ORDER BY cr.id DESC LIMIT 300', $p);
    }

    /** Credits dont le remboursement a commence (au moins un paiement saisi), avec le nombre de paiements et le dernier. */
    public function enRemboursement(int $org, ?int $recolteur, string $q = ''): array
    {
        $sql = 'SELECT cr.id, cr.numero, cr.statut, cr.client_id, cr.total_du, cr.total_rembourse, cr.restant, cr.date_echeance, c.code AS client_code, c.nom AS client_nom,
                    COUNT(r.id) AS paiements, MAX(r.date_paiement) AS dernier_paiement, COALESCE(SUM(r.montant),0) AS verse
                FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id
                JOIN avecxy_credit_remboursements r ON r.credit_id = cr.id AND r.supprime_le IS NULL
                WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL';
        $p = [$org];
        $sql .= Portee::sql('cr.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND cr.agent_id = ?';
            $p[] = $recolteur;
        }
        if ($q !== '') {
            $sql .= ' AND (c.nom LIKE ? OR c.code LIKE ?)';
            array_push($p, '%' . $q . '%', $q . '%');
        }
        return $this->bd->tous($sql . ' GROUP BY cr.id, cr.numero, cr.statut, cr.client_id, cr.total_du, cr.total_rembourse, cr.restant, cr.date_echeance, c.code, c.nom
            ORDER BY CASE WHEN cr.restant > 0 THEN 0 ELSE 1 END, MAX(r.date_paiement) DESC, cr.id DESC LIMIT 500', $p);
    }

    public function prochainNumero(int $org): int
    {
        return 1 + (int) $this->bd->valeur('SELECT COALESCE(MAX(numero),0) FROM avecxy_credits WHERE organisation_id = ?', [$org]);
    }

    public function creerCredit(array $d): int
    {
        return $this->bd->inserer('avecxy_credits', $d);
    }

    public function modifierCredit(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_credits', $d, ['id' => $id]);
    }

    /** Credits ouverts dont une penalite automatique peut etre due. */
    public function creditsASuivre(int $org): array
    {
        return $this->bd->tous(self::SELECTION . " WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut IN ('EN_COURS','EN_RETARD')
            AND (cr.date_echeance < ? OR EXISTS (SELECT 1 FROM avecxy_credit_echeances e WHERE e.credit_id = cr.id AND e.date_prevue < ?))", [$org, date('Y-m-d'), date('Y-m-d')]);
    }

    public function totaux(int $org, ?int $recolteur): array
    {
        $sql = "SELECT COUNT(*) AS nombre, COALESCE(SUM(restant),0) AS encours, COALESCE(SUM(CASE WHEN statut = 'EN_RETARD' THEN restant ELSE 0 END),0) AS en_retard,
                COALESCE(SUM(CASE WHEN statut = 'EN_RETARD' THEN 1 ELSE 0 END),0) AS nombre_retard
                FROM avecxy_credits WHERE organisation_id = ? AND supprime_le IS NULL AND statut IN ('EN_COURS','EN_RETARD')";
        $p = [$org];
        $sql .= Portee::sql('agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    // ---------------- remboursements et penalites ----------------

    public function remboursementsDuCredit(int $creditId, bool $valides = false): array
    {
        return $this->bd->tous(
            'SELECT r.*, u.nom AS auteur_nom FROM avecxy_credit_remboursements r LEFT JOIN avecxy_utilisateurs u ON u.id = r.cree_par
             WHERE r.credit_id = ? AND r.supprime_le IS NULL' . ($valides ? " AND r.statut = 'VALIDE'" : '') . ' ORDER BY r.date_paiement, r.id',
            [$creditId]
        );
    }

    public function remboursement(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_credit_remboursements WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
    }

    public function remboursements(int $org, ?int $recolteur, string $du, string $au, string $statut = '', string $q = ''): array
    {
        $sql = 'SELECT r.*, cr.numero AS credit_numero, cr.agent_id, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, a.nom AS recolteur_nom, u.nom AS auteur_nom
                FROM avecxy_credit_remboursements r JOIN avecxy_credits cr ON cr.id = r.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
                JOIN avecxy_agents a ON a.id = cr.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = r.cree_par
                WHERE r.organisation_id = ? AND r.supprime_le IS NULL AND cr.supprime_le IS NULL';
        $p = [$org];
        $sql .= Portee::sql('cr.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND cr.agent_id = ?';
            $p[] = $recolteur;
        }
        if ($q !== '') {
            $sql .= ' AND (c.nom LIKE ? OR c.code LIKE ?)';
            array_push($p, '%' . $q . '%', $q . '%');
        }
        if ($statut !== '') {
            $sql .= ' AND r.statut = ?';
            $p[] = $statut;
        } else {
            $sql .= ' AND r.date_paiement >= ? AND r.date_paiement <= ?';
            array_push($p, $du, $au);
        }
        return $this->bd->tous($sql . ' ORDER BY r.date_paiement DESC, r.id DESC LIMIT 500', $p);
    }

    public function creerRemboursement(array $d): int
    {
        return $this->bd->inserer('avecxy_credit_remboursements', $d);
    }

    public function modifierRemboursement(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_credit_remboursements', $d, ['id' => $id]);
    }

    public function enAttenteDuCredit(int $creditId): mixed
    {
        return $this->bd->valeur("SELECT COALESCE(SUM(montant),0) FROM avecxy_credit_remboursements WHERE credit_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL", [$creditId]);
    }

    public function penalitesDuCredit(int $creditId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_credit_penalites WHERE credit_id = ? AND supprime_le IS NULL ORDER BY date_penalite, id', [$creditId]);
    }

    public function creerPenalite(array $d): int
    {
        return $this->bd->inserer('avecxy_credit_penalites', $d);
    }

    public function compterPenalitesAuto(int $creditId): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_credit_penalites WHERE credit_id = ? AND automatique = 1', [$creditId]);
    }

    public function echeances(int $creditId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_credit_echeances WHERE credit_id = ? ORDER BY numero, id', [$creditId]);
    }

    public function creerEcheance(array $d): int
    {
        return $this->bd->inserer('avecxy_credit_echeances', $d);
    }

    public function deplacerEcheance(int $id, string $date): void
    {
        $this->bd->modifier('avecxy_credit_echeances', ['date_prevue' => $date], ['id' => $id]);
    }

    public function effacerBenefices(int $creditId): void
    {
        $this->bd->executer('DELETE FROM avecxy_benefices WHERE credit_id = ?', [$creditId]);
    }

    // ---------------- corbeille ----------------

    public function ligne(string $table, int $org, int $id): ?array
    {
        return $this->bd->un("SELECT * FROM $table WHERE organisation_id = ? AND id = ?", [$org, $id]);
    }

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
}

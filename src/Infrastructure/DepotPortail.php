<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Espace client : acces des epargnants et lecture de leurs comptes. */
final class DepotPortail
{
    private const COMPTE = "SELECT c.id, c.code, c.nom, c.statut, c.organisation_id, c.agent_id, x.groupe_id, x.code_secret, x.doit_changer_code, x.actif, x.langue,
            o.nom AS organisation_nom, o.modules_actifs, o.statut AS organisation_statut, o.couleur_principale, o.telephone AS organisation_telephone,
            d.symbole AS devise_symbole, d.decimales AS devise_decimales, a.nom AS recolteur_nom, a.telephone AS recolteur_telephone
        FROM avecxy_client_acces x JOIN avecxy_clients c ON c.id = x.client_id JOIN avecxy_organisations o ON o.id = c.organisation_id
        JOIN avecxy_devises d ON d.code = o.devise_code JOIN avecxy_agents a ON a.id = c.agent_id
        WHERE c.supprime_le IS NULL";

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function acces(int $clientId): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_client_acces WHERE client_id = ?', [$clientId]);
    }

    public function compteParCode(string $code): ?array
    {
        return $this->bd->un(self::COMPTE . ' AND c.code = ?', [$code]);
    }

    public function compte(int $clientId): ?array
    {
        return $this->bd->un(self::COMPTE . ' AND c.id = ?', [$clientId]);
    }

    /** Tous les comptes rattaches au meme epargnant. */
    public function comptesDuGroupe(?string $groupe, int $clientId): array
    {
        if ($groupe === null || $groupe === '') {
            return $this->bd->tous(self::COMPTE . ' AND c.id = ?', [$clientId]);
        }
        return $this->bd->tous(self::COMPTE . ' AND x.groupe_id = ? AND x.actif = 1 ORDER BY c.code', [$groupe]);
    }

    /** Clients actifs sans acces ouvert a l'espace client. */
    public function clientsSansAcces(int $org): array
    {
        return $this->bd->tous(
            "SELECT c.id, c.code, c.nom, c.telephone, a.nom AS recolteur_nom FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id
             LEFT JOIN avecxy_client_acces x ON x.client_id = c.id AND x.actif = 1
             WHERE c.organisation_id = ? AND c.supprime_le IS NULL AND c.statut = 'ACTIF' AND x.client_id IS NULL ORDER BY a.nom, c.nom",
            [$org]
        );
    }

    public function compterAcces(int $org): int
    {
        return (int) $this->bd->valeur(
            'SELECT COUNT(*) FROM avecxy_client_acces x JOIN avecxy_clients c ON c.id = x.client_id WHERE c.organisation_id = ? AND c.supprime_le IS NULL AND x.actif = 1', [$org]
        );
    }

    public function creerAcces(array $d): void
    {
        $this->bd->inserer('avecxy_client_acces', $d);
    }

    public function modifierAcces(int $clientId, array $d): void
    {
        $this->bd->modifier('avecxy_client_acces', $d, ['client_id' => $clientId]);
    }

    public function fusionnerGroupes(string $ancien, string $nouveau): void
    {
        $this->bd->modifier('avecxy_client_acces', ['groupe_id' => $nouveau], ['groupe_id' => $ancien]);
    }

    /** Le forfait en cours de l'organisation comprend-il l'espace client ? */
    public function forfaitAvecModule(int $org): bool
    {
        $modules = $this->bd->valeur(
            'SELECT p.modules FROM avecxy_abonnements a JOIN avecxy_plans p ON p.id = a.plan_id WHERE a.organisation_id = ? ORDER BY a.fin DESC, a.id DESC LIMIT 1',
            [$org]
        );
        $m = json_decode((string) $modules, true);
        return is_array($m) && !empty($m['espace_client']);
    }

    // ---------------- ce que voit l'epargnant ----------------

    /** Modules du forfait en cours de l'organisation. */
    public function modulesDuForfait(int $org): array
    {
        $modules = $this->bd->valeur(
            'SELECT p.modules FROM avecxy_abonnements a JOIN avecxy_plans p ON p.id = a.plan_id WHERE a.organisation_id = ? ORDER BY a.fin DESC, a.id DESC LIMIT 1',
            [$org]
        );
        return json_decode((string) $modules, true) ?: [];
    }

    public function carnets(int $clientId): array
    {
        return $this->bd->tous(
            "SELECT k.*, ty.nom AS type_nom,
                (SELECT COALESCE(SUM(o.montant_total),0) FROM avecxy_cotisations o WHERE o.carnet_id = k.id AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL) AS en_attente
             FROM avecxy_carnets k JOIN avecxy_carnet_types ty ON ty.id = k.carnet_type_id
             WHERE k.client_id = ? AND k.supprime_le IS NULL ORDER BY k.statut, k.id DESC",
            [$clientId]
        );
    }

    public function carnet(int $clientId, int $carnetId): ?array
    {
        return $this->bd->un(
            "SELECT k.*, ty.nom AS type_nom,
                (SELECT COALESCE(SUM(o.montant_total),0) FROM avecxy_cotisations o WHERE o.carnet_id = k.id AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL) AS en_attente
             FROM avecxy_carnets k JOIN avecxy_carnet_types ty ON ty.id = k.carnet_type_id
             WHERE k.client_id = ? AND k.id = ? AND k.supprime_le IS NULL",
            [$clientId, $carnetId]
        );
    }

    /** Argent du client qui attend la validation de l'organisation : cotisations et paiements de credit. */
    public function enAttente(int $clientId): array
    {
        return $this->bd->un(
            "SELECT (SELECT COALESCE(SUM(montant_total),0) FROM avecxy_cotisations WHERE client_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL) AS cotisations,
                    (SELECT COUNT(*) FROM avecxy_cotisations WHERE client_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL) AS nombre,
                    (SELECT COALESCE(SUM(r.montant),0) FROM avecxy_credit_remboursements r JOIN avecxy_credits cr ON cr.id = r.credit_id
                       WHERE cr.client_id = ? AND r.statut = 'EN_ATTENTE' AND r.supprime_le IS NULL AND cr.supprime_le IS NULL) AS paiements",
            [$clientId, $clientId, $clientId]
        );
    }

    /**
     * Toutes les operations du client (ou d'un seul carnet), les plus recentes d'abord :
     * ventes de carnet, cotisations (validees ou en attente), retraits, frais, interets recus, paiements de credit.
     */
    public function operations(int $clientId, int $carnetId = 0, int $limite = 2000): array
    {
        $k = $carnetId > 0 ? ' AND k.id = ?' : '';
        $sql = "SELECT k.date_ouverture AS jour, 'CARNET' AS type, k.prix AS montant, NULL AS parts, k.validation AS statut, k.numero AS reference, k.id AS carnet_id, k.id AS ordre, 0 AS epargne
                  FROM avecxy_carnets k WHERE k.client_id = ? AND k.supprime_le IS NULL AND k.reprise_id IS NULL$k
                UNION ALL
                SELECT o.date_cotisation, 'COTISATION', o.montant_total, o.nombre_parts, o.statut, k.numero, k.id, o.id, o.montant_epargne
                  FROM avecxy_cotisations o JOIN avecxy_carnets k ON k.id = o.carnet_id
                  WHERE o.client_id = ? AND o.supprime_le IS NULL AND o.statut IN ('VALIDE','EN_ATTENTE') AND k.supprime_le IS NULL$k
                UNION ALL
                SELECT r.date_retrait, 'RETRAIT', r.montant_retire, NULL, r.statut, k.numero, k.id, r.id, r.montant_retire
                  FROM avecxy_retraits r JOIN avecxy_carnets k ON k.id = r.carnet_id
                  WHERE r.client_id = ? AND r.supprime_le IS NULL AND r.statut = 'VALIDE' AND k.supprime_le IS NULL$k
                UNION ALL
                SELECT f.date_frais, 'FRAIS', f.montant, NULL, 'VALIDE', k.numero, k.id, f.id, f.montant
                  FROM avecxy_frais_tenue f JOIN avecxy_carnets k ON k.id = f.carnet_id
                  WHERE f.client_id = ? AND f.supprime_le IS NULL AND k.supprime_le IS NULL$k
                UNION ALL
                SELECT i.date_interet, 'INTERET', i.montant, NULL, 'VALIDE', k.numero, k.id, i.id, i.montant
                  FROM avecxy_interets_epargne i JOIN avecxy_carnets k ON k.id = i.carnet_id
                  WHERE i.client_id = ? AND i.supprime_le IS NULL AND k.supprime_le IS NULL$k";
        $p = $carnetId > 0 ? [$clientId, $carnetId, $clientId, $carnetId, $clientId, $carnetId, $clientId, $carnetId, $clientId, $carnetId] : [$clientId, $clientId, $clientId, $clientId, $clientId];
        if ($carnetId === 0) {
            $sql .= "
                UNION ALL
                SELECT rb.date_paiement, 'REMBOURSEMENT', rb.montant, NULL, rb.statut, cr.numero, 0, rb.id, 0
                  FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id
                  WHERE cr.client_id = ? AND rb.supprime_le IS NULL AND rb.statut IN ('VALIDE','EN_ATTENTE') AND cr.supprime_le IS NULL
                UNION ALL
                SELECT cr.date_credit, 'CREDIT', cr.montant, NULL, 'VALIDE', cr.numero, 0, cr.id, 0
                  FROM avecxy_credits cr WHERE cr.client_id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE' AND cr.reprise_id IS NULL";
            array_push($p, $clientId, $clientId);
        }
        return $this->bd->tous("SELECT * FROM ($sql) h ORDER BY h.jour DESC, h.ordre DESC LIMIT " . (int) $limite, $p);
    }

    public function credits(int $clientId): array
    {
        return $this->bd->tous("SELECT cr.*, p.nom AS produit_nom FROM avecxy_credits cr JOIN avecxy_credit_produits p ON p.id = cr.credit_produit_id
            WHERE cr.client_id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE' ORDER BY cr.id DESC LIMIT 50", [$clientId]);
    }

    public function credit(int $clientId, int $id): ?array
    {
        return $this->bd->un("SELECT cr.*, p.nom AS produit_nom, p.jours_tolerance FROM avecxy_credits cr JOIN avecxy_credit_produits p ON p.id = cr.credit_produit_id
            WHERE cr.client_id = ? AND cr.id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'", [$clientId, $id]);
    }

    /** Paiements d'un credit, valides ou en attente. */
    public function paiementsDuCredit(int $creditId): array
    {
        return $this->bd->tous("SELECT date_paiement, montant, statut FROM avecxy_credit_remboursements
            WHERE credit_id = ? AND supprime_le IS NULL AND statut IN ('VALIDE','EN_ATTENTE') ORDER BY date_paiement DESC, id DESC", [$creditId]);
    }

    public function penalitesDuCredit(int $creditId): array
    {
        return $this->bd->tous('SELECT date_penalite, montant FROM avecxy_credit_penalites WHERE credit_id = ? AND supprime_le IS NULL ORDER BY date_penalite DESC, id DESC', [$creditId]);
    }

    public function echeances(int $creditId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_credit_echeances WHERE credit_id = ? ORDER BY numero, id', [$creditId]);
    }

    /** Demandes de credit du client, refusees comprises (avec le motif). */
    public function demandes(int $clientId): array
    {
        return $this->bd->tous(
            'SELECT e.id, e.statut, e.montant_demande, e.plafond_accorde, e.origine, e.cree_le, e.decide_le, e.motif_suppression, e.credit_id, p.nom AS produit_nom
             FROM avecxy_credit_eligibilites e JOIN avecxy_credit_produits p ON p.id = e.credit_produit_id
             WHERE e.client_id = ? ORDER BY e.id DESC LIMIT 30',
            [$clientId]
        );
    }

    public function produitsDeCredit(int $org): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_credit_produits WHERE organisation_id = ? AND supprime_le IS NULL AND actif = 1 ORDER BY nom', [$org]);
    }
}

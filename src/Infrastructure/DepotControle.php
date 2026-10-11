<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Ce qui attend le controle (ventes de carnets, cotisations, paiements de credit) et l'historique des controles. */
final class DepotControle
{
    public const TYPES = ['carnets', 'cotisations', 'paiements'];

    /** Pour chaque type : FROM + WHERE des lignes en attente (alias x = la ligne, cr/k selon le cas). */
    private const SOURCES = [
        'carnets' => ["avecxy_carnets x", "x.validation = 'EN_ATTENTE' AND x.statut = 'ACTIF' AND x.supprime_le IS NULL", 'x.agent_id', 'x.prix'],
        'cotisations' => ["avecxy_cotisations x", "x.statut = 'EN_ATTENTE' AND x.supprime_le IS NULL", 'x.agent_id', 'x.montant_total'],
        'paiements' => ["avecxy_credit_remboursements x JOIN avecxy_credits cr ON cr.id = x.credit_id", "x.statut = 'EN_ATTENTE' AND x.supprime_le IS NULL AND cr.supprime_le IS NULL", 'cr.agent_id', 'x.montant'],
    ];

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    /** Par recolteur : nombre et montant de ce qui attend, pour un type. */
    public function resume(int $org, string $type, ?int $agent = null): array
    {
        [$de, $ou, $colAgent, $colMontant] = self::SOURCES[$type];
        $p = [$org];
        $filtre = Portee::sql($colAgent, $p);
        if ($agent !== null) {
            $filtre .= " AND $colAgent = ?";
            $p[] = $agent;
        }
        return $this->bd->tous(
            "SELECT a.id, a.code, a.nom, COUNT(*) AS nombre, COALESCE(SUM($colMontant),0) AS montant
             FROM $de JOIN avecxy_agents a ON a.id = $colAgent WHERE x.organisation_id = ? AND $ou$filtre GROUP BY a.id, a.code, a.nom ORDER BY a.nom",
            $p
        );
    }

    /** Montant total en attente d'un type (toute l'organisation ou un recolteur). */
    public function total(int $org, string $type, ?int $agent): mixed
    {
        [$de, $ou, $colAgent, $colMontant] = self::SOURCES[$type];
        $p = [$org];
        $ou .= Portee::sql($colAgent, $p);
        if ($agent !== null) {
            $ou .= " AND $colAgent = ?";
            $p[] = $agent;
        }
        return $this->bd->valeur("SELECT COALESCE(SUM($colMontant),0) FROM $de WHERE x.organisation_id = ? AND $ou", $p);
    }

    /** Lignes en attente d'un recolteur : id, jour, client, reference, detail, montant, auteur, bloque. */
    public function lignes(int $org, string $type, int $agent): array
    {
        $sql = match ($type) {
            'carnets' => "SELECT x.id, x.date_ouverture AS jour, c.code AS client_code, c.nom AS client_nom, x.numero AS reference, ty.nom AS detail, x.prix AS montant, u.nom AS auteur, 0 AS bloque, x.client_id, x.id AS carnet_id
                FROM avecxy_carnets x JOIN avecxy_clients c ON c.id = x.client_id JOIN avecxy_carnet_types ty ON ty.id = x.carnet_type_id LEFT JOIN avecxy_utilisateurs u ON u.id = x.cree_par
                WHERE x.organisation_id = ? AND x.agent_id = ? AND x.validation = 'EN_ATTENTE' AND x.statut = 'ACTIF' AND x.supprime_le IS NULL ORDER BY x.date_ouverture, x.id",
            'cotisations' => "SELECT x.id, x.date_cotisation AS jour, c.code AS client_code, c.nom AS client_nom, k.numero AS reference, x.nombre_parts AS detail, x.montant_total AS montant, u.nom AS auteur,
                    CASE WHEN k.validation = 'VALIDE' THEN 0 ELSE 1 END AS bloque, x.client_id, x.carnet_id
                FROM avecxy_cotisations x JOIN avecxy_carnets k ON k.id = x.carnet_id JOIN avecxy_clients c ON c.id = x.client_id LEFT JOIN avecxy_utilisateurs u ON u.id = x.cree_par
                WHERE x.organisation_id = ? AND x.agent_id = ? AND x.statut = 'EN_ATTENTE' AND x.supprime_le IS NULL ORDER BY x.date_cotisation, x.id",
            default => "SELECT x.id, x.date_paiement AS jour, c.code AS client_code, c.nom AS client_nom, cr.numero AS reference, NULL AS detail, x.montant, u.nom AS auteur, 0 AS bloque, cr.client_id, 0 AS carnet_id
                FROM avecxy_credit_remboursements x JOIN avecxy_credits cr ON cr.id = x.credit_id JOIN avecxy_clients c ON c.id = cr.client_id LEFT JOIN avecxy_utilisateurs u ON u.id = x.cree_par
                WHERE x.organisation_id = ? AND cr.agent_id = ? AND x.statut = 'EN_ATTENTE' AND x.supprime_le IS NULL AND cr.supprime_le IS NULL ORDER BY x.date_paiement, x.id",
        };
        return $this->bd->tous($sql, [$org, $agent]);
    }

    public function creer(array $d): int
    {
        return $this->bd->inserer('avecxy_controles', $d);
    }

    public function historique(int $org, string $du, string $au, int $agent = 0): array
    {
        $sql = 'SELECT k.*, a.code AS recolteur_code, a.nom AS recolteur_nom, u.nom AS auteur_nom FROM avecxy_controles k
                JOIN avecxy_agents a ON a.id = k.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = k.fait_par
                WHERE k.organisation_id = ? AND k.cree_le >= ? AND k.cree_le <= ?';
        $p = [$org, $du . ' 00:00:00', $au . ' 23:59:59'];
        if ($agent > 0) {
            $sql .= ' AND k.agent_id = ?';
            $p[] = $agent;
        }
        return $this->bd->tous($sql . ' ORDER BY k.id DESC LIMIT 2000', $p);
    }
}

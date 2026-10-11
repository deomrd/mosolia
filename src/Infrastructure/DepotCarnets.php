<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Carnets vendus aux clients (table avecxy_carnets). */
final class DepotCarnets
{
    private const SELECTION = 'SELECT k.*, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, a.nom AS recolteur_nom, t.nom AS type_nom
        FROM avecxy_carnets k
        JOIN avecxy_clients c ON c.id = k.client_id
        JOIN avecxy_agents a ON a.id = k.agent_id
        JOIN avecxy_carnet_types t ON t.id = k.carnet_type_id';

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function parId(int $org, int $id, ?int $recolteur = null, bool $avecCorbeille = false): ?array
    {
        $sql = self::SELECTION . ' WHERE k.organisation_id = ? AND k.id = ?';
        $p = [$org, $id];
        if (!$avecCorbeille) {
            $sql .= ' AND k.supprime_le IS NULL';
        }
        $sql .= Portee::sql('k.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND k.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    /** Reserve le carnet pour la transaction en cours et renvoie son etat le plus recent. */
    public function verrouiller(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_carnets WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL' . $this->bd->verrou(), [$org, $id]);
    }

    /** @return array{lignes:array,total:int} */
    public function liste(int $org, ?int $recolteur, string $q, string $statut, int $page, int $parPage = 50, bool $corbeille = false): array
    {
        $ou = 'k.organisation_id = ? AND k.supprime_le IS ' . ($corbeille ? 'NOT NULL' : 'NULL');
        $p = [$org];
        $ou .= Portee::sql('k.agent_id', $p);
        if ($recolteur !== null) {
            $ou .= ' AND k.agent_id = ?';
            $p[] = $recolteur;
        }
        if ($statut === 'ACTIF') {
            $ou .= " AND k.statut = 'ACTIF'";
        } elseif ($statut === 'INACTIF' || $statut === 'FERME') {
            $ou .= " AND k.statut <> 'ACTIF'";
        }
        if ($q !== '') {
            $ou .= ' AND (c.nom LIKE ? OR c.code LIKE ? OR k.numero = ?)';
            array_push($p, '%' . $q . '%', $q . '%', ctype_digit($q) ? (int) $q : 0);
        }
        $total = (int) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_carnets k JOIN avecxy_clients c ON c.id = k.client_id WHERE $ou", $p);
        $lignes = $this->bd->tous(self::SELECTION . " WHERE $ou ORDER BY k.id DESC LIMIT " . (int) $parPage . ' OFFSET ' . max(0, ($page - 1) * $parPage), $p);
        return ['lignes' => $lignes, 'total' => $total];
    }

    public function duClient(int $org, int $clientId): array
    {
        return $this->bd->tous(self::SELECTION . ' WHERE k.organisation_id = ? AND k.client_id = ? AND k.supprime_le IS NULL ORDER BY k.statut, k.id DESC', [$org, $clientId]);
    }

    /** Carnets actifs d'un recolteur, pour la collecte du jour. */
    public function actifsDuRecolteur(int $org, int $agentId): array
    {
        $enAttente = "(SELECT COALESCE(SUM(o.nombre_parts),0) FROM avecxy_cotisations o WHERE o.carnet_id = k.id AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL) AS parts_en_attente";
        return $this->bd->tous(
            str_replace('SELECT k.*,', 'SELECT k.*, ' . $enAttente . ',', self::SELECTION) . "
             WHERE k.organisation_id = ? AND k.agent_id = ? AND k.statut = 'ACTIF' AND k.supprime_le IS NULL AND c.supprime_le IS NULL
             ORDER BY c.nom, k.id",
            [$org, $agentId]
        );
    }

    /** Numero lisible suivant, par organisation. A appeler dans une transaction. */
    public function prochainNumero(int $org): int
    {
        $this->bd->un('SELECT id FROM avecxy_organisations WHERE id = ?' . $this->bd->verrou(), [$org]);
        return (int) $this->bd->valeur('SELECT COALESCE(MAX(numero),0) + 1 FROM avecxy_carnets WHERE organisation_id = ?', [$org]);
    }

    public function creer(array $d): int
    {
        return $this->bd->inserer('avecxy_carnets', $d);
    }

    public function modifier(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_carnets', $d, ['id' => $id]);
    }

    public function compterVendus(int $typeId): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_carnets WHERE carnet_type_id = ? AND supprime_le IS NULL', [$typeId]);
    }
}

<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Clients epargnants (table avecxy_clients). */
final class DepotClients
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    /**
     * @param ?int $recolteur null = tous ; sinon seulement ce portefeuille
     * @return array{lignes:array,total:int}
     */
    public function liste(int $org, ?int $recolteur, string $q, int $filtreRecolteur, int $page, int $parPage = 50, bool $corbeille = false): array
    {
        $ou = 'c.organisation_id = ? AND c.supprime_le IS ' . ($corbeille ? 'NOT NULL' : 'NULL');
        $p = [$org];
        $ou .= Portee::sql('c.agent_id', $p);
        if ($recolteur !== null) {
            $ou .= ' AND c.agent_id = ?';
            $p[] = $recolteur;
        } elseif ($filtreRecolteur > 0) {
            $ou .= ' AND c.agent_id = ?';
            $p[] = $filtreRecolteur;
        }
        if ($q !== '') {
            $ou .= ' AND (c.nom LIKE ? OR c.code LIKE ? OR c.telephone LIKE ?)';
            array_push($p, '%' . $q . '%', $q . '%', '%' . $q . '%');
        }
        $total = (int) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_clients c WHERE $ou", $p);
        $lignes = $this->bd->tous(
            "SELECT c.*, a.code AS recolteur_code, a.nom AS recolteur_nom,
                (SELECT COUNT(*) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.supprime_le IS NULL AND k.statut = 'ACTIF') AS carnets_actifs,
                (SELECT COALESCE(SUM(k.epargne),0) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.supprime_le IS NULL AND k.statut = 'ACTIF') AS epargne
             FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id
             WHERE $ou ORDER BY c.nom LIMIT " . (int) $parPage . ' OFFSET ' . max(0, ($page - 1) * $parPage),
            $p
        );
        return ['lignes' => $lignes, 'total' => $total];
    }

    public function parId(int $org, int $id, ?int $recolteur = null, bool $avecCorbeille = false): ?array
    {
        $sql = 'SELECT c.*, a.code AS recolteur_code, a.nom AS recolteur_nom FROM avecxy_clients c
                JOIN avecxy_agents a ON a.id = c.agent_id WHERE c.organisation_id = ? AND c.id = ?';
        $p = [$org, $id];
        if (!$avecCorbeille) {
            $sql .= ' AND c.supprime_le IS NULL';
        }
        $sql .= Portee::sql('c.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND c.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    /** Clients portant deja ce nom, selon la portee choisie par l'organisation. */
    public function homonymes(int $org, string $nom, string $portee, int $agentId, int $saufId = 0): array
    {
        if ($portee === 'AUCUN') {
            return [];
        }
        $sql = 'SELECT c.*, a.code AS recolteur_code FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id
                WHERE c.organisation_id = ? AND c.nom = ? AND c.id <> ? AND c.supprime_le IS NULL';
        $p = [$org, $nom, $saufId];
        if ($portee === 'RECOLTEUR') {
            $sql .= ' AND c.agent_id = ?';
            $p[] = $agentId;
        }
        return $this->bd->tous($sql . ' ORDER BY c.id', $p);
    }

    /** Noms deja pris qui commencent par ce nom (pour numeroter : KASONGO 2, KASONGO 3). */
    public function nomsCommencantPar(int $org, string $nom): array
    {
        return array_column($this->bd->tous(
            'SELECT nom FROM avecxy_clients WHERE organisation_id = ? AND nom LIKE ? AND supprime_le IS NULL', [$org, $nom . '%']
        ), 'nom');
    }

    /** Attribue le prochain code du recolteur (K7MP-1, K7MP-2...). A appeler dans une transaction. */
    public function prochainCode(int $org, int $agentId): string
    {
        $this->bd->executer('UPDATE avecxy_agents SET dernier_numero_client = dernier_numero_client + 1 WHERE organisation_id = ? AND id = ?', [$org, $agentId]);
        $a = $this->bd->un('SELECT code, dernier_numero_client FROM avecxy_agents WHERE organisation_id = ? AND id = ?', [$org, $agentId]);
        return $a['code'] . '-' . $a['dernier_numero_client'];
    }

    public function compter(int $org): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_clients WHERE organisation_id = ? AND supprime_le IS NULL', [$org]);
    }

    public function creer(array $d): int
    {
        return $this->bd->inserer('avecxy_clients', $d);
    }

    public function modifier(int $org, int $id, array $d): void
    {
        $this->bd->modifier('avecxy_clients', $d, ['organisation_id' => $org, 'id' => $id]);
    }

    /** Carnets et credits non supprimes du client (empechent sa suppression). */
    public function compterEngagements(int $id): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_carnets WHERE client_id = ? AND supprime_le IS NULL', [$id])
            + (int) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_credits WHERE client_id = ? AND supprime_le IS NULL AND statut <> 'ANNULE'", [$id]);
    }
}

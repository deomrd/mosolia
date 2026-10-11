<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Recolteurs (table avecxy_agents). */
final class DepotRecolteurs
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function liste(int $organisationId, bool $corbeille = false): array
    {
        $p = [$organisationId];
        $agence = '';
        if (Portee::$agence !== null) {
            // un responsable d'agence ne voit que les recolteurs de son agence
            $agence = ' AND a.agence_id = ?';
            $p[] = Portee::$agence;
        }
        return $this->bd->tous(
            'SELECT a.*, ag.nom AS agence_nom,
                (SELECT u.nom FROM avecxy_utilisateurs u WHERE u.agent_id = a.id AND u.supprime_le IS NULL ORDER BY u.id LIMIT 1) AS utilisateur_nom,
                (SELECT COUNT(*) FROM avecxy_clients c WHERE c.agent_id = a.id AND c.supprime_le IS NULL) AS nb_clients
             FROM avecxy_agents a LEFT JOIN avecxy_agences ag ON ag.id = a.agence_id
             WHERE a.organisation_id = ? AND a.supprime_le IS ' . ($corbeille ? 'NOT NULL' : 'NULL') . $agence . '
             ORDER BY a.nom',
            $p
        );
    }

    public function parId(int $organisationId, int $id, bool $avecCorbeille = false): ?array
    {
        $sql = 'SELECT * FROM avecxy_agents WHERE organisation_id = ? AND id = ?';
        return $this->bd->un($avecCorbeille ? $sql : $sql . ' AND supprime_le IS NULL', [$organisationId, $id]);
    }

    /** Le code est unique sur toute la plateforme, corbeille comprise. */
    public function codePris(string $code): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agents WHERE code = ?', [$code]);
    }

    public function compter(int $organisationId): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agents WHERE organisation_id = ? AND supprime_le IS NULL', [$organisationId]);
    }

    public function compterClients(int $id): int
    {
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_clients WHERE agent_id = ? AND supprime_le IS NULL', [$id]);
    }

    public function creer(array $donnees): int
    {
        return $this->bd->inserer('avecxy_agents', $donnees);
    }

    public function modifier(int $organisationId, int $id, array $donnees): void
    {
        $this->bd->modifier('avecxy_agents', $donnees, ['organisation_id' => $organisationId, 'id' => $id]);
    }

    /** Compte utilisateur rattache a ce recolteur, s'il y en a un. */
    public function utilisateurLie(int $id): ?array
    {
        return $this->bd->un('SELECT id, nom FROM avecxy_utilisateurs WHERE agent_id = ? AND supprime_le IS NULL ORDER BY id LIMIT 1', [$id]);
    }

    /** Comptes de l'organisation qu'on peut rattacher : libres, ou deja rattaches a ce recolteur. */
    public function utilisateursRattachables(int $organisationId, int $recolteurId): array
    {
        return $this->bd->tous(
            'SELECT u.id, u.nom, r.nom AS profil_nom FROM avecxy_utilisateurs u JOIN avecxy_roles r ON r.id = u.role_id
             WHERE u.organisation_id = ? AND u.supprime_le IS NULL AND (u.agent_id IS NULL OR u.agent_id = ?)
             ORDER BY r.est_agent DESC, u.nom',
            [$organisationId, $recolteurId]
        );
    }

    public function rattacher(int $organisationId, int $recolteurId, int $utilisateurId): void
    {
        $this->bd->executer('UPDATE avecxy_utilisateurs SET agent_id = NULL WHERE organisation_id = ? AND agent_id = ?', [$organisationId, $recolteurId]);
        if ($utilisateurId > 0) {
            $this->bd->executer('UPDATE avecxy_utilisateurs SET agent_id = ? WHERE organisation_id = ? AND id = ?', [$recolteurId, $organisationId, $utilisateurId]);
        }
    }
}

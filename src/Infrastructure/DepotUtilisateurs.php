<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Lecture et ecriture des utilisateurs (table avecxy_utilisateurs). */
final class DepotUtilisateurs
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function parIdentifiant(string $identifiant): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_utilisateurs WHERE identifiant = ? AND supprime_le IS NULL', [$identifiant]);
    }

    public function identifiantPris(string $identifiant, int $saufId = 0): bool
    {
        // meme un compte a la corbeille garde son identifiant, pour pouvoir etre restaure
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_utilisateurs WHERE identifiant = ? AND id <> ?', [$identifiant, $saufId]);
    }

    public function parId(int $organisationId, int $id, bool $avecCorbeille = false): ?array
    {
        $sql = 'SELECT * FROM avecxy_utilisateurs WHERE organisation_id = ? AND id = ?';
        if (!$avecCorbeille) {
            $sql .= ' AND supprime_le IS NULL';
        }
        return $this->bd->un($sql, [$organisationId, $id]);
    }

    public function liste(int $organisationId, bool $corbeille = false): array
    {
        return $this->bd->tous(
            'SELECT u.*, r.nom AS profil_nom, r.est_admin, a.code AS recolteur_code
             FROM avecxy_utilisateurs u
             JOIN avecxy_roles r ON r.id = u.role_id
             LEFT JOIN avecxy_agents a ON a.id = u.agent_id
             WHERE u.organisation_id = ? AND u.supprime_le IS ' . ($corbeille ? 'NOT NULL' : 'NULL') . '
             ORDER BY u.nom',
            [$organisationId]
        );
    }

    public function creer(array $donnees): int
    {
        return $this->bd->inserer('avecxy_utilisateurs', $donnees);
    }

    public function modifier(int $organisationId, int $id, array $donnees): void
    {
        $this->bd->modifier('avecxy_utilisateurs', $donnees, ['organisation_id' => $organisationId, 'id' => $id]);
    }

    /** Nombre d'administrateurs actifs, sans compter l'utilisateur indique. */
    public function compterAdminsActifs(int $organisationId, int $saufId = 0): int
    {
        return (int) $this->bd->valeur(
            'SELECT COUNT(*) FROM avecxy_utilisateurs u JOIN avecxy_roles r ON r.id = u.role_id
             WHERE u.organisation_id = ? AND r.est_admin = 1 AND u.statut = ? AND u.supprime_le IS NULL AND u.id <> ?',
            [$organisationId, 'ACTIF', $saufId]
        );
    }

    public function compterActifs(int $organisationId): int
    {
        return (int) $this->bd->valeur(
            'SELECT COUNT(*) FROM avecxy_utilisateurs WHERE organisation_id = ? AND supprime_le IS NULL',
            [$organisationId]
        );
    }

    /** @return array<string,string> code du droit => ACCORDE | REFUSE */
    public function reglesPersonnelles(int $utilisateurId): array
    {
        $lignes = $this->bd->tous(
            'SELECT p.code, up.effet FROM avecxy_utilisateur_permissions up
             JOIN avecxy_permissions p ON p.id = up.permission_id WHERE up.utilisateur_id = ?',
            [$utilisateurId]
        );
        return array_column($lignes, 'effet', 'code');
    }

    /** @param array<int,string> $regles id du droit => ACCORDE | REFUSE */
    public function definirRegles(int $utilisateurId, array $regles, int $par): void
    {
        $this->bd->executer('DELETE FROM avecxy_utilisateur_permissions WHERE utilisateur_id = ?', [$utilisateurId]);
        foreach ($regles as $permissionId => $effet) {
            $this->bd->inserer('avecxy_utilisateur_permissions', [
                'utilisateur_id' => $utilisateurId,
                'permission_id' => $permissionId,
                'effet' => $effet,
                'defini_par' => $par,
                'defini_le' => $this->bd->maintenant(),
            ]);
        }
    }

    // --- limitation des tentatives de connexion ---

    public function tentative(string $cle): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_connexion_tentatives WHERE cle = ?', [$cle]);
    }

    public function enregistrerTentative(string $cle, int $nombre, int $debut, int $bloqueJusqua): void
    {
        $this->bd->executer('DELETE FROM avecxy_connexion_tentatives WHERE cle = ?', [$cle]);
        $this->bd->inserer('avecxy_connexion_tentatives', [
            'cle' => $cle, 'nombre' => $nombre, 'debut_fenetre' => $debut, 'bloque_jusqua' => $bloqueJusqua,
        ]);
    }

    public function effacerTentatives(string $cle): void
    {
        $this->bd->executer('DELETE FROM avecxy_connexion_tentatives WHERE cle = ?', [$cle]);
    }
}

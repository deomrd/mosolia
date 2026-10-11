<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Profils (avecxy_roles) et leurs droits. */
final class DepotProfils
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function liste(int $organisationId): array
    {
        return $this->bd->tous(
            'SELECT r.*,
                (SELECT COUNT(*) FROM avecxy_utilisateurs u WHERE u.role_id = r.id AND u.supprime_le IS NULL) AS nb_utilisateurs,
                (SELECT COUNT(*) FROM avecxy_role_permissions rp WHERE rp.role_id = r.id) AS nb_droits
             FROM avecxy_roles r WHERE r.organisation_id = ? ORDER BY r.est_admin DESC, r.nom',
            [$organisationId]
        );
    }

    public function parId(int $organisationId, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_roles WHERE organisation_id = ? AND id = ?', [$organisationId, $id]);
    }

    public function codePris(int $organisationId, string $code, int $saufId = 0): bool
    {
        return (bool) $this->bd->valeur(
            'SELECT COUNT(*) FROM avecxy_roles WHERE organisation_id = ? AND code = ? AND id <> ?',
            [$organisationId, $code, $saufId]
        );
    }

    public function creer(array $donnees): int
    {
        return $this->bd->inserer('avecxy_roles', $donnees);
    }

    public function modifier(int $id, array $donnees): void
    {
        $this->bd->modifier('avecxy_roles', $donnees, ['id' => $id]);
    }

    public function supprimer(int $id): void
    {
        $this->bd->executer('DELETE FROM avecxy_role_permissions WHERE role_id = ?', [$id]);
        $this->bd->executer('DELETE FROM avecxy_roles WHERE id = ?', [$id]);
    }

    public function compterUtilisateurs(int $id): int
    {
        // les comptes a la corbeille comptent aussi : ils peuvent etre restaures
        return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_utilisateurs WHERE role_id = ?', [$id]);
    }

    /** @return string[] codes des droits du profil */
    public function droits(int $id): array
    {
        return array_column($this->bd->tous(
            'SELECT p.code FROM avecxy_role_permissions rp JOIN avecxy_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$id]
        ), 'code');
    }

    /** @param string[] $codes */
    public function definirDroits(int $id, array $codes): void
    {
        $ids = $this->idsDesDroits();
        $this->bd->executer('DELETE FROM avecxy_role_permissions WHERE role_id = ?', [$id]);
        foreach (array_unique($codes) as $code) {
            if (isset($ids[$code])) {
                $this->bd->inserer('avecxy_role_permissions', ['role_id' => $id, 'permission_id' => $ids[$code]]);
            }
        }
    }

    /** @return array<string,int> code => id */
    public function idsDesDroits(): array
    {
        return array_map('intval', array_column($this->bd->tous('SELECT id, code FROM avecxy_permissions'), 'id', 'code'));
    }
}

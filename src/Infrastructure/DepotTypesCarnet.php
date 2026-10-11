<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Types de carnet de chaque organisation (table avecxy_carnet_types). */
final class DepotTypesCarnet
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function liste(int $organisationId, bool $corbeille = false): array
    {
        return $this->bd->tous(
            'SELECT t.*, (SELECT COUNT(*) FROM avecxy_carnets c WHERE c.carnet_type_id = t.id AND c.supprime_le IS NULL) AS nb_carnets
             FROM avecxy_carnet_types t
             WHERE t.organisation_id = ? AND t.supprime_le IS ' . ($corbeille ? 'NOT NULL' : 'NULL') . '
             ORDER BY t.par_defaut DESC, t.nom',
            [$organisationId]
        );
    }

    public function parId(int $organisationId, int $id, bool $avecCorbeille = false): ?array
    {
        $sql = 'SELECT * FROM avecxy_carnet_types WHERE organisation_id = ? AND id = ?';
        return $this->bd->un($avecCorbeille ? $sql : $sql . ' AND supprime_le IS NULL', [$organisationId, $id]);
    }

    public function nomPris(int $organisationId, string $nom, int $saufId = 0): bool
    {
        return (bool) $this->bd->valeur(
            'SELECT COUNT(*) FROM avecxy_carnet_types WHERE organisation_id = ? AND nom = ? AND id <> ? AND supprime_le IS NULL',
            [$organisationId, $nom, $saufId]
        );
    }

    public function creer(array $donnees): int
    {
        return $this->bd->inserer('avecxy_carnet_types', $donnees);
    }

    public function modifier(int $organisationId, int $id, array $donnees): void
    {
        $this->bd->modifier('avecxy_carnet_types', $donnees, ['organisation_id' => $organisationId, 'id' => $id]);
    }

    /** Un seul type par defaut par organisation. */
    public function retirerParDefaut(int $organisationId, int $saufId): void
    {
        $this->bd->executer('UPDATE avecxy_carnet_types SET par_defaut = 0 WHERE organisation_id = ? AND id <> ?', [$organisationId, $saufId]);
    }
}

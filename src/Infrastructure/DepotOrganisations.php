<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Organisations, abonnements, forfaits et reglages de la plateforme. */
final class DepotOrganisations
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function parId(int $id): ?array
    {
        return $this->bd->un(
            'SELECT o.*, d.symbole AS devise_symbole, d.decimales AS devise_decimales
             FROM avecxy_organisations o JOIN avecxy_devises d ON d.code = o.devise_code WHERE o.id = ?',
            [$id]
        );
    }

    public function codePris(string $code): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_organisations WHERE code = ?', [$code]);
    }

    public function nomCourtPris(string $nomCourt, int $saufId = 0): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_organisations WHERE nom_court = ? AND id <> ?', [$nomCourt, $saufId]);
    }

    public function creer(array $donnees): int
    {
        return $this->bd->inserer('avecxy_organisations', $donnees);
    }

    public function modifier(int $id, array $donnees): void
    {
        $this->bd->modifier('avecxy_organisations', $donnees, ['id' => $id]);
    }

    public function devises(): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_devises WHERE actif = 1 ORDER BY code');
    }

    public function plan(string $code): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_plans WHERE code = ?', [$code]);
    }

    public function planParId(int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_plans WHERE id = ?', [$id]);
    }

    public function creerAbonnement(array $donnees): int
    {
        return $this->bd->inserer('avecxy_abonnements', $donnees);
    }

    /** L'abonnement le plus recent de l'organisation (celui qui fait foi). */
    public function dernierAbonnement(int $organisationId): ?array
    {
        return $this->bd->un(
            'SELECT a.*, p.nom AS plan_nom, p.max_utilisateurs, p.max_agents, p.max_clients, p.modules
             FROM avecxy_abonnements a JOIN avecxy_plans p ON p.id = a.plan_id
             WHERE a.organisation_id = ? ORDER BY a.fin DESC, a.id DESC LIMIT 1',
            [$organisationId]
        );
    }

    public function parametrePlateforme(string $cle, string $defaut): string
    {
        $v = $this->bd->valeur('SELECT valeur FROM avecxy_plateforme_parametres WHERE cle = ?', [$cle]);
        return $v === null ? $defaut : (string) $v;
    }
}

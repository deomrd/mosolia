<?php
namespace AvecXY\Application;

use AvecXY\Domaine\EtatAbonnement;

/** Qui est connecte, dans quelle organisation, avec quels droits. Recalcule a chaque page. */
final class Contexte
{
    /** @param string[] $droits */
    public function __construct(
        public readonly array $utilisateur,
        public readonly array $organisation,
        public readonly array $profil,
        public readonly array $droits,
        public readonly EtatAbonnement $abonnement,
        public readonly ?array $forfait,
    ) {
        // un utilisateur rattache a une agence ne voit que les clients, recolteurs et la caisse de cette agence
        \AvecXY\Infrastructure\Portee::$agence = $this->agenceRestreinte();
    }

    /** Agence a laquelle l'utilisateur est limite (null = toutes : siege ou organisation sans agence). */
    public function agenceRestreinte(): ?int
    {
        $agence = (int) ($this->utilisateur['agence_id'] ?? 0);
        return $agence > 0 ? $agence : null;
    }

    public function peut(string $droit): bool
    {
        return in_array($droit, $this->droits, true);
    }

    public function estAdmin(): bool
    {
        return (int) $this->profil['est_admin'] === 1;
    }

    /**
     * Portefeuille visible : null = tous les recolteurs ; sinon l'identifiant du seul
     * recolteur dont l'utilisateur voit les clients (0 = aucun, compte sans fiche recolteur).
     */
    public function recolteurRestreint(): ?int
    {
        if ($this->peut('clients.voir_tous')) {
            return null;
        }
        return (int) ($this->utilisateur['agent_id'] ?? 0);
    }

    /** Le forfait de l'organisation comprend-il ce module (credit, espace_client) ? */
    public function module(string $module): bool
    {
        $modules = json_decode((string) ($this->forfait['modules'] ?? ''), true);
        return is_array($modules) && !empty($modules[$module]);
    }

    public function decimales(): int
    {
        return (int) $this->organisation['devise_decimales'];
    }

    public function organisationId(): int
    {
        return (int) $this->organisation['id'];
    }

    public function utilisateurId(): int
    {
        return (int) $this->utilisateur['id'];
    }
}

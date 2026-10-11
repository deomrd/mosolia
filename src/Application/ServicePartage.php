<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotOperations;

/**
 * Partage d'un benefice entre le recolteur et l'organisation, selon la regle
 * en vigueur a la date de l'operation. Sans regle, tout revient a l'organisation.
 */
final class ServicePartage
{
    private array $cache = [];

    public function __construct(private readonly DepotOperations $operations)
    {
    }

    /** @return array{agent:int,organisation:int,regle_id:?int} montants en centimes */
    public function repartir(int $org, string $source, string $date, int $centimes): array
    {
        $cle = $org . '|' . $source . '|' . $date;
        if (!array_key_exists($cle, $this->cache)) {
            $this->cache[$cle] = $this->operations->reglePartage($org, $source, $date);
        }
        $regle = $this->cache[$cle];
        if ($regle === null) {
            return ['agent' => 0, 'organisation' => $centimes, 'regle_id' => null];
        }
        $agent = Montant::pourcentage($centimes, (string) $regle['pourcentage_agent']);
        return ['agent' => $agent, 'organisation' => $centimes - $agent, 'regle_id' => (int) $regle['id']];
    }

    public function oublierCache(): void
    {
        $this->cache = [];
    }
}

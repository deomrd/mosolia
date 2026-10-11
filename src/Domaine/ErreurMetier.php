<?php
namespace AvecXY\Domaine;

/**
 * Refus explique a l'utilisateur (regle metier non respectee).
 * Le message est une cle de traduction, avec ses valeurs.
 */
final class ErreurMetier extends \RuntimeException
{
    public function __construct(public readonly string $cle, public readonly array $valeurs = [])
    {
        parent::__construct($cle);
    }
}

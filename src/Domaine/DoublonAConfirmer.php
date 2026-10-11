<?php
namespace AvecXY\Domaine;

/**
 * Un client du meme nom existe deja et l'organisation a choisi de demander a chaque
 * fois quoi faire : la saisie est suspendue le temps que l'utilisateur tranche.
 */
final class DoublonAConfirmer extends \RuntimeException
{
    /** @param array[] $homonymes clients existants portant ce nom */
    public function __construct(public readonly array $homonymes)
    {
        parent::__construct('doublon');
    }
}

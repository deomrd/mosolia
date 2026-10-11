<?php
namespace AvecXY\Infrastructure;

/**
 * Agence a laquelle la personne connectee est limitee (null = toutes les agences : siege ou organisation
 * sans agence). Fixee une fois par page par le noyau ; chaque requete qui filtre deja par recolteur
 * y ajoute ce filtre, si bien qu'un responsable d'agence ne voit que les clients des recolteurs de son agence.
 */
final class Portee
{
    public static ?int $agence = null;

    /** Morceau de SQL a ajouter (et parametre) pour une colonne qui designe un recolteur. */
    public static function sql(string $colonneRecolteur, array &$p): string
    {
        if (self::$agence === null) {
            return '';
        }
        $p[] = self::$agence;
        return " AND $colonneRecolteur IN (SELECT id FROM avecxy_agents WHERE agence_id = ?)";
    }
}

<?php
namespace AvecXY\Domaine;

final class Dates
{
    /** Controle une date saisie (AAAA-MM-JJ) ; elle ne peut pas etre dans le futur. */
    public static function passee(string $valeur, string $cle = 'date.invalide'): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if ($d === false || $d->format('Y-m-d') !== $valeur) {
            throw new ErreurMetier($cle);
        }
        if ($valeur > date('Y-m-d')) {
            throw new ErreurMetier('date.future');
        }
        return $valeur;
    }

    /** Date valide ou valeur par defaut (pour les filtres de liste). */
    public static function ou(string $valeur, string $defaut): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        return $d !== false && $d->format('Y-m-d') === $valeur ? $valeur : $defaut;
    }

    public static function ajouterJours(string $date, int $jours): string
    {
        return (new \DateTimeImmutable($date))->modify(($jours >= 0 ? '+' : '') . $jours . ' days')->format('Y-m-d');
    }

    public static function joursEntre(string $du, string $au): int
    {
        return (int) (new \DateTimeImmutable($du))->diff(new \DateTimeImmutable($au))->format('%r%a');
    }
}

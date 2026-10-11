<?php
namespace AvecXY\Domaine;

/**
 * Identifiant d'un recolteur : 4 caracteres, genere automatiquement, unique sur
 * toute la plateforme. Il prefixe les codes de ses clients (K7MP-1, K7MP-2...).
 * Les caracteres qui se confondent a l'oral ou a l'ecrit (0/O, 1/I/L) sont exclus.
 */
final class CodeRecolteur
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const LONGUEUR = 4;

    public static function generer(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LONGUEUR; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }
}

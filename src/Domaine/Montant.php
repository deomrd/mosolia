<?php
namespace AvecXY\Domaine;

/**
 * Montants d'argent. Ils circulent sous forme de texte decimal ("1500.00"),
 * jamais en nombre flottant, pour qu'aucun centime ne se perde en calcul.
 */
final class Montant
{
    /**
     * Lit une saisie ("1 500", "1500,50") et renvoie "1500.00".
     * Refuse les negatifs, les lettres, et plus de decimales que la devise n'en a.
     */
    public static function lire(string $saisie, int $decimalesDevise, string $cleErreur = 'montant.invalide'): string
    {
        $v = str_replace([' ', "\u{00A0}", "\u{202F}", "'"], '', trim($saisie));
        $v = str_replace(',', '.', $v);
        if (!preg_match('/^\d{1,13}(\.\d+)?$/', $v)) {
            throw new ErreurMetier($cleErreur);
        }
        [$entier, $fraction] = array_pad(explode('.', $v, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        if (strlen($fraction) > min(2, $decimalesDevise)) {
            throw new ErreurMetier('montant.trop_de_decimales', ['n' => $decimalesDevise]);
        }
        return ltrim($entier, '0') === '' ? '0.' . str_pad($fraction, 2, '0') : ltrim($entier, '0') . '.' . str_pad($fraction, 2, '0');
    }

    /** Compare deux montants : -1, 0 ou 1. */
    public static function comparer(string $a, string $b): int
    {
        return self::enCentimes($a) <=> self::enCentimes($b);
    }

    public static function estNul(string $a): bool
    {
        return self::enCentimes($a) === 0;
    }

    /** "1500.00" => "1 500" (CDF) ou "1 500,00" (USD), sans le symbole. */
    public static function afficher(string|int|float|null $montant, int $decimalesDevise): string
    {
        $c = self::enCentimes($montant);
        $signe = $c < 0 ? '-' : '';
        $c = abs($c);
        $entier = intdiv($c, 100);
        $texte = $signe . number_format($entier, 0, '', ' ');
        return $decimalesDevise > 0 ? $texte . ',' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT) : $texte;
    }

    /** Valeur pour un champ de formulaire : "1500" ou "1500.50". */
    public static function pourChamp(string|int|float|null $montant): string
    {
        if ($montant === null || $montant === '') {
            return '';
        }
        $c = self::enCentimes($montant);
        return $c % 100 === 0 ? (string) intdiv($c, 100) : intdiv($c, 100) . '.' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "1500.50" (ou une valeur numerique rendue par la base) => 150050 centimes. */
    public static function enCentimes(string|int|float|null $montant): int
    {
        if ($montant === null || $montant === '') {
            return 0;
        }
        return (int) round((float) $montant * 100);
    }

    /** 150050 centimes => "1500.50" (forme enregistree en base). */
    public static function depuisCentimes(int $centimes): string
    {
        $signe = $centimes < 0 ? '-' : '';
        $c = abs($centimes);
        return $signe . intdiv($c, 100) . '.' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Pourcentage d'un montant, en centimes, arrondi au plus proche.
     * @param string|int|float $taux ex. "45", "2.5"
     */
    public static function pourcentage(int $centimes, string|int|float $taux): int
    {
        $tauxCentiemes = (int) round((float) $taux * 100);   // 2.5 % => 250
        return intdiv($centimes * $tauxCentiemes + 5000, 10000);
    }

    /** Arrondit des centimes a l'unite de la devise (CDF n'a pas de centimes). */
    public static function arrondirDevise(int $centimes, int $decimalesDevise): int
    {
        return $decimalesDevise > 0 ? $centimes : intdiv($centimes + 50, 100) * 100;
    }
}

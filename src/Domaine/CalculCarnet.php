<?php
namespace AvecXY\Domaine;

/**
 * Calcule l'etat d'un carnet a partir de ses operations validees.
 * Tout est recalcule depuis le debut a chaque changement : corriger ou supprimer
 * une cotisation ancienne donne toujours un resultat juste, sans ecart cumule.
 */
final class CalculCarnet
{
    /**
     * @param array $carnet regles copiees sur le carnet (mode, mise, nombre_parts, taille_tranche...)
     * @param array<int,array{id:int,nombre_parts:?int,montant_total:mixed}> $cotisations validees, de la plus ancienne a la plus recente
     * @return array{lignes:array<int,array>,parts_validees:int,parts_benefice:int,total_cotise:int,epargne_brute:int}
     *         montants en centimes ; lignes indexees par id de cotisation
     */
    public static function repartir(array $carnet, array $cotisations): array
    {
        $lignes = [];
        $parts = 0;
        $partsBenefice = 0;
        $total = 0;
        $epargne = 0;
        // carnet repris du papier : ce qui a ete verse avant l'application sert de point de depart
        $reprisParts = (int) ($carnet['reprise_parts'] ?? 0);
        $dejaPris = (int) ($carnet['benefice_deja_pris'] ?? 0) === 1;

        if ($carnet['mode'] === 'LIBRE') {
            $total = $epargne = Montant::enCentimes($carnet['reprise_montant'] ?? 0);
            foreach ($cotisations as $c) {
                $montant = Montant::enCentimes($c['montant_total']);
                $lignes[(int) $c['id']] = ['parts_benefice' => 0, 'parts_epargne' => 0, 'montant_total' => $montant, 'montant_benefice' => 0, 'montant_epargne' => $montant];
                $total += $montant;
                $epargne += $montant;
            }
            return ['lignes' => $lignes, 'parts_validees' => 0, 'parts_benefice' => 0, 'total_cotise' => $total, 'epargne_brute' => $epargne, 'reprise_benefice' => 0];
        }

        $mise = Montant::enCentimes($carnet['mise']);
        $benefices = array_flip(TypeCarnet::partsBenefice(
            (int) $carnet['nombre_parts'], (int) $carnet['taille_tranche'],
            (int) $carnet['parts_benefice_par_tranche'], (string) $carnet['prelevement_benefice']
        ));
        // benefice deja pris sur papier : celui de la tranche en cours a la reprise n'est plus a partager
        $taille = (int) $carnet['taille_tranche'];
        $limiteDejaPris = $dejaPris && $reprisParts > 0 ? ($taille >= 1 ? (int) ceil($reprisParts / $taille) * $taille : $reprisParts) : 0;
        $repriseBenefice = 0;
        if ($reprisParts > 0) {
            $b0 = 0;
            for ($numero = 1; $numero <= $reprisParts; $numero++) {
                if (isset($benefices[$numero])) {
                    $b0++;
                }
            }
            $parts = $reprisParts;
            $partsBenefice = $b0;
            $total = $reprisParts * $mise;
            $epargne = ($reprisParts - $b0) * $mise;
            $repriseBenefice = $dejaPris ? 0 : $b0 * $mise;
        }
        foreach ($cotisations as $c) {
            $n = (int) $c['nombre_parts'];
            $b = 0;
            $dejaPartages = 0;
            for ($numero = $parts + 1; $numero <= $parts + $n; $numero++) {
                if (isset($benefices[$numero])) {
                    $b++;
                    if ($numero <= $limiteDejaPris) {
                        $dejaPartages++;
                    }
                }
            }
            $lignes[(int) $c['id']] = [
                'parts_benefice' => $b, 'parts_epargne' => $n - $b, 'montant_total' => $n * $mise,
                'montant_benefice' => $b * $mise, 'montant_epargne' => ($n - $b) * $mise,
                'benefice_a_partager' => ($b - $dejaPartages) * $mise,
            ];
            $parts += $n;
            $partsBenefice += $b;
            $total += $n * $mise;
            $epargne += ($n - $b) * $mise;
        }
        return ['lignes' => $lignes, 'parts_validees' => $parts, 'parts_benefice' => $partsBenefice, 'total_cotise' => $total, 'epargne_brute' => $epargne, 'reprise_benefice' => $repriseBenefice];
    }

    /**
     * Epargne (en centimes) que represente la tranche numero $tranche (1, 2, 3...).
     */
    public static function epargneDeTranche(array $carnet, int $tranche): int
    {
        $taille = (int) $carnet['taille_tranche'];
        $total = (int) $carnet['nombre_parts'];
        $debut = ($tranche - 1) * $taille + 1;
        $fin = min($tranche * $taille, $total);
        if ($taille < 1 || $debut > $total) {
            return 0;
        }
        $benefice = count(array_filter(
            TypeCarnet::partsBenefice($total, $taille, (int) $carnet['parts_benefice_par_tranche'], (string) $carnet['prelevement_benefice']),
            static fn (int $n) => $n >= $debut && $n <= $fin
        ));
        return ($fin - $debut + 1 - $benefice) * Montant::enCentimes($carnet['mise']);
    }

    /** Nombre de tranches entierement cotisees. La derniere, plus courte, compte quand le carnet est plein. */
    public static function tranchesCompletes(array $carnet, int $partsValidees): int
    {
        $taille = (int) $carnet['taille_tranche'];
        if ($taille < 1) {
            return 0;
        }
        if ($partsValidees >= (int) $carnet['nombre_parts']) {
            return TypeCarnet::nombreTranches((int) $carnet['nombre_parts'], $taille);
        }
        return intdiv($partsValidees, $taille);
    }

    /**
     * Frais de tenue de compte dus pour une operation (centimes).
     * @param int $base solde (ou montant du retrait) sur lequel porte un pourcentage
     */
    public static function frais(array $carnet, int $base, int $decimalesDevise): int
    {
        return match ($carnet['frais_tenue_type']) {
            'FIXE' => Montant::enCentimes($carnet['frais_tenue_valeur']),
            'POURCENTAGE_SOLDE', 'POURCENTAGE_RETRAIT' => Montant::arrondirDevise(Montant::pourcentage($base, (string) $carnet['frais_tenue_valeur']), $decimalesDevise),
            default => 0,
        };
    }
}

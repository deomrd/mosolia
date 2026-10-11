<?php
namespace AvecXY\Domaine;

/**
 * Interets qu'une organisation verse sur un compte bloque.
 * L'interet court chaque jour sur le solde du jour : un depot fait en cours de periode ne rapporte
 * qu'a partir de son jour, un retrait cesse de rapporter le jour meme.
 * Le taux est exprime par an, par mois, ou pour toute la duree du blocage.
 */
final class InteretsEpargne
{
    /**
     * @param array<int,array{jour:string,montant:int}> $mouvements variations du solde (centimes, signees), dans n'importe quel ordre
     * @param string $du premier jour compte ; $au jour qui suit le dernier jour compte
     * @return int centimes, arrondis a l'unite de la devise
     */
    public static function calculer(array $mouvements, string $du, string $au, string $taux, string $base, int $dureeJours, int $decimales): int
    {
        if ($au <= $du) {
            return 0;
        }
        $parJour = self::tauxJournalier($taux, $base, $dureeJours);
        if ($parJour <= 0) {
            return 0;
        }
        usort($mouvements, static fn ($a, $b) => strcmp($a['jour'], $b['jour']));
        // solde au matin du premier jour : tout ce qui s'est passe avant
        $solde = 0;
        $suivants = [];
        foreach ($mouvements as $m) {
            if ($m['jour'] < $du) {
                $solde += $m['montant'];
            } elseif ($m['jour'] < $au) {
                $suivants[] = $m;
            }
        }
        // somme des soldes de fin de journee sur la periode, par paliers
        $cumul = 0.0;
        $jour = $du;
        $i = 0;
        $n = count($suivants);
        while ($jour < $au) {
            while ($i < $n && $suivants[$i]['jour'] === $jour) {
                $solde += $suivants[$i]['montant'];
                $i++;
            }
            $prochain = $i < $n ? $suivants[$i]['jour'] : $au;
            $jours = Dates::joursEntre($jour, $prochain);
            $cumul += max(0, $solde) * $jours;
            $jour = $prochain;
        }
        return Montant::arrondirDevise((int) round($cumul * $parJour), $decimales);
    }

    public static function tauxJournalier(string $taux, string $base, int $dureeJours): float
    {
        $t = (float) $taux / 100;
        return match ($base) {
            'MOIS' => $t * 12 / 365,
            'DUREE' => $dureeJours > 0 ? $t / $dureeJours : 0.0,
            default => $t / 365,
        };
    }

    /**
     * Periodes de versement d'un compte bloque, de son ouverture a son echeance.
     * MENSUEL : un versement a chaque mois anniversaire ; FIN : un seul a l'echeance.
     * @return array<int,array{periode:string,du:string,au:string}>
     */
    public static function periodes(string $ouverture, string $echeance, string $versement): array
    {
        if ($versement !== 'MENSUEL') {
            return [['periode' => 'FIN', 'du' => $ouverture, 'au' => $echeance]];
        }
        $liste = [];
        $debut = new \DateTimeImmutable($ouverture);
        for ($k = 1; $k <= 1200; $k++) {
            $du = $debut->modify('+' . ($k - 1) . ' months')->format('Y-m-d');
            if ($du >= $echeance) {
                break;
            }
            $au = min($echeance, $debut->modify('+' . $k . ' months')->format('Y-m-d'));
            $liste[] = ['periode' => sprintf('M%03d', $k), 'du' => $du, 'au' => $au];
        }
        return $liste;
    }

    /** Date d'echeance d'un blocage de N mois. */
    public static function echeance(string $ouverture, int $mois): string
    {
        return (new \DateTimeImmutable($ouverture))->modify('+' . $mois . ' months')->format('Y-m-d');
    }
}

<?php
namespace AvecXY\Domaine;

/**
 * Calendrier de remboursement d'un credit.
 *   FIXE : l'interet est calcule une fois sur le montant prete ; echeances egales.
 *   DEGRESSIF : interet de chaque periode sur le capital restant du ; echeances egales (annuites constantes).
 *   DEGRESSIF_CAPITAL : meme part de capital a chaque echeance, plus l'interet sur le restant du ; echeances decroissantes.
 * Avec un taux degressif, le taux est celui d'une periode (d'une echeance a la suivante).
 */
final class Echeancier
{
    public const MODES = ['FIXE', 'DEGRESSIF', 'DEGRESSIF_CAPITAL'];
    public const UNITES = ['JOUR', 'SEMAINE', 'MOIS'];

    /** Dates des echeances : tous les N jours, N semaines ou N mois apres l'octroi. @return string[] */
    public static function dates(string $dateCredit, int $nombre, int $intervalle, string $unite): array
    {
        $debut = new \DateTimeImmutable($dateCredit);
        $dates = [];
        for ($n = 1; $n <= max(1, $nombre); $n++) {
            $dates[] = match ($unite) {
                'MOIS' => $debut->modify('+' . ($n * $intervalle) . ' months')->format('Y-m-d'),
                'SEMAINE' => $debut->modify('+' . ($n * $intervalle * 7) . ' days')->format('Y-m-d'),
                default => $debut->modify('+' . ($n * $intervalle) . ' days')->format('Y-m-d'),
            };
        }
        return $dates;
    }

    /**
     * Tableau d'amortissement.
     * @param string[] $dates une date par echeance
     * @param int $interetFixe interet total en mode FIXE (ignore sinon)
     * @return array<int,array{numero:int,date_prevue:string,capital:int,interet:int,montant:int,restant:int}> centimes ; restant = capital du apres l'echeance
     */
    public static function amortissement(int $capital, string $taux, array $dates, string $mode, int $interetFixe, int $decimales): array
    {
        $n = max(1, count($dates));
        $r = (float) $taux / 100;
        $arrondi = static fn (float $c): int => Montant::arrondirDevise((int) round($c), $decimales);
        $lignes = [];
        $restant = $capital;

        if ($mode === 'FIXE' || $r <= 0) {
            $interet = $mode === 'FIXE' ? $interetFixe : 0;
            $total = $capital + $interet;
            $cumulCapital = 0;
            foreach (self::generer($total, $n, 1, $dates[0] ?? date('Y-m-d'), $decimales) as $i => $e) {
                $partCapital = $i === $n - 1 ? $capital - $cumulCapital : min($capital - $cumulCapital, $arrondi($total > 0 ? $e['montant'] * $capital / $total : 0));
                $cumulCapital += $partCapital;
                $restant -= $partCapital;
                $lignes[] = ['numero' => $i + 1, 'date_prevue' => $dates[$i], 'capital' => $partCapital, 'interet' => $e['montant'] - $partCapital, 'montant' => $e['montant'], 'restant' => $restant];
            }
            return $lignes;
        }

        $annuite = $arrondi($capital * $r / (1 - (1 + $r) ** -$n));
        $partFixe = $arrondi($capital / $n);
        for ($k = 1; $k <= $n; $k++) {
            $interet = $arrondi($restant * $r);
            if ($k === $n) {
                $partCapital = $restant;
            } elseif ($mode === 'DEGRESSIF') {
                $partCapital = max(0, min($restant, $annuite - $interet));
            } else {
                $partCapital = min($restant, $partFixe);
            }
            $restant -= $partCapital;
            $lignes[] = ['numero' => $k, 'date_prevue' => $dates[$k - 1], 'capital' => $partCapital, 'interet' => $interet, 'montant' => $partCapital + $interet, 'restant' => $restant];
        }
        return $lignes;
    }

    /**
     * Le calendrier porte-t-il le partage capital / interet (credit accorde avec un tableau d'amortissement) ?
     * @param array<int,array> $echeances lignes de avecxy_credit_echeances
     */
    public static function avecAmortissement(array $echeances): bool
    {
        foreach ($echeances as $e) {
            if (($e['capital'] ?? null) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Partage d'un paiement selon le tableau d'amortissement : les paiements couvrent les echeances dans l'ordre ;
     * dans chaque echeance, l'interet (frais ajoutes compris) passe avant le capital. Une echeance sans partage
     * (penalite de reconduction) et tout ce qui depasse le calendrier sont des penalites.
     * @param int $deja centimes deja payes avant ce paiement
     * @return array{capital:int,interet:int,penalite:int}
     */
    public static function partagerPaiement(array $echeances, int $deja, int $montant): array
    {
        $r = ['capital' => 0, 'interet' => 0, 'penalite' => 0];
        $debut = $deja;
        $fin = $deja + $montant;
        $curseur = 0;
        $tranches = [];
        foreach ($echeances as $e) {
            $total = Montant::enCentimes($e['montant']);
            if (($e['capital'] ?? null) === null) {
                $tranches[] = ['penalite', $total];
                continue;
            }
            $capital = Montant::enCentimes($e['capital']);
            $tranches[] = ['interet', $total - $capital];
            $tranches[] = ['capital', $capital];
        }
        foreach ($tranches as [$nature, $taille]) {
            $de = max($debut, $curseur);
            $a = min($fin, $curseur + $taille);
            if ($a > $de) {
                $r[$nature] += $a - $de;
            }
            $curseur += $taille;
        }
        if ($fin > max($debut, $curseur)) {
            $r['penalite'] += $fin - max($debut, $curseur);
        }
        return $r;
    }

    /** Somme des interets d'un tableau d'amortissement. */
    public static function totalInterets(array $lignes): int
    {
        return array_sum(array_column($lignes, 'interet'));
    }

    /**
     * @return array<int,array{numero:int,date_prevue:string,montant:int}> montants en centimes
     */
    public static function generer(int $total, int $nombre, int $intervalleJours, string $dateCredit, int $decimalesDevise): array
    {
        $nombre = max(1, $nombre);
        $part = Montant::arrondirDevise(intdiv($total, $nombre), $decimalesDevise);
        $lignes = [];
        $reste = $total;
        for ($n = 1; $n <= $nombre; $n++) {
            $montant = $n === $nombre ? $reste : min($reste, $part);
            $reste -= $montant;
            $lignes[] = ['numero' => $n, 'date_prevue' => Dates::ajouterJours($dateCredit, $n * $intervalleJours), 'montant' => $montant];
        }
        return $lignes;
    }

    /**
     * Situation de chaque echeance selon ce qui a deja ete rembourse (les paiements couvrent les echeances dans l'ordre).
     * @param array<int,array{date_prevue:string,montant:mixed}> $echeances
     * @return array{lignes:array,attendu:int,retard:int} attendu = du a ce jour ; retard = part echue non payee
     */
    public static function situation(array $echeances, int $rembourse, string $aujourdhui, int $joursTolerance = 0): array
    {
        $cumul = 0;
        $attendu = 0;
        $retard = 0;
        foreach ($echeances as &$e) {
            $montant = Montant::enCentimes($e['montant']);
            $paye = max(0, min($montant, $rembourse - $cumul));
            $cumul += $montant;
            $echue = Dates::ajouterJours((string) $e['date_prevue'], $joursTolerance) < $aujourdhui;
            $e['paye'] = $paye;
            $e['etat'] = $paye >= $montant ? 'PAYEE' : ($echue ? 'EN_RETARD' : ($paye > 0 ? 'PARTIELLE' : 'A_VENIR'));
            if ((string) $e['date_prevue'] <= $aujourdhui) {
                $attendu += $montant - $paye;
            }
            if ($echue) {
                $retard += $montant - $paye;
            }
        }
        return ['lignes' => $echeances, 'attendu' => $attendu, 'retard' => $retard];
    }
}

<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\InteretsEpargne;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Verse les interets des comptes bloques : chaque mois anniversaire, ou en une fois a l'echeance,
 * selon la regle copiee sur le compte. Une periode n'est jamais versee deux fois.
 */
final class ServiceInterets
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCarnets $carnets,
        private readonly DepotOperations $operations,
        private readonly RecalculCarnet $recalcul,
    ) {
    }

    /**
     * Verse ce qui est du sur un compte, jusqu'a aujourd'hui. A appeler dans une transaction.
     * @return int centimes verses
     */
    public function verser(int $org, int $carnetId, int $decimales, ?string $aujourdhui = null): int
    {
        $aujourdhui ??= date('Y-m-d');
        $total = 0;
        // un compte renouvele automatiquement enchaine les periodes : on rattrape jusqu'a aujourd'hui
        for ($tour = 0; $tour < 60; $tour++) {
            $carnet = $this->carnets->verrouiller($org, $carnetId);
            if ($carnet === null || ($carnet['blocage'] ?? null) === null || $carnet['statut'] !== 'ACTIF'
                || $carnet['date_echeance'] === null || ($carnet['validation'] ?? 'VALIDE') !== 'VALIDE') {
                return $total;
            }
            $total += $this->verserPeriode($org, $carnet, $decimales, $aujourdhui);
            // renouvele le lendemain de l'echeance : le jour meme, le client peut encore tout retirer
            if (($carnet['renouvellement'] ?? 'AUCUN') !== 'AUTOMATIQUE' || $aujourdhui <= (string) $carnet['date_echeance'] || (int) $carnet['duree_mois'] < 1) {
                return $total;
            }
            $debut = (string) $carnet['date_echeance'];
            $this->carnets->modifier($carnetId, [
                'debut_blocage' => $debut, 'date_echeance' => InteretsEpargne::echeance($debut, (int) $carnet['duree_mois']),
                'cycle' => (int) ($carnet['cycle'] ?? 1) + 1,
            ]);
        }
        return $total;
    }

    /** Debut de la periode de blocage en cours. */
    public static function debut(array $carnet): string
    {
        return (string) ($carnet['debut_blocage'] ?? null ?: $carnet['date_ouverture']);
    }

    /** Cle d'une periode : le premier cycle garde FIN, M001... ; les suivants sont prefixes (C2-FIN). */
    private static function cle(array $carnet, string $periode): string
    {
        $cycle = (int) ($carnet['cycle'] ?? 1);
        return $cycle > 1 ? 'C' . $cycle . '-' . $periode : $periode;
    }

    private function verserPeriode(int $org, array $carnet, int $decimales, string $aujourdhui): int
    {
        $carnetId = (int) $carnet['id'];
        $debut = self::debut($carnet);
        $duree = Dates::joursEntre($debut, (string) $carnet['date_echeance']);
        $total = 0;
        foreach (InteretsEpargne::periodes($debut, (string) $carnet['date_echeance'], (string) $carnet['interets_versement']) as $p) {
            $cle = self::cle($carnet, $p['periode']);
            if ($p['au'] > $aujourdhui || $this->operations->interetDejaVerse($carnetId, $cle)) {
                continue;
            }
            $montant = InteretsEpargne::calculer(
                $this->operations->mouvementsEpargne($carnet), $p['du'], $p['au'],
                (string) $carnet['taux_interet'], (string) $carnet['taux_base'], $duree, $decimales
            );
            if ($montant <= 0) {
                continue;
            }
            $this->operations->creerInteret([
                'organisation_id' => $org, 'carnet_id' => $carnetId, 'client_id' => $carnet['client_id'], 'agent_id' => $carnet['agent_id'],
                'date_interet' => $p['au'], 'periode' => $cle, 'du_jour' => $p['du'], 'au_jour' => $p['au'],
                'montant' => Montant::depuisCentimes($montant), 'cree_le' => $this->bd->maintenant(),
            ]);
            $carnet = $this->recalcul->recalculer($org, $carnetId);
            $total += $montant;
        }
        return $total;
    }

    /** Tous les comptes bloques ouverts de l'organisation (travaux du jour). @return int comptes servis */
    public function toutVerser(int $org, int $decimales): int
    {
        $ids = array_column($this->bd->tous(
            "SELECT id FROM avecxy_carnets WHERE organisation_id = ? AND blocage IS NOT NULL AND statut = 'ACTIF' AND supprime_le IS NULL",
            [$org]
        ), 'id');
        $servis = 0;
        foreach ($ids as $id) {
            $verse = $this->bd->transaction(fn (): int => $this->verser($org, (int) $id, $decimales));
            if ($verse > 0) {
                $servis++;
            }
        }
        return $servis;
    }

    /**
     * Interet couru depuis le dernier versement, pas encore verse (pour l'affichage).
     */
    public function couru(array $carnet, int $decimales, ?string $aujourdhui = null): int
    {
        $aujourdhui ??= date('Y-m-d');
        if (($carnet['blocage'] ?? null) === null || $carnet['date_echeance'] === null || $carnet['statut'] !== 'ACTIF') {
            return 0;
        }
        $debut = self::debut($carnet);
        $duree = Dates::joursEntre($debut, (string) $carnet['date_echeance']);
        foreach (InteretsEpargne::periodes($debut, (string) $carnet['date_echeance'], (string) $carnet['interets_versement']) as $p) {
            if ($this->operations->interetDejaVerse((int) $carnet['id'], self::cle($carnet, $p['periode']))) {
                continue;
            }
            $au = min($p['au'], Dates::ajouterJours($aujourdhui, 1));
            return InteretsEpargne::calculer($this->operations->mouvementsEpargne($carnet), $p['du'], $au,
                (string) $carnet['taux_interet'], (string) $carnet['taux_base'], $duree, $decimales);
        }
        return 0;
    }
}

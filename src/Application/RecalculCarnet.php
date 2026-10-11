<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Remet un carnet d'aplomb apres tout changement : repartition de chaque cotisation
 * entre benefice et epargne, benefices partages, totaux et solde du client.
 * Toujours appele dans la transaction de l'operation qui a provoque le changement.
 */
final class RecalculCarnet
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCarnets $carnets,
        private readonly DepotOperations $operations,
        private readonly ServicePartage $partage,
    ) {
    }

    /** @return array le carnet a jour */
    public function recalculer(int $org, int $carnetId): array
    {
        $carnet = $this->carnets->verrouiller($org, $carnetId) ?? throw new ErreurMetier('carnets.carnet_introuvable');
        $cotisations = $this->operations->validees($carnetId);
        $r = CalculCarnet::repartir($carnet, $cotisations);

        $this->operations->effacerBeneficesDuCarnet($carnetId);
        $benefice = function (string $source, int $sourceId, string $date, int $centimes) use ($org, $carnet): void {
            if ($centimes <= 0) {
                return;
            }
            $p = $this->partage->repartir($org, $source, $date, $centimes);
            $this->operations->creerBenefice([
                'organisation_id' => $org, 'date_operation' => $date, 'periode' => substr($date, 0, 7),
                'source' => $source, 'source_id' => $sourceId, 'agent_id' => $carnet['agent_id'], 'client_id' => $carnet['client_id'],
                'regle_partage_id' => $p['regle_id'], 'montant_total' => Montant::depuisCentimes($centimes),
                'part_agent' => Montant::depuisCentimes($p['agent']), 'part_organisation' => Montant::depuisCentimes($p['organisation']),
                'carnet_id' => $carnet['id'], 'cree_le' => $this->bd->maintenant(),
            ]);
        };

        if (($carnet['validation'] ?? 'VALIDE') === 'VALIDE') {
            $benefice('VENTE_CARNET', (int) $carnet['id'], (string) $carnet['date_ouverture'], Montant::enCentimes($carnet['prix']));
        }
        foreach ($cotisations as $c) {
            $l = $r['lignes'][(int) $c['id']];
            $this->operations->modifierCotisation((int) $c['id'], [
                'mise' => $carnet['mode'] === 'PARTS' ? $carnet['mise'] : null,
                'montant_total' => Montant::depuisCentimes($l['montant_total']),
                'parts_benefice' => $l['parts_benefice'], 'parts_epargne' => $l['parts_epargne'],
                'montant_benefice' => Montant::depuisCentimes($l['montant_benefice']),
                'montant_epargne' => Montant::depuisCentimes($l['montant_epargne']),
            ]);
            $benefice('PART_BENEFICE', (int) $c['id'], (string) $c['date_cotisation'], $l['benefice_a_partager'] ?? $l['montant_benefice']);
        }
        // carnet repris dont le benefice n'avait pas encore ete pris sur papier : il se partage a la date de la reprise
        $benefice('PART_BENEFICE', 0, substr((string) $carnet['cree_le'], 0, 10), (int) ($r['reprise_benefice'] ?? 0));
        $totalFrais = 0;
        foreach ($this->operations->fraisDuCarnet($carnetId) as $f) {
            $centimes = Montant::enCentimes($f['montant']);
            $totalFrais += $centimes;
            $benefice('FRAIS_TENUE', (int) $f['id'], (string) $f['date_frais'], $centimes);
        }

        // interets verses sur un compte bloque : une charge pour l'organisation, qui reduit son benefice
        $totalInterets = 0;
        foreach ($this->operations->interetsDuCarnet($carnetId) as $i) {
            $centimes = Montant::enCentimes($i['montant']);
            $totalInterets += $centimes;
            if ($centimes > 0) {
                $this->operations->creerBenefice([
                    'organisation_id' => $org, 'date_operation' => $i['date_interet'], 'periode' => substr((string) $i['date_interet'], 0, 7),
                    'source' => 'INTERET_EPARGNE', 'source_id' => $i['id'], 'agent_id' => $carnet['agent_id'], 'client_id' => $carnet['client_id'],
                    'regle_partage_id' => null, 'montant_total' => Montant::depuisCentimes(-$centimes),
                    'part_agent' => '0.00', 'part_organisation' => Montant::depuisCentimes(-$centimes),
                    'carnet_id' => $carnet['id'], 'cree_le' => $this->bd->maintenant(),
                ]);
            }
        }

        $retraits = $this->operations->totauxRetraits($carnetId);
        $retire = Montant::enCentimes($retraits['retire']) + Montant::enCentimes($carnet['reprise_retire'] ?? 0);
        $epargne = $r['epargne_brute'] + $totalInterets - $retire - $totalFrais;
        if ($epargne < 0) {
            // ex. suppression d'une cotisation dont l'epargne a deja ete retiree
            throw new ErreurMetier('carnets.epargne_negative');
        }
        $maj = [
            'parts_validees' => $r['parts_validees'], 'parts_benefice_couvertes' => $r['parts_benefice'],
            'total_cotise' => Montant::depuisCentimes($r['total_cotise']), 'epargne' => Montant::depuisCentimes($epargne),
            'total_retire' => Montant::depuisCentimes($retire), 'total_frais' => Montant::depuisCentimes($totalFrais),
            'total_interets' => Montant::depuisCentimes($totalInterets),
            'tranches_retirees' => (int) $retraits['tranches'] + (int) ($carnet['reprise_tranches'] ?? 0),
        ];
        $this->carnets->modifier($carnetId, $maj);
        return array_merge($carnet, $maj);
    }
}

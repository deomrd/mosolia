<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\Echeancier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Infrastructure\Tableur;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Indicateurs de gestion d'une microfinance : portefeuille a risque, encours et epargne par produit,
 * interets recus et verses, taux de remboursement, clientele.
 * Le capital restant d'un credit = montant prete - rembourse (le capital est rembourse en premier).
 */
final class ServiceIndicateurs
{
    public const TRANCHES = [['a_jour', 0, 0], ['j1_30', 1, 30], ['j31_90', 31, 90], ['j91_180', 91, 180], ['j181', 181, PHP_INT_MAX]];

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    /** Filtre sur l'agence du recolteur : null = tout, 0 = siege, N = agence N. @return array{0:string,1:array} */
    private static function agence(string $colonneRecolteur, ?int $agence): array
    {
        if ($agence === null) {
            return ['', []];
        }
        return $agence === 0
            ? [" AND $colonneRecolteur IN (SELECT id FROM avecxy_agents WHERE agence_id IS NULL)", []]
            : [" AND $colonneRecolteur IN (SELECT id FROM avecxy_agents WHERE agence_id = ?)", [$agence]];
    }

    /**
     * Jours de retard d'un credit a une date : depuis la plus ancienne echeance echue non payee
     * (ou depuis l'echeance finale pour un credit sans calendrier).
     */
    public static function joursDeRetard(array $credit, array $echeances, string $jour): int
    {
        $rembourse = Montant::enCentimes($credit['total_rembourse']);
        if ($echeances) {
            foreach (Echeancier::situation($echeances, $rembourse, $jour)['lignes'] as $e) {
                if ($e['paye'] < Montant::enCentimes($e['montant']) && (string) $e['date_prevue'] < $jour) {
                    return Dates::joursEntre((string) $e['date_prevue'], $jour);
                }
            }
            return 0;
        }
        return Montant::enCentimes($credit['restant']) > 0 && (string) $credit['date_echeance'] < $jour ? Dates::joursEntre((string) $credit['date_echeance'], $jour) : 0;
    }

    /**
     * Capital restant d'un credit : selon son tableau d'amortissement s'il en a un,
     * sinon prete - deja rembourse (le capital est rembourse en premier).
     */
    public static function capitalRestant(array $credit, array $echeances): int
    {
        $prete = Montant::enCentimes($credit['montant']);
        $rembourse = Montant::enCentimes($credit['total_rembourse']);
        if (Echeancier::avecAmortissement($echeances)) {
            return max(0, $prete - Echeancier::partagerPaiement($echeances, 0, $rembourse)['capital']);
        }
        return max(0, $prete - $rembourse);
    }

    /** @return array tous les indicateurs (montants en centimes) */
    public function calculer(int $org, string $du, string $au, ?int $agence): array
    {
        [$filtreCr, $pCr] = self::agence('cr.agent_id', $agence);
        $credits = $this->bd->tous(
            "SELECT cr.*, p.nom AS produit_nom, p.id AS produit_id FROM avecxy_credits cr JOIN avecxy_credit_produits p ON p.id = cr.credit_produit_id
             WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.date_credit <= ?$filtreCr",
            array_merge([$org, $au], $pCr)
        );
        $tranches = [];
        foreach (self::TRANCHES as [$cle]) {
            $tranches[$cle] = ['nombre' => 0, 'capital' => 0];
        }
        $produits = [];
        $encours = 0;
        $par = [1 => 0, 30 => 0, 90 => 0];
        foreach ($credits as $cr) {
            $echeances = $this->bd->tous('SELECT * FROM avecxy_credit_echeances WHERE credit_id = ? ORDER BY numero, id', [$cr['id']]);
            $retard = self::joursDeRetard($cr, $echeances, $au);
            $capital = self::capitalRestant($cr, $echeances);
            $encours += $capital;
            foreach (self::TRANCHES as [$cle, $min, $max]) {
                if ($retard >= $min && $retard <= $max) {
                    $tranches[$cle]['nombre']++;
                    $tranches[$cle]['capital'] += $capital;
                }
            }
            foreach ($par as $seuil => $_) {
                if ($retard >= $seuil) {
                    $par[$seuil] += $capital;
                }
            }
            $p = (int) $cr['produit_id'];
            $produits[$p] ??= ['nom' => $cr['produit_nom'], 'nombre' => 0, 'prete' => 0, 'capital' => 0, 'restant' => 0, 'en_retard' => 0];
            $produits[$p]['nombre']++;
            $produits[$p]['prete'] += Montant::enCentimes($cr['montant']);
            $produits[$p]['capital'] += $capital;
            $produits[$p]['restant'] += Montant::enCentimes($cr['restant']);
            $produits[$p]['en_retard'] += $retard > 0 ? $capital : 0;
        }
        $parPct = [];
        foreach ($par as $seuil => $m) {
            $parPct[$seuil] = $encours > 0 ? round($m * 100 / $encours, 2) : 0.0;
        }

        // taux de remboursement : echeances tombees dans la periode, et ce qui en est paye
        $echeancesPeriode = $this->bd->tous(
            "SELECT cr.id AS credit_id, cr.total_rembourse FROM avecxy_credits cr
             WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE'$filtreCr
               AND EXISTS (SELECT 1 FROM avecxy_credit_echeances e WHERE e.credit_id = cr.id AND e.date_prevue >= ? AND e.date_prevue <= ?)",
            array_merge([$org], $pCr, [$du, $au])
        );
        $du_ = 0;
        $paye = 0;
        foreach ($echeancesPeriode as $c) {
            $lignes = $this->bd->tous('SELECT * FROM avecxy_credit_echeances WHERE credit_id = ? ORDER BY numero, id', [$c['credit_id']]);
            foreach (Echeancier::situation($lignes, Montant::enCentimes($c['total_rembourse']), $au)['lignes'] as $e) {
                if ((string) $e['date_prevue'] >= $du && (string) $e['date_prevue'] <= $au) {
                    $du_ += Montant::enCentimes($e['montant']);
                    $paye += $e['paye'];
                }
            }
        }

        // flux de la periode
        [$filtreB, $pB] = self::agence('b.agent_id', $agence);
        $source = fn (string $s): int => Montant::enCentimes($this->bd->valeur(
            "SELECT COALESCE(SUM(b.montant_total),0) FROM avecxy_benefices b WHERE b.organisation_id = ? AND b.source = ? AND b.date_operation >= ? AND b.date_operation <= ?$filtreB",
            array_merge([$org, $s, $du, $au], $pB)
        ) ?? 0);
        [$filtreI, $pI] = self::agence('i.agent_id', $agence);
        $interetsVerses = Montant::enCentimes($this->bd->valeur(
            "SELECT COALESCE(SUM(i.montant),0) FROM avecxy_interets_epargne i WHERE i.organisation_id = ? AND i.supprime_le IS NULL AND i.date_interet >= ? AND i.date_interet <= ?$filtreI",
            array_merge([$org, $du, $au], $pI)
        ) ?? 0);
        $decaisse = $this->bd->un(
            "SELECT COUNT(*) AS nombre, COALESCE(SUM(cr.montant),0) AS montant FROM avecxy_credits cr
             WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut <> 'ANNULE' AND cr.reprise_id IS NULL AND cr.date_credit >= ? AND cr.date_credit <= ?$filtreCr",
            array_merge([$org, $du, $au], $pCr)
        );

        // epargne par produit
        [$filtreK, $pK] = self::agence('k.agent_id', $agence);
        $epargne = $this->bd->tous(
            "SELECT ty.id, ty.nom, ty.mode, ty.blocage, COUNT(k.id) AS nombre, COALESCE(SUM(k.epargne),0) AS epargne, COALESCE(SUM(k.total_interets),0) AS interets
             FROM avecxy_carnet_types ty JOIN avecxy_carnets k ON k.carnet_type_id = ty.id AND k.statut = 'ACTIF' AND k.supprime_le IS NULL$filtreK
             WHERE ty.organisation_id = ? GROUP BY ty.id, ty.nom, ty.mode, ty.blocage ORDER BY ty.nom",
            array_merge($pK, [$org])
        );
        foreach ($epargne as &$e) {
            $e['mode_affiche'] = TypeCarnet::modeAffiche($e);
            $e['epargne'] = Montant::enCentimes($e['epargne']);
            $e['interets'] = Montant::enCentimes($e['interets']);
        }
        unset($e);

        // clientele
        [$filtreC, $pC] = self::agence('c.agent_id', $agence);
        $clients = $this->bd->un(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN c.sexe = 'F' THEN 1 ELSE 0 END) AS femmes, SUM(CASE WHEN c.sexe = 'M' THEN 1 ELSE 0 END) AS hommes,
                    SUM(CASE WHEN EXISTS (SELECT 1 FROM avecxy_credits cr WHERE cr.client_id = c.id AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.supprime_le IS NULL) THEN 1 ELSE 0 END) AS emprunteurs,
                    SUM(CASE WHEN c.piece_numero IS NOT NULL THEN 1 ELSE 0 END) AS avec_piece
             FROM avecxy_clients c WHERE c.organisation_id = ? AND c.supprime_le IS NULL AND c.statut = 'ACTIF'$filtreC",
            array_merge([$org], $pC)
        ) ?? [];

        return [
            'encours' => $encours, 'nombre_credits' => count($credits), 'tranches' => $tranches, 'par' => $par, 'par_pct' => $parPct,
            'produits' => array_values($produits),
            'remboursement' => ['du' => $du_, 'paye' => $paye, 'taux' => $du_ > 0 ? round($paye * 100 / $du_, 2) : null],
            'flux' => [
                'decaisse_nombre' => (int) ($decaisse['nombre'] ?? 0), 'decaisse' => Montant::enCentimes($decaisse['montant'] ?? 0),
                'interets_recus' => $source('INTERET_CREDIT'), 'penalites' => $source('PENALITE_CREDIT'), 'frais_dossier' => $source('FRAIS_DOSSIER'),
                'interets_verses' => $interetsVerses, 'frais_tenue' => $source('FRAIS_TENUE'),
            ],
            'epargne' => $epargne, 'epargne_totale' => array_sum(array_column($epargne, 'epargne')),
            'clients' => array_map('intval', $clients),
        ];
    }

    /** Classeur Excel des indicateurs. */
    public function classeur(array $i, string $titre, string $du, string $au): string
    {
        $a = static fn (int $c): float => $c / 100;
        $feuille = [[$titre], [t('gestion.periode', ['du' => jour($du), 'au' => jour($au)])], [],
            [t('gestion.portefeuille')], [t('gestion.encours_capital'), $a($i['encours'])], [t('gestion.nombre_credits'), $i['nombre_credits']]];
        foreach ([1, 30, 90] as $s) {
            $feuille[] = [t('gestion.par', ['n' => $s]), $a($i['par'][$s]), $i['par_pct'][$s] / 100];
        }
        $feuille[] = [];
        $feuille[] = [t('gestion.anciennete_retards'), t('gestion.nombre'), t('gestion.capital_restant')];
        foreach ($i['tranches'] as $cle => $tr) {
            $feuille[] = [t('gestion.tranche.' . $cle), $tr['nombre'], $a($tr['capital'])];
        }
        $feuille[] = [];
        $feuille[] = [t('gestion.par_produit'), t('gestion.nombre'), t('gestion.prete'), t('gestion.capital_restant'), t('gestion.en_retard')];
        foreach ($i['produits'] as $p) {
            $feuille[] = [$p['nom'], $p['nombre'], $a($p['prete']), $a($p['capital']), $a($p['en_retard'])];
        }
        $feuille[] = [];
        $feuille[] = [t('gestion.flux')];
        foreach ($i['flux'] as $cle => $v) {
            $feuille[] = [t('gestion.flux.' . $cle), $cle === 'decaisse_nombre' ? $v : $a($v)];
        }
        $feuille[] = [t('gestion.taux_remboursement'), $i['remboursement']['taux'] === null ? '' : $i['remboursement']['taux'] / 100, $a($i['remboursement']['paye']), $a($i['remboursement']['du'])];
        $feuille[] = [];
        $feuille[] = [t('gestion.epargne_par_produit'), t('gestion.nombre'), t('gestion.epargne'), t('gestion.interets_verses')];
        foreach ($i['epargne'] as $e) {
            $feuille[] = [$e['nom'], (int) $e['nombre'], $a($e['epargne']), $a($e['interets'])];
        }
        $feuille[] = [];
        foreach (['total', 'femmes', 'hommes', 'emprunteurs', 'avec_piece'] as $cle) {
            $feuille[] = [t('gestion.clients.' . $cle), $i['clients'][$cle] ?? 0];
        }
        return Tableur::ecrire([['nom' => t('menu.rapports_gestion'), 'lignes' => $feuille, 'largeurs' => [44, 16, 18, 18, 18]]]);
    }
}

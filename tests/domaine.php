<?php
// Verifie les regles de calcul :  php tests/domaine.php
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\ControleCredit;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\Texte;
use AvecXY\Domaine\TypeCarnet;

$ko = 0;
$verifier = static function (string $quoi, mixed $obtenu, mixed $attendu) use (&$ko): void {
    if ($obtenu !== $attendu) {
        $ko++;
        echo "ECHEC  $quoi\n   attendu : " . json_encode($attendu) . "\n   obtenu  : " . json_encode($obtenu) . "\n";
    }
};

$verifier('nom', Texte::nom('  élodie  kasôngo '), 'ELODIE KASONGO');
$verifier('31 parts', TypeCarnet::partsBenefice(31, 31, 1, 'DEBUT_TRANCHE'), [1]);
$verifier('90 parts', TypeCarnet::partsBenefice(90, 30, 1, 'DEBUT_TRANCHE'), [1, 31, 61]);
$verifier('montant', Montant::lire('1 500,50', 2), '1500.50');
$verifier('centimes', Montant::enCentimes('1500.50'), 150050);
$verifier('centimes numerique', Montant::enCentimes(0.1 + 0.2), 30);
$verifier('depuis centimes', Montant::depuisCentimes(150050), '1500.50');
$verifier('45 % de 1000', Montant::pourcentage(100000, '45'), 45000);
$verifier('2,5 % de 999', Montant::pourcentage(99900, '2.5'), 2498);
$verifier('arrondi CDF', Montant::arrondirDevise(2498, 0), 2500);

$c90 = ['mode' => 'PARTS', 'mise' => '1000.00', 'nombre_parts' => 90, 'taille_tranche' => 30, 'parts_benefice_par_tranche' => 1, 'prelevement_benefice' => 'DEBUT_TRANCHE'];
$r = CalculCarnet::repartir($c90, [
    ['id' => 1, 'nombre_parts' => 1, 'montant_total' => 0],    // part 1 : benefice
    ['id' => 2, 'nombre_parts' => 29, 'montant_total' => 0],   // parts 2 a 30
    ['id' => 3, 'nombre_parts' => 5, 'montant_total' => 0],    // parts 31 a 35 : la 31 est benefice
]);
$verifier('90 : premiere cotisation', $r['lignes'][1]['montant_benefice'], 100000);
$verifier('90 : deuxieme cotisation', $r['lignes'][2]['montant_epargne'], 2900000);
$verifier('90 : troisieme cotisation', [$r['lignes'][3]['parts_benefice'], $r['lignes'][3]['parts_epargne']], [1, 4]);
$verifier('90 : totaux', [$r['parts_validees'], $r['parts_benefice'], $r['total_cotise'], $r['epargne_brute']], [35, 2, 3500000, 3300000]);
$verifier('90 : epargne tranche 1', CalculCarnet::epargneDeTranche($c90, 1), 2900000);
$verifier('90 : tranches completes a 35 parts', CalculCarnet::tranchesCompletes($c90, 35), 1);
$verifier('90 : tranches completes a 90 parts', CalculCarnet::tranchesCompletes($c90, 90), 3);

$libre = ['mode' => 'LIBRE', 'frais_tenue_type' => 'POURCENTAGE_SOLDE', 'frais_tenue_valeur' => '2.5'];
$r = CalculCarnet::repartir($libre, [['id' => 7, 'nombre_parts' => null, 'montant_total' => '12500.00']]);
$verifier('libre : tout en epargne', [$r['total_cotise'], $r['epargne_brute']], [1250000, 1250000]);
$verifier('libre : frais 2,5 % en CDF', CalculCarnet::frais($libre, 1250000, 0), 31300);

// --- credits ---
$produit = [
    'montant_minimum' => '1000.00', 'montant_maximum' => '50000.00', 'multiple_epargne_max' => '2.00', 'plafond_encours_pct_caisse' => '50.00',
    'credits_simultanes_max' => 1, 'carnets_fermes_minimum' => 1, 'carnet_actif_requis' => 1, 'anciennete_jours_minimum' => 30, 'sans_retard_anterieur' => 1,
];
$faits = ['epargne' => 1000000, 'carnets_fermes' => 1, 'carnet_actif' => true, 'anciennete_jours' => 45, 'retard_anterieur' => false, 'credits_en_cours' => 0, 'encours' => 0, 'caisse' => 10000000];
$verifier('credit conforme', ControleCredit::conforme(ControleCredit::controler($produit, $faits, 2000000)), true);
$refus = array_column(array_filter(ControleCredit::controler($produit, ['epargne' => 500000, 'retard_anterieur' => true, 'credits_en_cours' => 1] + $faits, 2000000), static fn ($l) => !$l['ok']), 'regle');
$verifier('credit : regles refusees', $refus, ['multiple_epargne', 'credits_simultanes', 'sans_retard']);
$verifier('credit : plafond des fonds', ControleCredit::conforme(ControleCredit::controler($produit, ['encours' => 4000000, 'caisse' => 6000000] + $faits, 2000000)), false);
// pret 8000 + interet 800 : le capital revient d'abord, puis l'interet, puis les penalites
$verifier('remboursement dans le capital', ControleCredit::beneficeDuRemboursement(800000, 80000, 0, 500000), ['interet' => 0, 'penalite' => 0]);
$verifier('remboursement a cheval', ControleCredit::beneficeDuRemboursement(800000, 80000, 500000, 350000), ['interet' => 50000, 'penalite' => 0]);
$verifier('remboursement final', ControleCredit::beneficeDuRemboursement(800000, 80000, 850000, 31500), ['interet' => 30000, 'penalite' => 1500]);

// --- calendrier de remboursement ---
$cal = AvecXY\Domaine\Echeancier::generer(11000000, 4, 7, '2026-01-01', 0);
$verifier('calendrier : 4 echeances egales', array_column($cal, 'montant'), [2750000, 2750000, 2750000, 2750000]);
$verifier('calendrier : dates', [$cal[0]['date_prevue'], $cal[3]['date_prevue']], ['2026-01-08', '2026-01-29']);
$verifier('calendrier : le reste va a la derniere', array_column(AvecXY\Domaine\Echeancier::generer(1000000, 3, 30, '2026-01-01', 0), 'montant'), [333300, 333300, 333400]);
$sit = AvecXY\Domaine\Echeancier::situation(array_map(static fn ($e) => ['date_prevue' => $e['date_prevue'], 'montant' => $e['montant'] / 100], $cal), 4000000, '2026-01-20');
$verifier('calendrier : etats', array_column($sit['lignes'], 'etat'), ['PAYEE', 'EN_RETARD', 'A_VENIR', 'A_VENIR']);
$verifier('calendrier : retard', $sit['retard'], 1500000);

// --- identifiants du personnel ---
$verifier('nom court propose : mots generiques retires', array_map([AvecXY\Domaine\Texte::class, 'nomCourtPropose'], ['LIGHT VISION BUSINESS', 'Maison Tuungane', 'IMF du Kivu', 'Avec Umoja']), ['lightvision', 'tuungane', 'imfkivu', 'umoja']);
$verifier('nom court propose : initiales si trop long, jamais de mot coupe', AvecXY\Domaine\Texte::nomCourtPropose("Coopérative d'épargne des agriculteurs du Sud-Kivu"), 'ceask');
$verifier('identifiant local reconnu', [AvecXY\Domaine\Texte::estIdentifiantValide('marie@tuungane'), AvecXY\Domaine\Texte::estIdentifiantValide('marie@'), AvecXY\Domaine\Texte::estIdentifiantValide('a@b.com'), AvecXY\Domaine\Texte::estIdentifiantValide('0899123456')], [true, false, true, true]);
$verifier('prenom pour identifiant', AvecXY\Domaine\Texte::prenomPourIdentifiant('Élodie-Marie Kasongo'), 'elodiemarie');

// --- carnet repris du papier ---
$repris = ['mode' => 'PARTS', 'mise' => '1000.00', 'nombre_parts' => 31, 'taille_tranche' => 31, 'parts_benefice_par_tranche' => 1, 'prelevement_benefice' => 'DEBUT_TRANCHE', 'reprise_parts' => 10];
$r = AvecXY\Domaine\CalculCarnet::repartir($repris + ['benefice_deja_pris' => 1], [['id' => 1, 'nombre_parts' => 2, 'montant_total' => 0]]);
$verifier('reprise, benefice deja pris : epargne et rien a partager', [$r['epargne_brute'], $r['reprise_benefice'], $r['parts_validees']], [1100000, 0, 12]);
$r = AvecXY\Domaine\CalculCarnet::repartir($repris + ['benefice_deja_pris' => 0], []);
$verifier('reprise, benefice pas encore pris : il reste a partager', [$r['epargne_brute'], $r['reprise_benefice']], [900000, 100000]);

// --- microfinance : credits a taux degressif ---
use AvecXY\Domaine\Echeancier;
use AvecXY\Domaine\InteretsEpargne;
$dates = Echeancier::dates('2026-01-31', 12, 1, 'MOIS');
$verifier('dates mensuelles : fin de mois', [$dates[0], $dates[1], $dates[11]], ['2026-03-03', '2026-03-31', '2027-01-31']);
$verifier('dates hebdomadaires', Echeancier::dates('2026-01-01', 2, 2, 'SEMAINE'), ['2026-01-15', '2026-01-29']);
// 100 000 FC a 2 % par mois sur 12 mois (sans centimes)
$a = Echeancier::amortissement(10000000, '2', Echeancier::dates('2026-01-05', 12, 1, 'MOIS'), 'DEGRESSIF', 0, 0);
$verifier('annuites : 9 456 par mois', array_unique(array_column(array_slice($a, 0, 11), 'montant')), [945600]);
$verifier('annuites : premiere echeance (interet 2 000)', [$a[0]['interet'], $a[0]['capital']], [200000, 745600]);
$verifier('annuites : tout le capital rembourse', [array_sum(array_column($a, 'capital')), end($a)['restant']], [10000000, 0]);
$verifier('annuites : interets totaux (arrondis au franc a chaque echeance)', Echeancier::totalInterets($a), 1347000);
$k = Echeancier::amortissement(10000000, '2', Echeancier::dates('2026-01-05', 12, 1, 'MOIS'), 'DEGRESSIF_CAPITAL', 0, 0);
$verifier('capital fixe : 8 333 de capital, echeances qui baissent', [$k[0]['capital'], $k[0]['montant'], $k[1]['montant'], $k[11]['capital']], [833300, 1033300, 1016600, 833700]);
$verifier('capital fixe : interets totaux (2 % x 100 000 x 6,5)', Echeancier::totalInterets($k), 1300000);
$f = Echeancier::amortissement(10000000, '10', Echeancier::dates('2026-01-05', 4, 1, 'SEMAINE'), 'FIXE', 1000000, 0);
$verifier('taux fixe : 4 echeances de 27 500 (capital 25 000 + interet 2 500)', [array_column($f, 'montant'), $f[0]['capital'], $f[0]['interet']], [[2750000, 2750000, 2750000, 2750000], 2500000, 250000]);

// paiement selon le tableau d'amortissement : interet de l'echeance, puis son capital
$tab = array_map(static fn ($l) => ['montant' => $l['montant'] / 100, 'capital' => $l['capital'] / 100], $a);
$verifier('premier paiement de 9 456 : 2 000 d\'interet, 7 456 de capital', Echeancier::partagerPaiement($tab, 0, 945600), ['capital' => 745600, 'interet' => 200000, 'penalite' => 0]);
$verifier('paiement partiel de 3 000 : l\'interet d\'abord', Echeancier::partagerPaiement($tab, 0, 300000), ['capital' => 100000, 'interet' => 200000, 'penalite' => 0]);
$verifier('tout payer d\'un coup : tous les interets restent dus', Echeancier::partagerPaiement($tab, 0, 11347000), ['capital' => 10000000, 'interet' => 1347000, 'penalite' => 0]);
$verifier('au-dela du calendrier : penalite', Echeancier::partagerPaiement($tab, 11347000, 50000), ['capital' => 0, 'interet' => 0, 'penalite' => 50000]);

// --- microfinance : interets d'un compte bloque ---
$depot = [['jour' => '2026-01-01', 'montant' => 10000000]];
$verifier('12 % par an sur 100 000 pendant un an', InteretsEpargne::calculer($depot, '2026-01-01', '2027-01-01', '12', 'AN', 365, 0), 1200000);
$verifier('3 % pour la duree (90 jours)', InteretsEpargne::calculer($depot, '2026-01-01', '2026-04-01', '3', 'DUREE', 90, 0), 300000);
$verifier('depot en cours de periode : il ne rapporte qu\'a partir de son jour', InteretsEpargne::calculer(array_merge($depot, [['jour' => '2026-02-15', 'montant' => 10000000]]), '2026-01-01', '2026-04-01', '3', 'DUREE', 90, 0), 450000);
$verifier('1 % par mois = 12 % par an', InteretsEpargne::calculer($depot, '2026-01-01', '2027-01-01', '1', 'MOIS', 365, 0), 1200000);
$verifier('versements mensuels : un par mois anniversaire', array_column(InteretsEpargne::periodes('2026-01-10', InteretsEpargne::echeance('2026-01-10', 3), 'MENSUEL'), 'au'), ['2026-02-10', '2026-03-10', '2026-04-10']);
$verifier('compte annuel : 372 parts, 12 de benefice', count(TypeCarnet::partsBenefice(372, 31, 1, 'DEBUT_TRANCHE')), 12);

echo $ko === 0 ? "Tous les calculs sont justes.\n" : "$ko echec(s).\n";
exit($ko === 0 ? 0 : 1);

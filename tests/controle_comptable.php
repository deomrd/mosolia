<?php
// Recoupe la comptabilite avec les donnees de gestion, pour tests/parcours.sh :
//   php tests/controle_comptable.php base.sqlite organisation_id
// Affiche : equilibre|caisse_compta=caisse_journal|depots=epargne_des_carnets|credits=capital_restant
// (chaque comparaison vaut OK ou les deux montants differents).
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotComptabilite;
use AvecXY\Noyau\BaseDeDonnees;

[, $fichier, $org] = $argv;
$org = (int) $org;
$bd = BaseDeDonnees::depuisConfig(['pilote' => 'sqlite', 'fichier' => $fichier]);
$compta = new DepotComptabilite($bd);
$totaux = $compta->totaux($org, null, '2999-12-31', null);
$debit = 0;
$credit = 0;
$parRole = [];
foreach ($compta->comptes($org, true) as $c) {
    $t = $totaux[$c['id']] ?? ['debit' => 0, 'credit' => 0];
    $debit += Montant::enCentimes($t['debit']);
    $credit += Montant::enCentimes($t['credit']);
    if ($c['cle'] !== null) {
        $parRole[$c['cle']] = Montant::enCentimes($t['debit']) - Montant::enCentimes($t['credit']);
    }
}
$cmp = static fn (int $a, int $b): string => $a === $b ? 'OK' : ($a / 100) . '<>' . ($b / 100);
$caisse = Montant::enCentimes((new DepotCaisse($bd))->solde($org));
$epargne = Montant::enCentimes($bd->valeur('SELECT COALESCE(SUM(epargne),0) FROM avecxy_carnets WHERE organisation_id = ? AND supprime_le IS NULL', [$org]));
$capital = 0;
foreach ($bd->tous("SELECT * FROM avecxy_credits WHERE organisation_id = ? AND supprime_le IS NULL AND statut <> 'ANNULE'", [$org]) as $cr) {
    $capital += \AvecXY\Application\ServiceIndicateurs::capitalRestant($cr, $bd->tous('SELECT * FROM avecxy_credit_echeances WHERE credit_id = ? ORDER BY numero, id', [$cr['id']]));
}
echo implode('|', [
    $debit === $credit ? 'EQUILIBRE' : 'DESEQUILIBRE ' . ($debit / 100) . '/' . ($credit / 100),
    'caisse ' . $cmp($parRole['caisse'] ?? 0, $caisse),
    'depots ' . $cmp(-($parRole['depots'] ?? 0), $epargne),
    'credits ' . $cmp($parRole['credits'] ?? 0, $capital),
]), "\n";

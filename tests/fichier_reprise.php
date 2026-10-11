<?php
// Fabrique un classeur de reprise rempli, comme le ferait une organisation, pour tests/parcours.sh.
//   php tests/fichier_reprise.php sortie.xlsx "RECOLTEUR" "TYPE DE CARNET" "REGLE DE CREDIT" [erreur]
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Infrastructure\Tableur;

[, $sortie, $recolteur, $type, $produit] = $argv;
$avecErreur = ($argv[5] ?? '') === 'erreur';
$serie = static fn (string $date): int => (int) round(strtotime($date . ' UTC') / 86400) + 25569;   // date facon Excel
$octroi = date('Y-m-d', strtotime('-20 day'));
$echeance = date('Y-m-d', strtotime('+10 day'));

$clients = [
    ['N', 'Nom', 'Tel', 'Recolteur', 'Type', 'Mise', 'Mises', 'Solde', 'Date', 'Deja', 'Tranches', 'Adresse', 'Activite'],
    [1, 'Kabila Rose', '0990000001', $recolteur, $type, 1000, 10, 9000, $serie(date('Y-m-d', strtotime('-12 day'))), 'OUI'],
    [2, 'Mutombo Jean', '0990000002', 'Sifa Nouvelle', $type, 2000, 4, '', date('d/m/Y', strtotime('-5 day')), 'NON'],
    [3, 'Sans Carnet', '', $recolteur],
];
if ($avecErreur) {
    $clients[] = [4, 'Erreur Type', '', $recolteur, 'TYPE QUI N EXISTE PAS', 1000, 2];
    $clients[] = [5, 'Erreur Recolteur', '', 'INCONNU'];
}
file_put_contents($sortie, Tableur::ecrire([
    ['nom' => 'Mode d\'emploi', 'lignes' => [['x']]],
    ['nom' => 'Récolteurs', 'lignes' => [['Nom', 'Tel'], [$recolteur, ''], ['Sifa Nouvelle', '0811111111']]],
    ['nom' => 'Clients et carnets', 'lignes' => $clients],
    ['nom' => 'Crédits en cours', 'lignes' => [
        ['N', 'Client', 'Regle', 'Montant', 'Taux', 'Octroi', 'Echeance', 'Deja', 'Reste'],
        [1, 1, $produit, 50000, 10, $serie($octroi), $serie($echeance), 20000],
    ]],
    ['nom' => 'Échéances', 'lignes' => [['N', 'Date', 'Montant', 'Payee'], [1, $serie($echeance), 55000, 'NON']]],
    ['nom' => 'Listes', 'lignes' => [['a']], 'cachee' => true],
]));

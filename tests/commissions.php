<?php
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Noyau\BaseDeDonnees;

$bd = BaseDeDonnees::depuisConfig(['pilote' => 'sqlite', 'fichier' => ':memory:']);
$bd->pdo->exec('CREATE TABLE avecxy_benefices (organisation_id INTEGER, agent_id INTEGER, periode TEXT, part_agent DECIMAL)');
$bd->pdo->exec('CREATE TABLE avecxy_paiements_agents (id INTEGER, organisation_id INTEGER, agent_id INTEGER, periode TEXT, part_agent DECIMAL, manquants_deduits DECIMAL, montant_paye DECIMAL, date_paiement TEXT, supprime_le TEXT)');
$bd->pdo->exec("INSERT INTO avecxy_benefices VALUES
    (1, 10, '2026-08', 100), (1, 10, '2026-08', 50),
    (1, 10, '2026-09', 200), (1, 10, '2026-10', 300),
    (1, 10, '2026-11', 400), (1, 10, '2026-12', 500),
    (1, 20, '2026-07', 999), (2, 10, '2026-06', 888)");
$bd->pdo->exec("INSERT INTO avecxy_paiements_agents VALUES
    (1, 1, 10, '2026-08', 140, 20, 120, '2026-09-01', NULL),
    (2, 1, 10, '2026-09', 200, 0, 200, '2026-10-01', '2026-10-02'),
    (3, 1, 10, '2026-05', 90, 0, 90, '2026-06-01', NULL),
    (4, 2, 10, '2026-10', 999, 0, 999, '2026-10-30', NULL)");
$depot = new DepotCaisse($bd);
$lignes = $depot->historiqueCommissions(1, 10, '2026-11');
$verifier = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$verifier(array_column($lignes, 'periode') === ['2026-10', '2026-09', '2026-08', '2026-05'], 'Mois passes, ordre et isolation organisation/collecteur');
$verifier($lignes[0]['paiement_id'] === null, 'Commission impayee');
$verifier($lignes[1]['paiement_id'] === null, 'Paiement annule ignore');
$verifier((int) $lignes[2]['paiement_id'] === 1 && (float) $lignes[2]['montant_paye'] === 120.0, 'Paiement et montant verse');
$verifier((float) $lignes[2]['part_agent'] === 140.0 && (float) $lignes[2]['manquants_deduits'] === 20.0, 'Montants historiques du paiement conserves');
$verifier($depot->historiqueCommissions(1, 0, '2026-11') === [], 'Compte sans collecteur');
$verifier($depot->historiqueCommissions(3, 10, '2026-11') === [], 'Organisation sans commissions');
echo "Historique de commissions : OK\n";

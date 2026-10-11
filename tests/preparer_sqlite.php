<?php
// Cree une base SQLite de test a partir du schema MySQL :  php tests/preparer_sqlite.php chemin.sqlite
// Sert uniquement aux essais locaux sans serveur MySQL. La production utilise MySQL/MariaDB.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Infrastructure\Migrateur;
use AvecXY\Noyau\BaseDeDonnees;

$racine = dirname(__DIR__);
$fichier = $argv[1] ?? $racine . '/stockage/test.sqlite';
@unlink($fichier);
$bd = BaseDeDonnees::depuisConfig(['pilote' => 'sqlite', 'fichier' => $fichier]);
$bd->pdo->exec('CREATE TABLE avecxy_migrations (fichier VARCHAR(190) NOT NULL PRIMARY KEY, applique_le DATETIME NOT NULL)');

$nb = 0;
foreach (Migrateur::instructions(file_get_contents($racine . '/database/migrations/0001_schema.sql')) as $sql) {
    if (!str_starts_with($sql, 'CREATE TABLE')) { continue; }           // SET NAMES, etc.
    $sql = preg_replace('/\)\s*ENGINE=.*$/s', ')', $sql);
    $lignes = [];
    foreach (explode("\n", $sql) as $ligne) {
        $t = trim($ligne);
        if (preg_match('/^(KEY|CONSTRAINT \w+ CHECK)/', $t)) { continue; }   // index simples et CHECK MySQL
        $ligne = preg_replace('/^(\s*)UNIQUE KEY \w+ (\(.*\))/', '$1UNIQUE $2', $ligne);
        $ligne = preg_replace('/\b(BIG)?INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $ligne);
        $ligne = preg_replace("/ENUM\\((?:'[^']*',?)+\\)/", 'TEXT', $ligne);
        $ligne = str_replace([' UNSIGNED', ' ON UPDATE CURRENT_TIMESTAMP'], '', $ligne);
        $lignes[] = $ligne;
    }
    $sql = preg_replace('/,\s*\n\)$/', "\n)", implode("\n", $lignes));
    $bd->pdo->exec($sql);
    $nb++;
}
$bd->inserer('avecxy_migrations', ['fichier' => '0001_schema.sql', 'applique_le' => $bd->maintenant()]);
$suite = (new Migrateur($bd, $racine . '/database/migrations'))->migrer();
echo "$nb tables creees ; migrations suivantes : " . implode(', ', $suite) . "\n";

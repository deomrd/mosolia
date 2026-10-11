<?php
// Cree un administrateur de la plateforme (vous, AvecXY) :
//   php bin/creer-admin-plateforme.php "NOM" email mot_de_passe
// Il se connecte ensuite sur /plateforme/connexion.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Noyau\Noyau;
use AvecXY\Noyau\Requete;

if ($argc < 4) {
    fwrite(STDERR, "Usage : php bin/creer-admin-plateforme.php \"NOM\" email mot_de_passe\n");
    exit(1);
}
$noyau = new Noyau(dirname(__DIR__), new Requete('CLI', '/', '', [], [], '', false));
try {
    $id = $noyau->servicePlateforme()->creerAdmin($argv[1], $argv[2], $argv[3]);
} catch (ErreurMetier $e) {
    fwrite(STDERR, 'Refuse : ' . t($e->cle, $e->valeurs) . "\n");
    exit(1);
}
echo "Administrateur de la plateforme cree (id $id). Connexion : /plateforme/connexion\n";

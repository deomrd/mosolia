<?php
// Ouvre une organisation et son premier administrateur :
//   php bin/creer-organisation.php "NOM" DEVISE "NOM ADMIN" identifiant mot_de_passe [FORFAIT] [fr|en]
// Exemple :
//   php bin/creer-organisation.php "AVEC Umoja" CDF "Jean Kasongo" jean@exemple.com "MotDePasse1" ESSAI fr
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Noyau\Noyau;
use AvecXY\Noyau\Requete;

if ($argc < 6) {
    fwrite(STDERR, "Usage : php bin/creer-organisation.php \"NOM\" DEVISE \"NOM ADMIN\" identifiant mot_de_passe [FORFAIT] [fr|en]\n");
    exit(1);
}
$noyau = new Noyau(dirname(__DIR__), new Requete('CLI', '/', '', [], [], '', false));
try {
    $r = $noyau->serviceOrganisation()->ouvrir($argv[1], strtoupper($argv[2]), $argv[3], $argv[4], $argv[5], strtoupper($argv[6] ?? 'ESSAI'), $argv[7] ?? 'fr');
} catch (ErreurMetier $e) {
    fwrite(STDERR, 'Refuse : ' . t($e->cle, $e->valeurs) . "\n");
    exit(1);
}
echo "Organisation creee (id {$r['organisation_id']}). L'administrateur devra changer son mot de passe a la premiere connexion.\n";

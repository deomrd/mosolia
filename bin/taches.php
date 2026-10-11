<?php
// Travaux automatiques pour toutes les organisations (facultatif) :
//   php bin/taches.php
// L'application les fait deja toute seule a la premiere page ouverte chaque jour.
// Ce script sert si l'on prefere une tache planifiee (cron) une fois par nuit.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Noyau\Noyau;
use AvecXY\Noyau\Requete;

$noyau = new Noyau(dirname(__DIR__), new Requete('GET', '/', '', [], [], '', false));
$organisations = $noyau->bd()->tous(
    "SELECT o.id, o.nom, d.decimales FROM avecxy_organisations o JOIN avecxy_devises d ON d.code = o.devise_code WHERE o.statut <> 'SUSPENDUE'"
);
foreach ($organisations as $o) {
    try {
        $fait = $noyau->serviceTaches()->duJour((int) $o['id'], (int) $o['decimales']);
        echo $o['nom'], ' : ', $fait ? 'fait' : 'deja fait aujourd\'hui', PHP_EOL;
    } catch (Throwable $e) {
        echo $o['nom'], ' : ERREUR ', $e->getMessage(), PHP_EOL;
    }
}

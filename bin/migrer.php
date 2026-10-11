<?php
// Met la base a jour :  php bin/migrer.php
// (La meme chose se fait sans terminal : page /installation, puis Espace plateforme > Reglages.)
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/src/autoload.php';

use AvecXY\Application\ServiceInstallation;
use AvecXY\Domaine\ErreurMetier;

try {
    $appliques = (new ServiceInstallation(dirname(__DIR__)))->migrer();
} catch (ErreurMetier $e) {
    fwrite(STDERR, 'Echec : ' . ($e->valeurs['detail'] ?? $e->cle) . "\n");
    exit(1);
}
echo $appliques ? "Migrations appliquees :\n  - " . implode("\n  - ", $appliques) . "\n" : "La base est deja a jour.\n";

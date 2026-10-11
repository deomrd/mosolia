<?php
// Application mobile des recolteurs : nom de l'appareil connecte (pour le reconnaitre et le desactiver).

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $bd->pdo->exec('ALTER TABLE avecxy_api_jetons ADD COLUMN appareil_nom VARCHAR(120) NULL');
};

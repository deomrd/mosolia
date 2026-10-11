<?php
// Nom court de l'organisation (sert aux identifiants du personnel : marie@tuungane)
// et origine des demandes de credit (personnel ou client depuis son espace).

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $bd->pdo->exec('ALTER TABLE avecxy_organisations ADD COLUMN nom_court VARCHAR(12) NULL');
    $bd->pdo->exec('CREATE UNIQUE INDEX uniq_org_nom_court ON avecxy_organisations (nom_court)');
    $bd->pdo->exec("ALTER TABLE avecxy_credit_eligibilites ADD COLUMN origine VARCHAR(10) NOT NULL DEFAULT 'PERSONNEL'");
};

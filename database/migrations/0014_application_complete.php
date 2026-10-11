<?php
// Application mobile complete : un « passage » a usage unique ouvre le site dans l'application
// (gestion pour le personnel, espace client pour les clients) sans redemander le mot de passe.

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    // type : PERSONNEL (utilisateur_id) ou CLIENT (compte de l'espace client) ; valable 2 minutes, une seule fois
    $bd->pdo->exec("CREATE TABLE avecxy_passages (
        id $id,
        code_hash CHAR(64) NOT NULL,
        type VARCHAR(10) NOT NULL,
        organisation_id $entier NOT NULL,
        utilisateur_id $entier NULL,
        compte_id $entier NULL,
        cible VARCHAR(190) NOT NULL DEFAULT '/',
        expire_le DATETIME NOT NULL,
        utilise_le DATETIME NULL,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_passage_code ON avecxy_passages (code_hash)');
};

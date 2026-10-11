<?php
// Regle de credit : remboursement libre ou selon un calendrier (nombre d'echeances, intervalle en jours).
// Le calendrier de chaque credit est cree a l'octroi.

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $bd->pdo->exec("ALTER TABLE avecxy_credit_produits ADD COLUMN mode_remboursement VARCHAR(12) NOT NULL DEFAULT 'LIBRE'");
    $bd->pdo->exec('ALTER TABLE avecxy_credit_produits ADD COLUMN echeances_nombre INT NOT NULL DEFAULT 1');
    $bd->pdo->exec('ALTER TABLE avecxy_credit_produits ADD COLUMN echeances_intervalle INT NOT NULL DEFAULT 30');
    $bd->pdo->exec('CREATE TABLE avecxy_credit_echeances (
        id ' . ($mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
        organisation_id ' . ($mysql ? 'INT UNSIGNED' : 'INTEGER') . ' NOT NULL,
        credit_id ' . ($mysql ? 'INT UNSIGNED' : 'INTEGER') . ' NOT NULL,
        numero INT NOT NULL,
        date_prevue DATE NOT NULL,
        montant DECIMAL(16,2) NOT NULL,
        cree_le DATETIME NOT NULL
    )' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
    $bd->pdo->exec('CREATE INDEX idx_echeance_credit ON avecxy_credit_echeances (credit_id, numero)');
};

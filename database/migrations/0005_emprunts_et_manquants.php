<?php
// Emprunts de l'organisation (avec ou sans interet), leurs remboursements,
// et reglement des manquants par les recolteurs.

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $corbeille = "supprime_le DATETIME NULL, supprime_par $entier NULL, motif_suppression VARCHAR(255) NULL, cree_le DATETIME NOT NULL";

    $bd->pdo->exec("CREATE TABLE avecxy_emprunts (
        id $id,
        organisation_id $entier NOT NULL,
        date_emprunt DATE NOT NULL,
        preteur VARCHAR(160) NOT NULL,
        montant DECIMAL(16,2) NOT NULL,
        taux_interet DECIMAL(5,2) NOT NULL DEFAULT 0,
        montant_interet DECIMAL(16,2) NOT NULL DEFAULT 0,
        total_du DECIMAL(16,2) NOT NULL,
        date_echeance DATE NULL,
        commentaire TEXT NULL,
        cree_par $entier NULL,
        $corbeille
    )$fin");
    $bd->pdo->exec("CREATE TABLE avecxy_emprunt_remboursements (
        id $id,
        organisation_id $entier NOT NULL,
        emprunt_id $entier NOT NULL,
        date_paiement DATE NOT NULL,
        montant DECIMAL(16,2) NOT NULL,
        commentaire TEXT NULL,
        cree_par $entier NULL,
        $corbeille
    )$fin");
    $bd->pdo->exec("CREATE TABLE avecxy_manquant_reglements (
        id $id,
        organisation_id $entier NOT NULL,
        agent_id $entier NOT NULL,
        date_paiement DATE NOT NULL,
        montant DECIMAL(16,2) NOT NULL,
        commentaire TEXT NULL,
        cree_par $entier NULL,
        $corbeille
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_emprunt_org ON avecxy_emprunts (organisation_id)');
    $bd->pdo->exec('CREATE INDEX idx_emprunt_remb ON avecxy_emprunt_remboursements (emprunt_id)');
    $bd->pdo->exec('CREATE INDEX idx_manquant_regl ON avecxy_manquant_reglements (organisation_id, agent_id)');
};

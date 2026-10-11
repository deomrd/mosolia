<?php
// Controle des ventes de carnets, historique des controles, demandes de credit en corbeille,
// PIN des utilisateurs et connexion par empreinte ou visage (cles de l'appareil).

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    // une vente de carnet attend le controle tant que validation = EN_ATTENTE
    $bd->pdo->exec("ALTER TABLE avecxy_carnets ADD COLUMN validation VARCHAR(12) NOT NULL DEFAULT 'VALIDE'");
    // une demande de credit refusee part a la corbeille
    $bd->pdo->exec('ALTER TABLE avecxy_credit_eligibilites ADD COLUMN supprime_le DATETIME NULL');
    $bd->pdo->exec("ALTER TABLE avecxy_credit_eligibilites ADD COLUMN supprime_par $entier NULL");
    $bd->pdo->exec('ALTER TABLE avecxy_credit_eligibilites ADD COLUMN motif_suppression VARCHAR(255) NULL');
    // PIN (hache) : connexion rapide sur le web, connexion principale sur mobile
    $bd->pdo->exec('ALTER TABLE avecxy_utilisateurs ADD COLUMN pin VARCHAR(255) NULL');

    $bd->pdo->exec("CREATE TABLE avecxy_controles (
        id $id,
        organisation_id $entier NOT NULL,
        agent_id $entier NOT NULL,
        type VARCHAR(12) NOT NULL,
        valides INT NOT NULL DEFAULT 0,
        rejetes INT NOT NULL DEFAULT 0,
        montant_valide DECIMAL(16,2) NOT NULL DEFAULT 0,
        montant_rejete DECIMAL(16,2) NOT NULL DEFAULT 0,
        montant_depose DECIMAL(16,2) NULL,
        manquant DECIMAL(16,2) NOT NULL DEFAULT 0,
        excedent DECIMAL(16,2) NOT NULL DEFAULT 0,
        remise_id $entier NULL,
        fait_par $entier NULL,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_controle_org ON avecxy_controles (organisation_id, cree_le)');

    // cles enregistrees par l'appareil de l'utilisateur (empreinte, visage) : norme WebAuthn
    $bd->pdo->exec("CREATE TABLE avecxy_utilisateur_cles (
        id $id,
        utilisateur_id $entier NOT NULL,
        identifiant_cle VARCHAR(255) NOT NULL,
        cle_publique TEXT NOT NULL,
        compteur BIGINT NOT NULL DEFAULT 0,
        nom VARCHAR(120) NULL,
        derniere_utilisation DATETIME NULL,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE UNIQUE INDEX uniq_cle ON avecxy_utilisateur_cles (identifiant_cle)');
    $bd->pdo->exec('CREATE INDEX idx_cle_utilisateur ON avecxy_utilisateur_cles (utilisateur_id)');
};

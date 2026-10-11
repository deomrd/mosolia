<?php
// Notifications (personnel, espace client, plateforme), reprise de l'existant (carnets et credits
// commences sur papier), droit d'annuler un retrait.

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    // destinataire : UTILISATEUR (personnel), CLIENT (espace client) ou PLATEFORME (tous les administrateurs AvecXY)
    $bd->pdo->exec("CREATE TABLE avecxy_notifications (
        id $id,
        organisation_id $entier NULL,
        destinataire VARCHAR(12) NOT NULL,
        destinataire_id $entier NOT NULL DEFAULT 0,
        type VARCHAR(40) NOT NULL,
        donnees TEXT NULL,
        lien VARCHAR(255) NULL,
        lu_le DATETIME NULL,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_notif_dest ON avecxy_notifications (destinataire, destinataire_id, lu_le)');

    // une reprise = l'import de la situation tenue sur papier avant l'application
    $bd->pdo->exec("CREATE TABLE avecxy_reprises (
        id $id,
        organisation_id $entier NOT NULL,
        date_demarrage DATE NOT NULL,
        statut VARCHAR(12) NOT NULL DEFAULT 'VALIDEE',
        recolteurs INT NOT NULL DEFAULT 0,
        clients INT NOT NULL DEFAULT 0,
        carnets INT NOT NULL DEFAULT 0,
        credits INT NOT NULL DEFAULT 0,
        epargne DECIMAL(16,2) NOT NULL DEFAULT 0,
        encours DECIMAL(16,2) NOT NULL DEFAULT 0,
        caisse DECIMAL(16,2) NOT NULL DEFAULT 0,
        fait_par $entier NULL,
        cree_le DATETIME NOT NULL,
        annulee_le DATETIME NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_reprise_org ON avecxy_reprises (organisation_id)');

    // comment l'organisation a demarre : NULL = pas encore choisi, VIDE ou REPRISE
    $bd->pdo->exec('ALTER TABLE avecxy_organisations ADD COLUMN demarrage VARCHAR(12) NULL');
    $bd->pdo->exec("UPDATE avecxy_organisations SET demarrage = 'VIDE' WHERE id IN (SELECT organisation_id FROM avecxy_clients)");

    foreach (['avecxy_agents', 'avecxy_clients', 'avecxy_carnets', 'avecxy_credits', 'avecxy_mouvements_fonds'] as $table) {
        $bd->pdo->exec("ALTER TABLE $table ADD COLUMN reprise_id $entier NULL");
    }
    // carnet repris : ce qui a ete verse et retire avant l'application ne passe ni par la caisse ni par le controle
    $bd->pdo->exec('ALTER TABLE avecxy_carnets ADD COLUMN reprise_parts INT NOT NULL DEFAULT 0');
    $bd->pdo->exec('ALTER TABLE avecxy_carnets ADD COLUMN reprise_montant DECIMAL(16,2) NOT NULL DEFAULT 0');
    $bd->pdo->exec('ALTER TABLE avecxy_carnets ADD COLUMN reprise_retire DECIMAL(16,2) NOT NULL DEFAULT 0');
    $bd->pdo->exec('ALTER TABLE avecxy_carnets ADD COLUMN reprise_tranches INT NOT NULL DEFAULT 0');
    // 1 = l'organisation a deja pris son benefice sur papier : il n'est pas repartage
    $bd->pdo->exec('ALTER TABLE avecxy_carnets ADD COLUMN benefice_deja_pris INT NOT NULL DEFAULT 0');
    // credit repris : ce qui a ete rembourse avant l'application (interets deja partages sur papier)
    $bd->pdo->exec('ALTER TABLE avecxy_credits ADD COLUMN reprise_rembourse DECIMAL(16,2) NOT NULL DEFAULT 0');

    // nouveau droit : annuler un retrait (ce qui rouvre le carnet). Donne aux profils qui pouvaient deja le faire.
    $existe = (int) $bd->valeur("SELECT COUNT(*) FROM avecxy_permissions WHERE code = 'retraits.annuler'");
    if ($existe === 0) {
        $modele = $bd->un("SELECT * FROM avecxy_permissions WHERE code = 'retraits.faire'");
        if ($modele !== null) {
            $nouveau = $bd->inserer('avecxy_permissions', ['code' => 'retraits.annuler', 'module' => $modele['module'], 'libelle' => 'retraits.annuler']);
            $bd->pdo->exec("INSERT INTO avecxy_role_permissions (role_id, permission_id)
                SELECT rp.role_id, $nouveau FROM avecxy_role_permissions rp JOIN avecxy_permissions p ON p.id = rp.permission_id
                WHERE p.code = 'cotisations.corriger' AND rp.role_id IN (
                    SELECT rp2.role_id FROM avecxy_role_permissions rp2 JOIN avecxy_permissions p2 ON p2.id = rp2.permission_id WHERE p2.code = 'retraits.faire')");
        }
    }
};

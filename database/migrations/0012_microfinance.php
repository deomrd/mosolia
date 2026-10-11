<?php
// Microfinance : comptes bloques (interets verses par l'organisation), solde minimum des comptes libres,
// reserve de parts (compte annuel), carnets sans duree ; credits a taux degressif, frais de dossier, garantie.

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $ajouter = static function (string $table, string $colonne) use ($bd): void {
        $bd->pdo->exec("ALTER TABLE $table ADD COLUMN $colonne");
    };

    // --- produits d'epargne (types de carnet) et carnets ---
    // blocage : NULL = compte ordinaire ; RETRAITS = seuls les retraits sont bloques ; TOUT = ni depot ni retrait pendant la periode
    foreach (['avecxy_carnet_types', 'avecxy_carnets'] as $table) {
        $ajouter($table, 'blocage VARCHAR(10) NULL');
        $ajouter($table, "taux_base VARCHAR(6) NOT NULL DEFAULT 'AN'");               // AN, MOIS ou DUREE
        $ajouter($table, "interets_versement VARCHAR(8) NOT NULL DEFAULT 'FIN'");     // FIN ou MENSUEL
        $ajouter($table, "retrait_anticipe VARCHAR(16) NOT NULL DEFAULT 'INTERDIT'"); // INTERDIT, PENALITE, PERTE_INTERETS
        $ajouter($table, 'penalite_anticipe DECIMAL(6,2) NOT NULL DEFAULT 0');
        $ajouter($table, 'solde_minimum DECIMAL(16,2) NULL');
        $ajouter($table, 'parts_reserve INT NULL');
    }
    // durees proposees au client et taux de chacune : [{"mois":6,"taux":"3.00"}, ...]
    $ajouter('avecxy_carnet_types', 'options_blocage TEXT NULL');
    $ajouter('avecxy_carnets', 'duree_mois INT NULL');
    $ajouter('avecxy_carnets', 'taux_interet DECIMAL(6,2) NULL');
    $ajouter('avecxy_carnets', 'total_interets DECIMAL(16,2) NOT NULL DEFAULT 0');

    // interets verses sur un compte bloque : ils s'ajoutent a l'epargne du client
    $bd->pdo->exec("CREATE TABLE avecxy_interets_epargne (
        id $id,
        organisation_id $entier NOT NULL,
        carnet_id $entier NOT NULL,
        client_id $entier NOT NULL,
        agent_id $entier NOT NULL,
        date_interet DATE NOT NULL,
        periode VARCHAR(10) NOT NULL,
        du_jour DATE NOT NULL,
        au_jour DATE NOT NULL,
        montant DECIMAL(16,2) NOT NULL,
        supprime_le DATETIME NULL,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_interet_carnet ON avecxy_interets_epargne (carnet_id, periode)');

    // --- produits de credit et credits ---
    $ajouter('avecxy_credit_produits', "mode_calcul VARCHAR(20) NOT NULL DEFAULT 'FIXE'");          // FIXE, DEGRESSIF, DEGRESSIF_CAPITAL
    $ajouter('avecxy_credit_produits', "echeances_unite VARCHAR(8) NOT NULL DEFAULT 'JOUR'");       // JOUR, SEMAINE, MOIS
    $ajouter('avecxy_credit_produits', "frais_dossier_type VARCHAR(12) NOT NULL DEFAULT 'AUCUN'");  // AUCUN, FIXE, POURCENTAGE
    $ajouter('avecxy_credit_produits', 'frais_dossier_valeur DECIMAL(16,2) NOT NULL DEFAULT 0');
    $ajouter('avecxy_credit_produits', "frais_dossier_mode VARCHAR(8) NOT NULL DEFAULT 'RETENU'");  // RETENU (deduit du pret) ou AJOUTE (a rembourser)
    $ajouter('avecxy_credit_produits', 'garantie_pct DECIMAL(6,2) NULL');
    $ajouter('avecxy_credits', "mode_calcul VARCHAR(20) NOT NULL DEFAULT 'FIXE'");
    $ajouter('avecxy_credits', 'frais_dossier DECIMAL(16,2) NOT NULL DEFAULT 0');
    $ajouter('avecxy_credits', "frais_dossier_mode VARCHAR(8) NOT NULL DEFAULT 'RETENU'");
    $ajouter('avecxy_credits', 'garantie DECIMAL(16,2) NOT NULL DEFAULT 0');
    $ajouter('avecxy_credit_echeances', 'capital DECIMAL(16,2) NULL');
    $ajouter('avecxy_credit_echeances', 'interet DECIMAL(16,2) NULL');

    if ($mysql) {
        // nouvelles valeurs : le carnet libre peut devenir un compte bloque, les benefices ont deux nouvelles sources
        $bd->pdo->exec("ALTER TABLE avecxy_benefices MODIFY source VARCHAR(20) NOT NULL");
        $bd->pdo->exec("ALTER TABLE avecxy_frais_tenue MODIFY origine VARCHAR(12) NOT NULL");
        $bd->pdo->exec("ALTER TABLE avecxy_regles_partage MODIFY source VARCHAR(20) NOT NULL");
        // un carnet sans duree n'a pas de date d'echeance
        $bd->pdo->exec('ALTER TABLE avecxy_carnet_types MODIFY duree_jours INT UNSIGNED NOT NULL DEFAULT 0');
    }
};

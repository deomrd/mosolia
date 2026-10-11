<?php
// Microfinance (suite) : dossier client complet, renouvellement des comptes bloques, agences avec caisse
// separee et transferts de fonds, comptabilite generale (plan de comptes, ecritures manuelles).

use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    $mysql = $bd->pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $grandId = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $entier = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $corbeille = "supprime_le DATETIME NULL, supprime_par $entier NULL, motif_suppression VARCHAR(255) NULL";
    $ajouter = static fn (string $table, string $colonne) => $bd->pdo->exec("ALTER TABLE $table ADD COLUMN $colonne");

    // --- dossier client ---
    $ajouter('avecxy_clients', 'sexe VARCHAR(1) NULL');
    $ajouter('avecxy_clients', 'date_naissance DATE NULL');
    $ajouter('avecxy_clients', 'lieu_naissance VARCHAR(120) NULL');
    $ajouter('avecxy_clients', 'piece_type VARCHAR(20) NULL');            // CARTE_ELECTEUR, PASSEPORT, PERMIS, CARTE_IDENTITE, AUTRE
    $ajouter('avecxy_clients', 'piece_numero VARCHAR(60) NULL');
    $ajouter('avecxy_clients', 'piece_expire_le DATE NULL');
    $ajouter('avecxy_clients', 'photo VARCHAR(120) NULL');                // fichier dans stockage/photos
    $ajouter('avecxy_clients', 'contact_urgence VARCHAR(160) NULL');

    // --- comptes bloques : renouvellement a l'echeance ---
    foreach (['avecxy_carnet_types', 'avecxy_carnets'] as $table) {
        $ajouter($table, "renouvellement VARCHAR(10) NOT NULL DEFAULT 'AUCUN'");   // AUCUN ou AUTOMATIQUE
    }
    $ajouter('avecxy_carnets', 'debut_blocage DATE NULL');                // debut de la periode en cours (NULL = ouverture)
    $ajouter('avecxy_carnets', 'cycle INT NOT NULL DEFAULT 1');

    // --- agences ---
    $bd->pdo->exec("CREATE TABLE avecxy_agences (
        id $id,
        organisation_id $entier NOT NULL,
        code VARCHAR(10) NOT NULL,
        nom VARCHAR(120) NOT NULL,
        adresse VARCHAR(255) NULL,
        telephone VARCHAR(40) NULL,
        actif INT NOT NULL DEFAULT 1,
        $corbeille,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_agence_org ON avecxy_agences (organisation_id)');
    // NULL = siege (ou organisation sans agence)
    foreach (['avecxy_agents', 'avecxy_utilisateurs', 'avecxy_mouvements_fonds', 'avecxy_emprunts'] as $table) {
        $ajouter($table, "agence_id $entier NULL");
    }
    $bd->pdo->exec("CREATE TABLE avecxy_transferts_fonds (
        id $grandId,
        organisation_id $entier NOT NULL,
        date_transfert DATE NOT NULL,
        de_agence_id $entier NULL,
        vers_agence_id $entier NULL,
        montant DECIMAL(16,2) NOT NULL,
        motif VARCHAR(255) NULL,
        cree_par $entier NULL,
        $corbeille,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_transfert_org ON avecxy_transferts_fonds (organisation_id, date_transfert)');

    // epargne d'un carnet repris au jour de la reprise (ecriture d'ouverture en comptabilite)
    $ajouter('avecxy_carnets', 'reprise_epargne DECIMAL(16,2) NOT NULL DEFAULT 0');
    $bd->pdo->exec("UPDATE avecxy_carnets SET reprise_epargne = epargne
        - (SELECT COALESCE(SUM(o.montant_epargne),0) FROM avecxy_cotisations o WHERE o.carnet_id = avecxy_carnets.id AND o.statut = 'VALIDE' AND o.supprime_le IS NULL)
        + (SELECT COALESCE(SUM(r.montant_retire),0) FROM avecxy_retraits r WHERE r.carnet_id = avecxy_carnets.id AND r.statut = 'VALIDE' AND r.supprime_le IS NULL)
        + (SELECT COALESCE(SUM(f.montant),0) FROM avecxy_frais_tenue f WHERE f.carnet_id = avecxy_carnets.id AND f.supprime_le IS NULL)
        - (SELECT COALESCE(SUM(i.montant),0) FROM avecxy_interets_epargne i WHERE i.carnet_id = avecxy_carnets.id AND i.supprime_le IS NULL)
        WHERE reprise_id IS NOT NULL");

    // --- comptabilite ---
    // nature : ACTIF, PASSIF, CHARGE, PRODUIT ; cle : role du compte dans les ecritures automatiques (caisse, depots...)
    $bd->pdo->exec("CREATE TABLE avecxy_compta_comptes (
        id $id,
        organisation_id $entier NOT NULL,
        numero VARCHAR(20) NOT NULL,
        libelle VARCHAR(160) NOT NULL,
        nature VARCHAR(8) NOT NULL,
        cle VARCHAR(30) NULL,
        actif INT NOT NULL DEFAULT 1,
        $corbeille,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_compte_org ON avecxy_compta_comptes (organisation_id, numero)');
    // ecritures saisies a la main (loyer, salaires, achats, corrections) ; les autres sont calculees des operations
    $bd->pdo->exec("CREATE TABLE avecxy_ecritures (
        id $grandId,
        organisation_id $entier NOT NULL,
        date_ecriture DATE NOT NULL,
        piece VARCHAR(40) NULL,
        libelle VARCHAR(255) NOT NULL,
        agence_id $entier NULL,
        cree_par $entier NULL,
        $corbeille,
        cree_le DATETIME NOT NULL
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_ecriture_org ON avecxy_ecritures (organisation_id, date_ecriture)');
    $bd->pdo->exec("CREATE TABLE avecxy_ecriture_lignes (
        id $grandId,
        ecriture_id $entier NOT NULL,
        compte_id $entier NOT NULL,
        debit DECIMAL(16,2) NOT NULL DEFAULT 0,
        credit DECIMAL(16,2) NOT NULL DEFAULT 0
    )$fin");
    $bd->pdo->exec('CREATE INDEX idx_ligne_ecriture ON avecxy_ecriture_lignes (ecriture_id)');

    // nouveaux droits, donnes aux profils qui avaient deja le droit voisin
    $donner = static function (string $code, string $module, string $voisin) use ($bd): void {
        $id = $bd->valeur('SELECT id FROM avecxy_permissions WHERE code = ?', [$code]);
        if ($id === null) {
            $id = $bd->inserer('avecxy_permissions', ['code' => $code, 'module' => $module, 'libelle' => $code]);
        }
        $bd->executer('INSERT INTO avecxy_role_permissions (role_id, permission_id)
            SELECT rp.role_id, ? FROM avecxy_role_permissions rp JOIN avecxy_permissions p ON p.id = rp.permission_id
            WHERE p.code = ? AND rp.role_id NOT IN (SELECT rp2.role_id FROM avecxy_role_permissions rp2 WHERE rp2.permission_id = ?)', [$id, $voisin, $id]);
    };
    $donner('agences.gerer', 'parametres', 'recolteurs.gerer');
    $donner('caisse.transferts', 'caisse', 'caisse.mouvements');
    $donner('compta.voir', 'comptabilite', 'montants.benefices');
    $donner('compta.saisir', 'comptabilite', 'caisse.mouvements');
    $donner('compta.plan', 'comptabilite', 'parametres.organisation');
    $donner('rapports.gestion', 'rapports', 'rapports.voir');
};

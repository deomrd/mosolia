-- =====================================================================
-- AvecXY - schema v2 (MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4)
-- Multi-organisation : toute table metier porte organisation_id.
-- Regles : montants en DECIMAL(16,2) ; supprimer = envoyer a la corbeille
-- (supprime_le), restaurable, trace dans le journal d'audit ; tout parametre
-- metier vit en base (aucun taux ni plafond dans le code).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. PLATEFORME (toi, super-admin)
-- ---------------------------------------------------------------------
-- (la table avecxy_migrations est creee par bin/migrer.php)

CREATE TABLE avecxy_plateforme_admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  statut ENUM('ACTIF','INACTIF') NOT NULL DEFAULT 'ACTIF',
  derniere_connexion DATETIME NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglages globaux : nom du produit, couleurs par defaut, delai de grace,
-- duree d'essai, regles de mot de passe, limites de connexion...
CREATE TABLE avecxy_plateforme_parametres (
  cle VARCHAR(80) PRIMARY KEY,
  valeur TEXT NOT NULL,
  type ENUM('TEXTE','ENTIER','DECIMAL','BOOLEEN','JSON') NOT NULL DEFAULT 'TEXTE',
  libelle VARCHAR(160) NULL,
  modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_devises (
  code CHAR(3) PRIMARY KEY,               -- CDF, USD, RWF...
  nom VARCHAR(60) NOT NULL,
  symbole VARCHAR(8) NOT NULL,            -- FC, $, FRw
  decimales TINYINT UNSIGNED NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forfaits fixes, definis par toi.
CREATE TABLE avecxy_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  nom VARCHAR(80) NOT NULL,
  description TEXT NULL,
  prix DECIMAL(16,2) NOT NULL,
  devise_code CHAR(3) NOT NULL,
  duree_jours INT UNSIGNED NOT NULL DEFAULT 30,
  max_agents INT UNSIGNED NULL,           -- NULL = illimite
  max_clients INT UNSIGNED NULL,
  max_utilisateurs INT UNSIGNED NULL,
  modules JSON NULL,                      -- ex. {"credit":true,"espace_client":true}
  ordre INT NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_plans_devise FOREIGN KEY (devise_code) REFERENCES avecxy_devises(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_organisations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,       -- saisi a la connexion (adresse unique)
  nom VARCHAR(160) NOT NULL,
  devise_code CHAR(3) NOT NULL,           -- une seule devise par caisse
  fuseau VARCHAR(60) NOT NULL DEFAULT 'Africa/Lubumbashi',
  langue ENUM('fr','en') NOT NULL DEFAULT 'fr',
  ville_defaut VARCHAR(80) NULL,
  telephone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  adresse TEXT NULL,
  logo VARCHAR(255) NULL,
  couleur_principale CHAR(7) NULL,
  modules_actifs JSON NULL,               -- modules du forfait que l'organisation a actives
  statut ENUM('ESSAI','ACTIVE','LECTURE_SEULE','SUSPENDUE') NOT NULL DEFAULT 'ESSAI',
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_devise FOREIGN KEY (devise_code) REFERENCES avecxy_devises(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_abonnements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  statut ENUM('ESSAI','ACTIF','GRACE','EXPIRE','ANNULE') NOT NULL DEFAULT 'ESSAI',
  debut DATE NOT NULL,
  fin DATE NOT NULL,
  fin_grace DATE NULL,
  renouvellement_auto TINYINT(1) NOT NULL DEFAULT 0,   -- reserve automatisation
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_abo_org (organisation_id, fin),
  CONSTRAINT fk_abo_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_abo_plan FOREIGN KEY (plan_id) REFERENCES avecxy_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paiements d'abonnement : manuels aujourd'hui, colonnes prestataire_*
-- reservees pour l'automatisation (Mobile Money, agregateur).
CREATE TABLE avecxy_abonnement_paiements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  abonnement_id INT UNSIGNED NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  devise_code CHAR(3) NOT NULL,
  mode ENUM('MANUEL','AUTOMATIQUE') NOT NULL DEFAULT 'MANUEL',
  moyen VARCHAR(40) NULL,                 -- especes, mobile money, virement
  prestataire VARCHAR(40) NULL,
  prestataire_reference VARCHAR(120) NULL,
  prestataire_reponse JSON NULL,
  statut ENUM('EN_ATTENTE','CONFIRME','ECHEC','REMBOURSE') NOT NULL DEFAULT 'EN_ATTENTE',
  paye_le DATETIME NULL,
  enregistre_par INT UNSIGNED NULL,       -- avecxy_plateforme_admins.id
  note TEXT NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_prestataire_ref (prestataire, prestataire_reference),
  KEY idx_abopay_org (organisation_id),
  CONSTRAINT fk_abopay_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_abopay_abo FOREIGN KEY (abonnement_id) REFERENCES avecxy_abonnements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. ACCES ET REGLAGES PAR ORGANISATION
-- ---------------------------------------------------------------------
-- Catalogue global des permissions (ex. cotisation.valider, credit.accorder).
CREATE TABLE avecxy_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  module VARCHAR(40) NOT NULL,
  libelle VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profils (roles) crees librement par l'admin de chaque organisation ;
-- un jeu de profils modeles est propose a l'ouverture du compte.
CREATE TABLE avecxy_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  nom VARCHAR(80) NOT NULL,
  est_admin TINYINT(1) NOT NULL DEFAULT 0,   -- a toutes les permissions
  est_agent TINYINT(1) NOT NULL DEFAULT 0,   -- limite a son portefeuille
  systeme TINYINT(1) NOT NULL DEFAULT 0,     -- non supprimable
  UNIQUE KEY uniq_role (organisation_id, code),
  CONSTRAINT fk_role_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES avecxy_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES avecxy_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_agents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  code CHAR(4) NOT NULL,                  -- 4 caracteres alphanumeriques, unique sur TOUTE la plateforme (ex. JM4K)
  nom VARCHAR(160) NOT NULL,
  telephone VARCHAR(40) NULL,
  adresse TEXT NULL,
  dernier_numero_client INT UNSIGNED NOT NULL DEFAULT 0,  -- compteur des codes clients JM4K-1, JM4K-2...
  statut ENUM('ACTIF','INACTIF') NOT NULL DEFAULT 'ACTIF',
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_agent_code (code),      -- global : il suffit a retrouver l'organisation
  KEY idx_agent_org (organisation_id),
  CONSTRAINT chk_agent_code CHECK (code REGEXP '^[A-Z0-9]{4}$'),
  CONSTRAINT fk_agent_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_utilisateurs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NULL,             -- fiche agent liee (uniquement si voulu)
  nom VARCHAR(160) NOT NULL,
  identifiant VARCHAR(160) NOT NULL,      -- e-mail ou telephone, unique sur toute la plateforme (connexion sans code d'organisation)
  email VARCHAR(160) NULL,
  telephone VARCHAR(40) NULL,
  mot_de_passe VARCHAR(255) NOT NULL,
  langue ENUM('fr','en') NULL,             -- NULL = langue de l'organisation
  doit_changer_mot_de_passe TINYINT(1) NOT NULL DEFAULT 1,
  statut ENUM('ACTIF','INACTIF') NOT NULL DEFAULT 'ACTIF',
  derniere_connexion DATETIME NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_identifiant (identifiant),
  KEY idx_user_org (organisation_id),
  KEY idx_user_agent (agent_id),
  CONSTRAINT fk_user_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES avecxy_roles(id),
  CONSTRAINT fk_user_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regles propres a un utilisateur : elles s'ajoutent a son profil (role)
-- ou lui retirent un droit. Un REFUSE l'emporte toujours sur le profil.
CREATE TABLE avecxy_utilisateur_permissions (
  utilisateur_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  effet ENUM('ACCORDE','REFUSE') NOT NULL,
  defini_par INT UNSIGNED NULL,
  defini_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (utilisateur_id, permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (utilisateur_id) REFERENCES avecxy_utilisateurs(id) ON DELETE CASCADE,
  CONSTRAINT fk_up_perm FOREIGN KEY (permission_id) REFERENCES avecxy_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_connexion_tentatives (
  cle CHAR(64) PRIMARY KEY,               -- sha256(ip|organisation|identifiant)
  nombre INT UNSIGNED NOT NULL,
  debut_fenetre INT UNSIGNED NOT NULL,
  bloque_jusqua INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de la PWA et de la future app Android.
CREATE TABLE avecxy_api_jetons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  utilisateur_id INT UNSIGNED NOT NULL,
  appareil_id VARCHAR(128) NOT NULL,
  jeton_hash CHAR(64) NOT NULL UNIQUE,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vu_le DATETIME NULL,
  expire_le DATETIME NOT NULL,
  revoque_le DATETIME NULL,
  KEY idx_jeton_user (utilisateur_id, appareil_id),
  CONSTRAINT fk_jeton_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_jeton_user FOREIGN KEY (utilisateur_id) REFERENCES avecxy_utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'idempotence des operations saisies hors ligne (remplace le SQLite).
CREATE TABLE avecxy_sync_operations (
  uuid CHAR(36) PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  utilisateur_id INT UNSIGNED NOT NULL,
  type VARCHAR(60) NOT NULL,
  charge_hash CHAR(64) NOT NULL,
  statut ENUM('EN_COURS','APPLIQUEE','REJETEE','A_VERIFIER') NOT NULL,
  reponse JSON NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sync_org_statut (organisation_id, statut),
  CONSTRAINT fk_sync_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglages libres de l'organisation (duree de session, mot de passe initial,
-- delai d'alerte d'echeance, validation obligatoire des cotisations agent...).
CREATE TABLE avecxy_parametres (
  organisation_id INT UNSIGNED NOT NULL,
  cle VARCHAR(80) NOT NULL,
  valeur TEXT NOT NULL,
  type ENUM('TEXTE','ENTIER','DECIMAL','BOOLEEN','JSON') NOT NULL DEFAULT 'TEXTE',
  modifie_par INT UNSIGNED NULL,
  modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organisation_id, cle),
  CONSTRAINT fk_param_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vocabulaire renommable : carnet, agent, maison, part, mise...
CREATE TABLE avecxy_vocabulaire (
  organisation_id INT UNSIGNED NOT NULL,
  cle VARCHAR(40) NOT NULL,
  langue ENUM('fr','en') NOT NULL,
  singulier VARCHAR(60) NOT NULL,
  pluriel VARCHAR(60) NOT NULL,
  PRIMARY KEY (organisation_id, cle, langue),
  CONSTRAINT fk_vocab_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trace de toute action sensible : validation, annulation, derogation,
-- changement de regle, paiement. Jamais modifiee ni supprimee.
CREATE TABLE avecxy_journal_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NULL,      -- NULL = action plateforme
  utilisateur_id INT UNSIGNED NULL,
  plateforme_admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  objet_type VARCHAR(60) NOT NULL,
  objet_id BIGINT UNSIGNED NULL,
  avant JSON NULL,
  apres JSON NULL,
  motif TEXT NULL,
  ip VARCHAR(45) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_org_date (organisation_id, cree_le),
  KEY idx_audit_objet (objet_type, objet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. REGLES METIER FIXEES PAR L'ADMINISTRATION DE L'ORGANISATION
-- ---------------------------------------------------------------------
-- Types de carnet crees librement par chaque organisation (parts fixes ou libre).
CREATE TABLE avecxy_carnet_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  nom VARCHAR(80) NOT NULL,
  mode ENUM('PARTS','LIBRE') NOT NULL DEFAULT 'PARTS',
  duree_jours INT UNSIGNED NOT NULL DEFAULT 31,
  prix DECIMAL(16,2) NOT NULL DEFAULT 0,
  -- mode PARTS : ex. 31 parts / tranche 31 / 1 part benefice
  --              ou 90 parts / tranche 30 / 1 part benefice (parts 1, 31, 61)
  nombre_parts INT UNSIGNED NULL,
  taille_tranche INT UNSIGNED NULL,
  parts_benefice_par_tranche INT UNSIGNED NOT NULL DEFAULT 1,
  prelevement_benefice ENUM('DEBUT_TRANCHE','FIN_TRANCHE') NOT NULL DEFAULT 'DEBUT_TRANCHE',
  mise_minimum DECIMAL(16,2) NOT NULL DEFAULT 0,
  mise_maximum DECIMAL(16,2) NULL,
  -- retraits et fermeture, au choix de l'organisation
  retrait ENUM('TOTAL','PAR_TRANCHE','LIBRE') NOT NULL DEFAULT 'TOTAL',
  fermeture ENUM('PREMIER_RETRAIT','DERNIERE_TRANCHE_RETIREE','ECHEANCE','MANUELLE') NOT NULL DEFAULT 'PREMIER_RETRAIT',
  -- mode LIBRE : depots et retraits de montants libres, frais de tenue de compte
  depot_minimum DECIMAL(16,2) NULL,
  frais_tenue_type ENUM('AUCUN','FIXE','POURCENTAGE_SOLDE','POURCENTAGE_RETRAIT') NOT NULL DEFAULT 'AUCUN',
  frais_tenue_valeur DECIMAL(16,2) NOT NULL DEFAULT 0,
  frais_tenue_frequence ENUM('MENSUEL','A_CHAQUE_RETRAIT','A_LA_CLOTURE') NULL,
  par_defaut TINYINT(1) NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ct_org (organisation_id),
  CONSTRAINT fk_ct_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remplace le 45/55 en dur. Une regle par source de benefice, datee :
-- changer un taux cree une nouvelle ligne, l'historique reste juste.
CREATE TABLE avecxy_regles_partage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  source ENUM('VENTE_CARNET','PART_BENEFICE','FRAIS_TENUE','INTERET_CREDIT','PENALITE_CREDIT') NOT NULL,
  pourcentage_agent DECIMAL(5,2) NOT NULL,
  pourcentage_organisation DECIMAL(5,2) NOT NULL,
  applicable_du DATE NOT NULL,
  cree_par INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_partage (organisation_id, source, applicable_du),
  CONSTRAINT fk_partage_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT chk_partage_total CHECK (pourcentage_agent + pourcentage_organisation = 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remplace le bareme admin 30 / comptable 14 / ... et sa bascule "aout 2026".
CREATE TABLE avecxy_regles_repartition (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  nom VARCHAR(120) NOT NULL,
  applicable_du DATE NOT NULL,
  cree_par INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_repartition (organisation_id, applicable_du),
  CONSTRAINT fk_repart_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_regles_repartition_lignes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  regle_id INT UNSIGNED NOT NULL,
  libelle VARCHAR(120) NOT NULL,          -- "Comptable", "Loyer et reinvestissement"
  role_id INT UNSIGNED NULL,              -- si la ligne revient a un role
  pourcentage DECIMAL(5,2) NOT NULL,
  ordre INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_rl_regle FOREIGN KEY (regle_id) REFERENCES avecxy_regles_repartition(id) ON DELETE CASCADE,
  CONSTRAINT fk_rl_role FOREIGN KEY (role_id) REFERENCES avecxy_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eligibilite, limites et conditions du credit, fixees par l'admin.
-- Toute colonne NULL = critere non applique.
CREATE TABLE avecxy_credit_produits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  nom VARCHAR(80) NOT NULL,
  -- conditions du pret
  taux_interet DECIMAL(5,2) NOT NULL,
  duree_jours INT UNSIGNED NOT NULL,
  taux_penalite DECIMAL(5,2) NOT NULL DEFAULT 0,
  base_penalite ENUM('RESTANT','CAPITAL') NOT NULL DEFAULT 'RESTANT',
  frequence_penalite ENUM('MANUELLE','JOUR','SEMAINE','MOIS') NOT NULL DEFAULT 'MANUELLE',
  jours_tolerance INT UNSIGNED NOT NULL DEFAULT 0,
  -- limites
  montant_minimum DECIMAL(16,2) NULL,
  montant_maximum DECIMAL(16,2) NULL,
  multiple_epargne_max DECIMAL(6,2) NULL,       -- ex. 2.00 = 2 x l'epargne du client
  plafond_encours_pct_caisse DECIMAL(5,2) NULL, -- encours total / argent en main
  credits_simultanes_max INT UNSIGNED NOT NULL DEFAULT 1,
  -- eligibilite
  carnets_fermes_minimum INT UNSIGNED NOT NULL DEFAULT 0,
  carnet_actif_requis TINYINT(1) NOT NULL DEFAULT 0,
  anciennete_jours_minimum INT UNSIGNED NOT NULL DEFAULT 0,
  sans_retard_anterieur TINYINT(1) NOT NULL DEFAULT 0,
  accord_agent_requis TINYINT(1) NOT NULL DEFAULT 1,
  validation_manuelle_requise TINYINT(1) NOT NULL DEFAULT 1,
  derogation_autorisee TINYINT(1) NOT NULL DEFAULT 1,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cp_org (organisation_id),
  CONSTRAINT fk_cp_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. OPERATIONS
-- ---------------------------------------------------------------------
CREATE TABLE avecxy_clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,              -- code du recolteur + numero : JM4K-1, JM4K-2... (unique plateforme)
  nom VARCHAR(160) NOT NULL,
  telephone VARCHAR(40) NULL,
  adresse TEXT NULL,
  activite VARCHAR(160) NULL,
  statut ENUM('ACTIF','INACTIF','ARCHIVE') NOT NULL DEFAULT 'ACTIF',
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_client_code (code),
  KEY idx_client_agent (organisation_id, agent_id),
  KEY idx_client_nom (organisation_id, nom),
  CONSTRAINT fk_client_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_client_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Espace client (module du forfait le plus eleve, activable par organisation).
-- Connexion SANS code d'organisation : code client (ex. JM4K-12) + code secret.
-- Un meme epargnant peut avoir plusieurs comptes (plusieurs recolteurs ou
-- organisations) : ils sont rattaches entre eux par groupe_id et il passe de
-- l'un a l'autre sans se reconnecter. Consultation seule.
CREATE TABLE avecxy_client_acces (
  client_id INT UNSIGNED PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  groupe_id CHAR(36) NULL,                -- comptes rattaches du meme epargnant
  telephone VARCHAR(40) NULL,
  code_secret VARCHAR(255) NOT NULL,      -- code a chiffres, stocke hache
  doit_changer_code TINYINT(1) NOT NULL DEFAULT 1,
  langue ENUM('fr','en') NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  derniere_connexion DATETIME NULL,
  active_par INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client_acces_groupe (groupe_id),
  CONSTRAINT fk_cacces_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cacces_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_client_jetons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  jeton_hash CHAR(64) NOT NULL UNIQUE,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expire_le DATETIME NOT NULL,
  revoque_le DATETIME NULL,
  CONSTRAINT fk_cjeton_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le carnet copie les parametres de son type a l'ouverture : modifier un
-- type n'affecte jamais les carnets deja vendus.
CREATE TABLE avecxy_carnets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  carnet_type_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,           -- numero lisible par organisation
  prix DECIMAL(16,2) NOT NULL DEFAULT 0,
  mode ENUM('PARTS','LIBRE') NOT NULL DEFAULT 'PARTS',
  mise DECIMAL(16,2) NULL,                -- NULL en mode LIBRE
  nombre_parts INT UNSIGNED NULL,
  taille_tranche INT UNSIGNED NULL,
  parts_benefice_par_tranche INT UNSIGNED NOT NULL DEFAULT 0,
  prelevement_benefice ENUM('DEBUT_TRANCHE','FIN_TRANCHE') NOT NULL DEFAULT 'DEBUT_TRANCHE',
  retrait ENUM('TOTAL','PAR_TRANCHE','LIBRE') NOT NULL DEFAULT 'TOTAL',
  fermeture ENUM('PREMIER_RETRAIT','DERNIERE_TRANCHE_RETIREE','ECHEANCE','MANUELLE') NOT NULL DEFAULT 'PREMIER_RETRAIT',
  frais_tenue_type ENUM('AUCUN','FIXE','POURCENTAGE_SOLDE','POURCENTAGE_RETRAIT') NOT NULL DEFAULT 'AUCUN',
  frais_tenue_valeur DECIMAL(16,2) NOT NULL DEFAULT 0,
  frais_tenue_frequence ENUM('MENSUEL','A_CHAQUE_RETRAIT','A_LA_CLOTURE') NULL,
  parts_validees INT UNSIGNED NOT NULL DEFAULT 0,
  tranches_retirees INT UNSIGNED NOT NULL DEFAULT 0,
  total_retire DECIMAL(16,2) NOT NULL DEFAULT 0,
  total_frais DECIMAL(16,2) NOT NULL DEFAULT 0,
  date_echeance DATE NULL,
  parts_benefice_couvertes INT UNSIGNED NOT NULL DEFAULT 0,
  total_cotise DECIMAL(16,2) NOT NULL DEFAULT 0,
  epargne DECIMAL(16,2) NOT NULL DEFAULT 0,  -- solde disponible du client
  statut ENUM('ACTIF','FERME','ANNULE') NOT NULL DEFAULT 'ACTIF',
  date_ouverture DATE NOT NULL,
  date_fermeture DATE NULL,
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_carnet_numero (organisation_id, numero),
  KEY idx_carnet_client (client_id, statut),
  KEY idx_carnet_agent (organisation_id, agent_id, statut),
  CONSTRAINT fk_carnet_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_carnet_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id),
  CONSTRAINT fk_carnet_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id),
  CONSTRAINT fk_carnet_type FOREIGN KEY (carnet_type_id) REFERENCES avecxy_carnet_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_cotisations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  carnet_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  date_cotisation DATE NOT NULL,
  nombre_parts INT UNSIGNED NULL,          -- NULL = depot libre
  mise DECIMAL(16,2) NULL,
  montant_total DECIMAL(16,2) NOT NULL,
  parts_benefice INT UNSIGNED NOT NULL DEFAULT 0,
  parts_epargne INT UNSIGNED NOT NULL DEFAULT 0,
  montant_benefice DECIMAL(16,2) NOT NULL DEFAULT 0,
  montant_epargne DECIMAL(16,2) NOT NULL DEFAULT 0,
  commentaire TEXT NULL,
  statut ENUM('EN_ATTENTE','VALIDE','REJETE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
  cree_par INT UNSIGNED NULL,
  traite_par INT UNSIGNED NULL,
  traite_le DATETIME NULL,
  sync_uuid CHAR(36) NULL,                -- saisie hors ligne
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cotisation_sync (sync_uuid),
  KEY idx_cot_carnet (carnet_id, statut),
  KEY idx_cot_org_date (organisation_id, date_cotisation, statut),
  KEY idx_cot_agent (organisation_id, agent_id, statut),
  CONSTRAINT fk_cot_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_cot_carnet FOREIGN KEY (carnet_id) REFERENCES avecxy_carnets(id),
  CONSTRAINT fk_cot_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id),
  CONSTRAINT fk_cot_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaque benefice garde la regle de partage appliquee. Une annulation
-- passe par une ligne inverse (annule_id), pas par un DELETE.
CREATE TABLE avecxy_benefices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  date_operation DATE NOT NULL,
  periode CHAR(7) NOT NULL,
  source ENUM('VENTE_CARNET','PART_BENEFICE','FRAIS_TENUE','INTERET_CREDIT','PENALITE_CREDIT') NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  regle_partage_id INT UNSIGNED NULL,
  montant_total DECIMAL(16,2) NOT NULL,
  part_agent DECIMAL(16,2) NOT NULL DEFAULT 0,
  part_organisation DECIMAL(16,2) NOT NULL DEFAULT 0,
  annule_id BIGINT UNSIGNED NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ben_periode (organisation_id, periode),
  KEY idx_ben_agent (organisation_id, agent_id, periode),
  KEY idx_ben_source (source, source_id),
  CONSTRAINT fk_ben_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_ben_regle FOREIGN KEY (regle_partage_id) REFERENCES avecxy_regles_partage(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_retraits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  carnet_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  date_retrait DATE NOT NULL,
  tranche INT UNSIGNED NULL,              -- numero de tranche retiree (mode PAR_TRANCHE)
  solde_avant DECIMAL(16,2) NOT NULL,
  montant_retire DECIMAL(16,2) NOT NULL,
  frais DECIMAL(16,2) NOT NULL DEFAULT 0,
  ferme_carnet TINYINT(1) NOT NULL DEFAULT 0,
  statut ENUM('VALIDE','ANNULE') NOT NULL DEFAULT 'VALIDE',
  commentaire TEXT NULL,
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ret_org_date (organisation_id, date_retrait),
  CONSTRAINT fk_ret_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_ret_carnet FOREIGN KEY (carnet_id) REFERENCES avecxy_carnets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dossier d'eligibilite : l'agent propose, un role habilite decide.
CREATE TABLE avecxy_credit_eligibilites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  credit_produit_id INT UNSIGNED NOT NULL,
  statut ENUM('PROPOSE','VALIDE','REJETE','EXPIRE') NOT NULL DEFAULT 'PROPOSE',
  montant_demande DECIMAL(16,2) NULL,
  plafond_accorde DECIMAL(16,2) NULL,
  controle JSON NULL,                     -- resultat regle par regle au moment du controle
  derogation TINYINT(1) NOT NULL DEFAULT 0,
  motif_derogation TEXT NULL,
  propose_par INT UNSIGNED NULL,
  propose_le DATETIME NULL,
  decide_par INT UNSIGNED NULL,
  decide_le DATETIME NULL,
  commentaire TEXT NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_elig_client (client_id, statut),
  KEY idx_elig_org (organisation_id, statut),
  CONSTRAINT fk_elig_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_elig_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id),
  CONSTRAINT fk_elig_produit FOREIGN KEY (credit_produit_id) REFERENCES avecxy_credit_produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le credit copie taux, duree et penalite du produit a l'octroi.
CREATE TABLE avecxy_credits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  credit_produit_id INT UNSIGNED NOT NULL,
  eligibilite_id INT UNSIGNED NULL,
  numero INT UNSIGNED NOT NULL,
  date_credit DATE NOT NULL,
  date_echeance DATE NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  taux_interet DECIMAL(5,2) NOT NULL,
  montant_interet DECIMAL(16,2) NOT NULL,
  taux_penalite DECIMAL(5,2) NOT NULL DEFAULT 0,
  total_penalites DECIMAL(16,2) NOT NULL DEFAULT 0,
  total_du DECIMAL(16,2) NOT NULL,
  total_rembourse DECIMAL(16,2) NOT NULL DEFAULT 0,
  restant DECIMAL(16,2) NOT NULL,
  statut ENUM('EN_COURS','EN_RETARD','REMBOURSE','ANNULE') NOT NULL DEFAULT 'EN_COURS',
  derogation TINYINT(1) NOT NULL DEFAULT 0,
  motif_derogation TEXT NULL,
  commentaire TEXT NULL,
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_credit_numero (organisation_id, numero),
  KEY idx_credit_client (client_id, statut),
  KEY idx_credit_echeance (organisation_id, statut, date_echeance),
  CONSTRAINT fk_credit_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_credit_client FOREIGN KEY (client_id) REFERENCES avecxy_clients(id),
  CONSTRAINT fk_credit_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id),
  CONSTRAINT fk_credit_produit FOREIGN KEY (credit_produit_id) REFERENCES avecxy_credit_produits(id),
  CONSTRAINT fk_credit_elig FOREIGN KEY (eligibilite_id) REFERENCES avecxy_credit_eligibilites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_credit_remboursements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  credit_id INT UNSIGNED NOT NULL,
  date_paiement DATE NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  commentaire TEXT NULL,
  statut ENUM('EN_ATTENTE','VALIDE','REJETE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
  cree_par INT UNSIGNED NULL,
  traite_par INT UNSIGNED NULL,
  traite_le DATETIME NULL,
  sync_uuid CHAR(36) NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_remb_sync (sync_uuid),
  KEY idx_remb_credit (credit_id, statut),
  KEY idx_remb_org_date (organisation_id, date_paiement, statut),
  CONSTRAINT fk_remb_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_remb_credit FOREIGN KEY (credit_id) REFERENCES avecxy_credits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_credit_penalites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  credit_id INT UNSIGNED NOT NULL,
  date_penalite DATE NOT NULL,
  taux DECIMAL(5,2) NOT NULL,
  base DECIMAL(16,2) NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  automatique TINYINT(1) NOT NULL DEFAULT 0,
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_penalite_jour (credit_id, date_penalite),
  CONSTRAINT fk_pen_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_pen_credit FOREIGN KEY (credit_id) REFERENCES avecxy_credits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remises de fonds des agents (controle encaisse / verse).
CREATE TABLE avecxy_remises_fonds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  date_remise DATE NOT NULL,
  periode CHAR(7) NOT NULL,
  origine ENUM('MANUELLE','CONTROLE_COLLECTE') NOT NULL DEFAULT 'MANUELLE',
  montant_attendu DECIMAL(16,2) NOT NULL,
  montant_verse DECIMAL(16,2) NOT NULL,
  manquant DECIMAL(16,2) NOT NULL DEFAULT 0,
  excedent DECIMAL(16,2) NOT NULL DEFAULT 0,
  commentaire TEXT NULL,
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_remise_agent (organisation_id, agent_id, periode),
  CONSTRAINT fk_remise_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_remise_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Apports et retraits de fonds propres dans la caisse.
CREATE TABLE avecxy_mouvements_fonds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  date_mouvement DATE NOT NULL,
  type ENUM('APPORT','RETRAIT') NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  source VARCHAR(160) NULL,
  commentaire TEXT NULL,
  statut ENUM('VALIDE','ANNULE') NOT NULL DEFAULT 'VALIDE',
  cree_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mvt_org_date (organisation_id, date_mouvement),
  CONSTRAINT fk_mvt_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paiement mensuel des agents : montant recalcule cote serveur, detail conserve.
CREATE TABLE avecxy_paiements_agents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  periode CHAR(7) NOT NULL,
  date_paiement DATE NOT NULL,
  part_agent DECIMAL(16,2) NOT NULL,
  manquants_deduits DECIMAL(16,2) NOT NULL DEFAULT 0,
  montant_paye DECIMAL(16,2) NOT NULL,
  commentaire TEXT NULL,
  paye_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_paiement_agent (organisation_id, agent_id, periode),
  CONSTRAINT fk_pa_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_pa_agent FOREIGN KEY (agent_id) REFERENCES avecxy_agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repartition mensuelle de la part de l'organisation.
CREATE TABLE avecxy_repartitions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  periode CHAR(7) NOT NULL,
  regle_id INT UNSIGNED NOT NULL,
  date_paiement DATE NOT NULL,
  montant_base DECIMAL(16,2) NOT NULL,
  commentaire TEXT NULL,
  paye_par INT UNSIGNED NULL,
  supprime_le DATETIME NULL,              -- non NULL = dans la corbeille
  supprime_par INT UNSIGNED NULL,
  motif_suppression VARCHAR(255) NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_repartition_periode (organisation_id, periode),
  CONSTRAINT fk_rep_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_rep_regle FOREIGN KEY (regle_id) REFERENCES avecxy_regles_repartition(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_repartition_lignes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repartition_id INT UNSIGNED NOT NULL,
  libelle VARCHAR(120) NOT NULL,
  role_id INT UNSIGNED NULL,
  pourcentage DECIMAL(5,2) NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  CONSTRAINT fk_repl_rep FOREIGN KEY (repartition_id) REFERENCES avecxy_repartitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Frais de tenue de compte preleves sur les carnets libres.
CREATE TABLE avecxy_frais_tenue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  carnet_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  date_frais DATE NOT NULL,
  periode CHAR(7) NOT NULL,
  origine ENUM('MENSUEL','RETRAIT','CLOTURE') NOT NULL,
  retrait_id BIGINT UNSIGNED NULL,
  base DECIMAL(16,2) NOT NULL,
  montant DECIMAL(16,2) NOT NULL,
  supprime_le DATETIME NULL,
  supprime_par INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_frais_carnet (carnet_id, periode),
  CONSTRAINT fk_frais_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_frais_carnet FOREIGN KEY (carnet_id) REFERENCES avecxy_carnets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. SUPPORT (dans l'application : l'organisation ecrit, la plateforme repond)
-- ---------------------------------------------------------------------
CREATE TABLE avecxy_support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id INT UNSIGNED NOT NULL,
  utilisateur_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  sujet VARCHAR(190) NOT NULL,
  categorie ENUM('QUESTION','PROBLEME','ABONNEMENT','SUGGESTION') NOT NULL DEFAULT 'QUESTION',
  priorite ENUM('NORMALE','URGENTE') NOT NULL DEFAULT 'NORMALE',
  statut ENUM('OUVERT','EN_COURS','EN_ATTENTE_CLIENT','RESOLU','FERME') NOT NULL DEFAULT 'OUVERT',
  dernier_message_le DATETIME NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ticket_numero (organisation_id, numero),
  KEY idx_ticket_statut (statut, dernier_message_le),
  CONSTRAINT fk_ticket_org FOREIGN KEY (organisation_id) REFERENCES avecxy_organisations(id),
  CONSTRAINT fk_ticket_user FOREIGN KEY (utilisateur_id) REFERENCES avecxy_utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avecxy_support_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  utilisateur_id INT UNSIGNED NULL,         -- auteur cote organisation
  plateforme_admin_id INT UNSIGNED NULL,    -- auteur cote plateforme
  message TEXT NOT NULL,
  piece_jointe VARCHAR(255) NULL,
  lu_le DATETIME NULL,
  cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_msg_ticket (ticket_id, cree_le),
  CONSTRAINT fk_msg_ticket FOREIGN KEY (ticket_id) REFERENCES avecxy_support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

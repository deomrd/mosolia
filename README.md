# Mosolia

Application web (et PWA) multi-organisation d'épargne et de crédit.
PHP 8.2+ et MySQL 8 / MariaDB 10.6+, sans framework, sans dépendance externe.

## Ce que fait l'application

| Domaine | Contenu |
|---|---|
| Organisations | Multi-organisation : chaque donnée porte son `organisation_id`. Devise unique par caisse, français / anglais, couleur, fuseau. |
| Accès | Chaque organisation a un nom court (12 caractères au plus) ; un compte créé sans identifiant reçoit `prenom@nomcourt` (ex. `marie@tuungane`, ce n'est pas une adresse e-mail) et un mot de passe provisoire, montrés une fois dans une fenêtre. Connexion par cet identifiant, e-mail ou téléphone et mot de passe, par PIN, ou par empreinte / visage (clé de l'appareil) ; profils et droits, règles par utilisateur, portefeuille restreint au récolteur. |
| Page d'accueil | Page publique qui présente l'application, avec les forfaits lus dans la base et vos coordonnées. |
| Terrain | Récolteurs (identifiant de 4 caractères généré), clients (code `XXXX-n`, règles de doublons), produits d'épargne, carnets et comptes, collecte du jour, cotisations, retraits. Listes filtrées pendant la saisie. |
| Produits d'épargne | Chaque organisation crée les siens, à partir d'un modèle ou de zéro : **carnet à parts** (durée ou sans durée, parts par tranches, parts de bénéfice), **compte annuel** (372 mises, la première de chaque mois à la maison, réserve de 62 mises : retrait partiel seulement si la réserve reste, tout retirer seulement une fois complet), **compte courant** (dépôts et retraits libres, frais d'ouverture, solde minimum, frais de tenue mensuels, par retrait ou à la clôture), **compte bloqué** (durées et taux proposés au client, taux par an, par mois ou pour la durée, intérêts versés chaque mois ou à l'échéance, blocage des retraits seulement ou de tout le compte, sortie anticipée interdite, avec pénalité ou en perdant les intérêts). |
| Agences | Chaque agence a ses récolteurs (donc ses clients) et sa propre caisse ; transferts de fonds entre caisses (siège et agences) ; un utilisateur rattaché à une agence ne voit que ses clients, ses récolteurs, sa caisse, ses rapports et sa comptabilité. Un crédit sort de la caisse de l'agence du client. Sans agence, tout est au siège comme avant. |
| Comptabilité | Partie double calculée à partir des opérations (comme la caisse : rien n'est recopié, une correction se répercute) : bilan, compte de résultat, balance, journal, grand livre, par période et par agence, export Excel. Plan de comptes de départ (numérotation SYSCOHADA) modifiable ; chaque rôle (caisse, épargne, crédits, produits, charges…) est porté par un compte choisi par l'organisation. Écritures manuelles équilibrées (loyer, salaires, banque…). |
| Indicateurs | Portefeuille à risque (PAR 1, 30, 90), ancienneté des retards, encours par produit, crédits décaissés, taux de remboursement, intérêts reçus et versés, épargne par produit, clientèle (femmes, hommes, pièce d'identité). Export Excel. |
| Dossier client | Sexe, naissance, pièce d'identité (type, numéro, validité), personne à prévenir, photo (rangée hors du site public, servie seulement à qui voit le client). |
| Contrôle | Ventes de carnets, cotisations et paiements de crédit attendent le contrôle : récolteur par récolteur, on coche ce qu'on valide ou rejette, on note l'argent déposé (manquant ou excédent), et tout est gardé dans l'historique. |
| Caisse | Journal jour par jour, apports et retraits de fonds, remises des récolteurs (manquants, excédents), paiement des récolteurs, répartition mensuelle. |
| Partage | Par source de bénéfice et par date : part du récolteur / part de l'organisation ; règles de répartition de la part de l'organisation. |
| Crédits | Règles fixées par l'organisation ; le récolteur envoie une demande, la personne autorisée l'ouvre et accorde (en tout ou en partie : c'est une dette tout de suite) ou refuse ; minimum et maximum jamais dépassés ; remboursement libre ou par calendrier (tous les N jours, semaines ou mois), reconduction à l'échéance. Intérêts **à taux fixe**, **dégressifs à échéances égales** ou **dégressifs à capital fixe**, avec tableau d'amortissement ; frais de dossier (montant ou pourcentage, retenus sur le prêt ou ajoutés à la première échéance) ; garantie (part de l'épargne bloquée jusqu'au remboursement) ; pénalités de retard ; un remboursement anticipé ne réduit pas les intérêts. Exemple chiffré sur la page des règles. Module de forfait `credit`. |
| Rapports | Dix rapports détaillés avec période, récolteur et export CSV ; journal des actions. |
| Notifications | Dans l'application (cloche) : chaque retrait et chaque annulation de retrait pour le récolteur ; l'annulation aussi pour la caisse, la comptabilité et l'administration ; le résultat du contrôle pour le récolteur ; demande et activation d'abonnement pour l'administrateur et pour la plateforme. Chaque client ne voit que les siennes dans son espace. |
| Reprise de l'existant | Une organisation qui travaillait sur papier télécharge un modèle Excel (ses récolteurs, types de carnet et règles de crédit sont déjà dans les listes), le remplit, l'envoie : l'application montre les totaux et les lignes à corriger, puis importe. Annulable tant que rien n'a été fait dessus. Saisie de plusieurs clients d'un coup à l'écran. |
| Corbeille | Tout ce qui est supprimé ou rejeté reste restaurable 30 jours, puis est effacé définitivement. |
| Abonnement | Forfaits en base, états rappel / grâce / lecture seule / suspension, demande d'activation, page « ce qui est bloqué ». Paiement manuel. |
| Support | Demandes et réponses dans l'application. |
| Espace plateforme | `/plateforme` : liste des organisations avec recherche et filtres (état, forfait, bientôt expirées, demande à traiter) ; fiche détaillée (identité, nom court, activité : carnets, épargne, crédits, caisse ; utilisateurs avec profil, dernière connexion et réinitialisation du mot de passe ; abonnements et paiements ; journal de vos actions) ; forfaits, activation, suspension, support, délais, notifications. |
| Espace client | `/espace-client` : une application à part entière (menu en accordéon, barre d'onglets en bas sur téléphone, installable). Identifiant (code client) et PIN. Le client voit son épargne validée et, à part, son argent **en attente de validation** ; tout son historique (achats de carnet, versements, retraits, frais, paiements de crédit) ; ses crédits avec échéances et remboursements ; ses demandes et leur statut (en attente, accordée, refusée avec le motif) ; son éligibilité règle par règle. S'il est éligible et que l'organisation l'autorise, il envoie lui-même une demande de crédit. Il est prévenu à chaque saisie du récolteur, puis à la validation ou au rejet. Paramètres → Espace client : activation, adresse, identifiants des anciens clients, autorisation des demandes. Module de forfait `espace_client`. |
| PWA | Installable ; page claire hors connexion. Les pages ne sont jamais mises en cache. |

### Ce qui n'est pas fait

- **MySQL n'a pas été exécuté.** Tous les essais ont tourné sur SQLite (voir « Essais »). Le SQL est
  écrit pour MySQL 8 / MariaDB, mais la première installation sur MySQL est le vrai premier test :
  lancer `php bin/migrer.php` puis parcourir les écrans avant toute mise en service.
- Application Android Mosolia : écrite mais jamais compilée ni essayée sur un téléphone (voir la section dédiée).
  Elle ne connaît pas encore les comptes bloqués (choix de la durée) ni les nouveaux calculs de crédit :
  une ouverture de compte bloqué faite hors ligne est refusée à la synchronisation si le produit propose
  plusieurs durées.
- Microfinance : pas d'états réglementaires au format de la Banque Centrale (il faut leurs modèles officiels).
- Comptabilité de caisse : intérêts et pénalités de crédit sont des produits le jour où ils sont payés (pas
  d'intérêts courus non échus, pas de provisions pour créances douteuses, pas de clôture d'exercice : les résultats
  des années passées restent sur une ligne à part du bilan). Les bénéfices déjà pris sur papier avant une reprise
  ne sont pas en comptabilité.
- Changer le compte qui porte le rôle « caisse » : les écritures manuelles déjà passées sur l'ancien compte
  sortent du journal de caisse (elles restent en comptabilité sur l'ancien compte).
- Paiement d'abonnement automatique (Mobile Money) : les colonnes `prestataire_*` sont réservées.
- Transférer un client vers un autre récolteur, vocabulaire personnalisé par organisation
  (`avecxy_vocabulaire`), logo de l'organisation.
- Espace client : le client ne voit jamais l'état de la caisse de l'organisation. Une demande faite depuis son
  espace peut donc encore être refusée pour manque de fonds, au moment de la décision.
- Notifications : elles s'affichent dans l'application seulement (pas de SMS, d'e-mail ni de notification
  poussée sur le téléphone).
- Reprise de l'existant : le modèle a été relu et rempli avec une bibliothèque tierce (openpyxl) puis importé
  sans erreur, mais il n'a pas été ouvert dans Microsoft Excel lui-même. Manquants et emprunts en cours ne
  sont pas dans le fichier : ils se saisissent dans les écrans Remises de fonds et Caisse.
- Empreinte et visage : vérifiés avec un authentificateur simulé dans le navigateur, pas avec un vrai
  capteur. Ils demandent un site en HTTPS sur un nom de domaine (pas une adresse IP) et un appareil équipé.

### Choix de calcul à connaître

- Argent en main = toutes les entrées moins toutes les sorties (`DepotCaisse::requeteFlux`, définition unique).
  Un manquant de remise sort de la caisse ; il est déduit du paiement du récolteur.
- Sans règle de partage, un bénéfice revient entièrement à l'organisation.
- Partage de départ : 50 % au récolteur sur les bénéfices de ses propres clients, 50 % à l'organisation.
  L'administrateur le change par source et par date dans « Partage des bénéfices ».
- Manquants : l'organisation choisit de les déduire du paiement du récolteur ou de seulement les suivre.
  Dans les deux cas, le rapport « Manquants et excédents par récolteur » les détaille par personne.
  Un récolteur peut aussi rembourser un manquant (écran Remises de fonds) : l'argent rentre en caisse et
  ne sera pas déduit une seconde fois.
- Intérêts d'un compte bloqué : ils courent chaque jour sur le solde du jour (un dépôt rapporte à partir de
  son jour). Taux par an : taux / 365 par jour ; par mois : taux × 12 / 365 ; pour la durée : taux / nombre de
  jours de la durée. Versés à chaque mois anniversaire de l'ouverture ou à l'échéance, ils s'ajoutent à l'épargne
  (un intérêt versé chaque mois rapporte à son tour) et sont comptés comme une **charge de l'organisation**
  (bénéfice négatif, sans part du récolteur). Sortie anticipée « en perdant les intérêts » : les intérêts déjà
  versés sont retenus ; « avec pénalité » : pourcentage du solde.
- Crédit dégressif : le taux est celui d'une échéance (2 % par mois si les échéances sont mensuelles).
  Échéances égales = annuités constantes ; chaque intérêt est arrondi à l'unité de la devise, la dernière échéance
  prend le reste. Exemple vérifié : 100 000 FC à 2 % sur 12 mois = 9 456 FC par mois, 13 470 FC d'intérêts.
- Remboursement d'un crédit qui a un tableau d'amortissement : chaque paiement couvre les échéances dans l'ordre,
  l'intérêt de l'échéance d'abord, puis son capital (crédits plus anciens ou sans calendrier : capital d'abord).
- Agences : une ligne de caisse appartient à l'agence du récolteur concerné (cotisations, retraits, crédits…),
  à l'agence choisie (apports, retraits de fonds, écritures) ou au siège (répartitions).
- Compte bloqué « renouvelé automatiquement » : le lendemain de l'échéance, s'il n'a pas été vidé, il repart
  pour la même durée au même taux ; les intérêts de la période écoulée sont versés avant.
- Frais de dossier retenus : ils sont un bénéfice le jour de l'octroi (source « Frais de dossier des crédits »,
  partageable avec le récolteur) et la caisse ne sort que le prêt moins les frais.
- Fonds de l'organisation : un apport n'est pas une dette (il se reprend par un retrait de fonds) ; un emprunt
  est une dette, avec ou sans intérêt (calculé une fois sur le montant), remboursée avec l'argent en main.
  L'intérêt payé sur un emprunt ne touche jamais la part des récolteurs. L'organisation choisit de le
  retirer de sa propre part avant la répartition (réglage de départ) ou non, et de reporter ou non sur les
  mois suivants ce qu'un mois n'a pas pu couvrir.
- Crédit : le taux et la durée sont fixés à l'octroi (les règles n'en donnent que la valeur proposée) ;
  l'intérêt est calculé une fois sur le montant prêté. À l'échéance, si le crédit n'est pas soldé, le reste
  à payer reçoit une pénalité au même taux et repart pour la même durée (110 000 dus, 60 000 payés :
  50 000 deviennent 55 000 pour 30 jours de plus). Les règles peuvent désactiver cette reconduction.
  Un remboursement couvre d'abord le capital, puis l'intérêt, puis les pénalités.
- Remboursement : selon les règles du crédit, libre ou selon un calendrier (nombre d'échéances et intervalle
  en jours ; échéances égales, créées à l'octroi ; une échéance dépassée et non payée met le crédit en retard).
  En mode libre, le client paie ce qu'il a, en autant de fois qu'il veut. Si les règles du crédit
  l'autorisent, il peut payer avec l'épargne d'un carnet : le retrait suit les règles de ce carnet (tout ou
  tranche, frais, fermeture) et ce qui dépasse le reste à payer lui est remis.
- Carnet clôturé : il est verrouillé. Plus aucune opération, pas même la correction d'un montant. Seule une
  personne qui a le droit « Annuler un retrait » peut annuler le retrait qui l'a clôturé : le carnet se rouvre
  avec toutes ses données. Un carnet clôturé ne compte plus parmi les carnets actifs.
- Reprise de l'existant : les mises déjà versées, les retraits déjà faits et les remboursements déjà reçus
  sur papier sont un point de départ inscrit sur le carnet ou le crédit (`reprise_*`). Ils comptent dans le
  solde du client mais ne passent ni par la caisse, ni par le contrôle. Le carnet repris est « déjà payé »
  (prix 0). « Déjà bénéficié = OUI » : le bénéfice de la tranche en cours à la reprise n'est pas repartagé ;
  « NON » : il est partagé à la date de démarrage, selon la règle de partage. Pour un crédit, le reste dû
  inscrit au cahier fait foi et les intérêts déjà encaissés ne sont pas repartagés. L'argent en main à la
  date de démarrage entre par une seule ligne « Solde d'ouverture ».
- Fichiers Excel : lus et écrits sans bibliothèque (`Infrastructure/Tableur`), seule l'extension PHP `zlib`
  est nécessaire. Format `.xlsx` uniquement (pas l'ancien `.xls`, pas de CSV).
- Répartition : chaque ligne est arrondie à l'unité de la devise, la dernière ligne prend le reste.

## Installation (dans le navigateur, sans terminal)

1. Avoir une base MySQL (utf8mb4). Elle peut être partagée avec un autre site : l'application ne crée aucune base,
   seulement ses tables, toutes préfixées `avecxy_`, sans toucher aux autres.
2. Ouvrir l'application dans le navigateur : elle mène d'elle-même à `/installation`.
   - Étape 1 : identifiants MySQL (l'application écrit `config/base.php` ; sauter si le fichier existe déjà).
   - Étape 2 : création des tables.
   - Étape 3 : votre compte d'administrateur de la plateforme.
3. Vous arrivez dans votre interface, l'espace plateforme (`/plateforme`) : créer les forfaits, puis
   ouvrir les organisations. Leur personnel se connecte sur `/connexion`.

La page d'installation se ferme définitivement dès qu'un administrateur de la plateforme existe.
Faire pointer le site sur le dossier `public/` (recommandé) ; si l'hébergeur impose le dossier parent,
le `.htaccess` racine renvoie tout vers `public/`.

Mise à jour : remplacer le code, puis Espace plateforme → Réglages → « Mettre à jour la base ».
Les commandes `php bin/migrer.php`, `bin/creer-admin-plateforme.php` et `bin/creer-organisation.php`
restent disponibles pour qui préfère le terminal.

Le site doit être servi en HTTPS : sans lui, ni l'installation en PWA ni la protection des
sessions ne sont complètes.

### Travaux automatiques

Retards et pénalités de crédit, frais de tenue mensuels, intérêts des comptes bloqués : l'application les fait seule, une fois
par jour et par organisation, à la première page ouverte. Aucune tâche planifiée n'est nécessaire.
`php bin/taches.php` fait le même travail pour toutes les organisations si vous préférez un cron.

## Organisation du code

| Dossier | Rôle |
|---|---|
| `src/Domaine` | Règles pures, sans base ni HTTP : noms, montants, droits, état de l'abonnement, calcul des carnets, contrôle des crédits |
| `src/Application` | Cas d'usage : un service par domaine (cotisations, retraits, caisse, crédits, plateforme, espace client...) |
| `src/Infrastructure` | Accès à la base (dépôts) et migrations |
| `src/Http` | Contrôleurs : lisent la requête, appellent un service, renvoient une page |
| `src/Noyau` | Mécanique : routeur, session, vues, traduction, base, enchaînement des contrôles |
| `config/` | `routes.php` (adresses et droit exigé), `menu.php` (menu accordéon), réglages techniques |
| `ressources/vues` | Pages HTML |
| `ressources/langues` | `fr.php` et `en.php` : tous les textes |
| `database/migrations` | Structure de la base, appliquée dans l'ordre par `bin/migrer.php` |

Sens des dépendances : Http → Application → Domaine. Le Domaine ne dépend de rien.

## Ajouter une page

1. Une ligne dans `config/routes.php` (avec le `droit` exigé).
2. Une méthode de contrôleur dans `src/Http/Controleurs`.
3. Une vue dans `ressources/vues`, ses textes dans `fr.php` et `en.php`.
4. Une entrée dans `config/menu.php`.

## Ajouter un droit

L'ajouter dans `src/Domaine/CatalogueDroits.php`, ajouter son libellé `droit.<code>` dans les
deux fichiers de langue, puis lancer `php bin/migrer.php`. Il devient cochable dans les profils.

## Règles tenues partout

- Chaque formulaire porte un jeton anti-falsification ; toutes les requêtes SQL sont préparées.
- Toute donnée est filtrée par `organisation_id` : une organisation ne voit jamais celle d'une autre.
- Les noms sont enregistrés en MAJUSCULES sans accents (`Domaine\Texte::nom`).
- Supprimer = envoyer à la corbeille. Chaque action sensible est inscrite au journal d'audit.
- Une organisation garde toujours au moins un administrateur actif.
- La structure de la base ne change que par les migrations, jamais pendant une requête.

## Essais

- `php tests/domaine.php` : les calculs (parts, tranches, frais, partage, crédits).
- `bash tests/parcours.sh` : 394 vérifications de bout en bout, de la création d'un forfait jusqu'aux
  demandes de crédit depuis l'espace client. Il crée une base SQLite temporaire et son propre serveur local ; il ne lit ni ne
  modifie `config/base.php`. Il faut `php` avec `pdo_sqlite`, et `curl`.
- Essai à la main sans MySQL : `php tests/preparer_sqlite.php stockage/essai.sqlite`, mettre
  `['pilote' => 'sqlite', 'fichier' => '...']` dans `config/base.php`, puis
  `php -S 127.0.0.1:8080 -t public tests/serveur.php`.

SQLite ne sert qu'aux essais : la production est prévue pour MySQL / MariaDB.

## Application Android Mosolia

Le dossier `android/` contient l'application native « Mosolia » (Kotlin, Jetpack Compose, SQLite).
Une seule application pour tous : l'adresse du serveur y est intégrée et n'est jamais affichée.
L'écran de connexion est unique, et le serveur reconnaît qui se connecte.

**Profils**

- **Récolteur** : la tournée hors ligne. Les saisies sont gardées dans le téléphone puis synchronisées.
- **Gestion** : le site dans l'application, en ligne. Chacun ne voit que les pages permises par ses droits.
- **Les deux** : la personne bascule entre « Ma tournée » (hors ligne) et « Gestion » (en ligne).
- **Client** : avec son code client et son code secret, il arrive dans son espace client.

**Fonctionnement dans l'application**

- Le site reconnaît l'application, qui ajoute `MosoliaApp` à l'agent du navigateur ou ouvre la session par un passage. Il cache alors ce qui ne sert que sur ordinateur : modèles et import Excel, exports, impression, photo.
- Sur un petit écran, les tableaux n'affichent que les colonnes essentielles. Les autres colonnes sont marquées `tel-cache` dans les vues ; le détail est dans la fiche.
- L'adresse intégrée à l'APK est `https://mosolia.boutixy.com`. *Plateforme → Réglages → « Adresse de l'application »* permet de la changer sans nouvel APK.

**API `/api/v1`** (jeton par téléphone, sans session)

| Adresse | Rôle |
| --- | --- |
| `GET /api/v1` | version et adresse du serveur donnée par la plateforme |
| `POST /api/v1/connexion` | identifiant + mot de passe → jeton, profil (`RECOLTEUR`, `GESTION`, `LES_DEUX`) et portefeuille ; code client + code secret → profil `CLIENT` et passage vers l'espace client |
| `POST /api/v1/passage` | passage à usage unique (2 minutes) vers une page du site, ouverte dans l'application sans redemander le mot de passe (`GET /passage/{code}`) |
| `GET /api/v1/portefeuille` | clients, carnets (avec comptes bloqués), crédits, types de carnet (durées et taux des comptes bloqués), règles de crédit (mode de calcul, garantie, échéances), saisies, argent à déposer, solde du mois, notifications. Une personne sans récolteur reçoit 403 avec le code `GESTION` |
| `POST /api/v1/synchroniser` | envoi des saisies faites hors ligne (chaque saisie porte un identifiant unique : jamais comptée deux fois) |
| `POST /api/v1/notifications/lues`, `POST /api/v1/deconnexion` | |

**Saisies hors ligne acceptées**

- nouveau client ;
- vente de carnet (durée choisie pour un compte bloqué) ;
- cotisation : saisie, correction ou suppression avant validation ;
- remboursement : saisie, ou suppression avant validation ;
- demande de crédit.

Le serveur applique les règles de l'organisation à la synchronisation. Une saisie refusée revient au récolteur avec la raison.

La page *Utilisateurs* liste les téléphones connectés de chaque personne, avec un bouton pour désactiver un téléphone perdu.

La construction de l'APK est décrite dans `android/README.md`. Limite connue : l'application Android n'a pas pu être compilée ni essayée sur un téléphone dans l'environnement de développement. Seuls le serveur et l'API sont couverts par les tests.

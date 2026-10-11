<?php
// Toutes les adresses de l'application.
// Options : public (sans connexion), droit (droit exige), toujours (autorise meme en lecture seule).

use AvecXY\Http\Controleurs\AbonnementControleur;
use AvecXY\Http\Controleurs\AccueilControleur;
use AvecXY\Http\Controleurs\AgencesControleur;
use AvecXY\Http\Controleurs\CaisseControleur;
use AvecXY\Http\Controleurs\ComptabiliteControleur;
use AvecXY\Http\Controleurs\CarnetsControleur;
use AvecXY\Http\Controleurs\PartageControleur;
use AvecXY\Http\Controleurs\PlateformeControleur;
use AvecXY\Http\Controleurs\PortailControleur;
use AvecXY\Http\Controleurs\ClientsControleur;
use AvecXY\Http\Controleurs\CompteControleur;
use AvecXY\Http\Controleurs\ControleControleur;
use AvecXY\Http\Controleurs\ConnexionControleur;
use AvecXY\Http\Controleurs\CorbeilleControleur;
use AvecXY\Http\Controleurs\CotisationsControleur;
use AvecXY\Http\Controleurs\CreditsControleur;
use AvecXY\Http\Controleurs\ReglesCreditControleur;
use AvecXY\Http\Controleurs\InstallationControleur;
use AvecXY\Http\Controleurs\NotificationsControleur;
use AvecXY\Http\Controleurs\OrganisationControleur;
use AvecXY\Http\Controleurs\ProfilsControleur;
use AvecXY\Http\Controleurs\RapportsControleur;
use AvecXY\Http\Controleurs\RecolteursControleur;
use AvecXY\Http\Controleurs\RepriseControleur;
use AvecXY\Http\Controleurs\RetraitsControleur;
use AvecXY\Http\Controleurs\TableauControleur;
use AvecXY\Http\Controleurs\TypesCarnetControleur;
use AvecXY\Http\Controleurs\UtilisateursControleur;
use AvecXY\Noyau\Routeur;

return static function (Routeur $r): void {
    // Installation dans le navigateur (se ferme des qu'un administrateur de la plateforme existe)
    $r->ajouter('GET', '/installation', [InstallationControleur::class, 'index'], ['public' => true]);
    $r->ajouter('POST', '/installation/base', [InstallationControleur::class, 'base'], ['public' => true]);
    $r->ajouter('POST', '/installation/tables', [InstallationControleur::class, 'tables'], ['public' => true]);
    $r->ajouter('POST', '/installation/admin', [InstallationControleur::class, 'admin'], ['public' => true]);

    // Connexion
    $r->ajouter('GET', '/connexion', [ConnexionControleur::class, 'formulaire'], ['public' => true]);
    $r->ajouter('POST', '/connexion', [ConnexionControleur::class, 'connecter'], ['public' => true]);
    $r->ajouter('GET', '/passage/{code}', [ConnexionControleur::class, 'passage'], ['public' => true]);
    $r->ajouter('POST', '/connexion/pin', [ConnexionControleur::class, 'connecterParPin'], ['public' => true]);
    $r->ajouter('POST', '/connexion/cle/defi', [ConnexionControleur::class, 'defiCle'], ['public' => true]);
    $r->ajouter('POST', '/connexion/cle', [ConnexionControleur::class, 'connecterParCle'], ['public' => true]);
    $r->ajouter('POST', '/deconnexion', [ConnexionControleur::class, 'deconnecter'], ['public' => true]);
    $r->ajouter('POST', '/langue', [ConnexionControleur::class, 'langue'], ['public' => true]);

    // Page publique qui presente l'application (affichee sur "/" quand personne n'est connecte)
    $r->ajouter('GET', '/bienvenue', [AccueilControleur::class, 'index'], ['public' => true]);

    // Accueil et compte personnel
    $r->ajouter('GET', '/', [TableauControleur::class, 'index']);
    $r->ajouter('GET', '/mon-compte', [CompteControleur::class, 'formulaire'], ['toujours' => true]);
    $r->ajouter('POST', '/mon-compte', [CompteControleur::class, 'enregistrer'], ['toujours' => true]);
    $r->ajouter('POST', '/mon-compte/cle/defi', [CompteControleur::class, 'defiCle'], ['toujours' => true]);
    $r->ajouter('POST', '/mon-compte/cle', [CompteControleur::class, 'ajouterCle'], ['toujours' => true]);
    $r->ajouter('POST', '/mon-compte/cle/{id}/supprimer', [CompteControleur::class, 'supprimerCle'], ['toujours' => true]);

    $r->ajouter('GET', '/notifications', [NotificationsControleur::class, 'personnel'], ['toujours' => true]);

    // Clients
    $r->ajouter('GET', '/clients', [ClientsControleur::class, 'index'], ['droit' => 'clients.voir']);
    $r->ajouter('GET', '/clients/nouveau', [ClientsControleur::class, 'nouveau'], ['droit' => 'clients.creer']);
    $r->ajouter('POST', '/clients/nouveau', [ClientsControleur::class, 'creer'], ['droit' => 'clients.creer']);
    $r->ajouter('GET', '/clients/{id}', [ClientsControleur::class, 'fiche'], ['droit' => 'clients.voir']);
    $r->ajouter('GET', '/clients/{id}/modifier', [ClientsControleur::class, 'modifier'], ['droit' => 'clients.modifier']);
    $r->ajouter('POST', '/clients/{id}/modifier', [ClientsControleur::class, 'enregistrer'], ['droit' => 'clients.modifier']);
    $r->ajouter('POST', '/clients/{id}/photo', [ClientsControleur::class, 'envoyerPhoto'], ['droit' => 'clients.modifier']);
    $r->ajouter('GET', '/clients/{id}/photo', [ClientsControleur::class, 'photo'], ['droit' => 'clients.voir']);
    $r->ajouter('POST', '/clients/{id}/supprimer', [ClientsControleur::class, 'supprimer'], ['droit' => 'clients.supprimer']);

    // Carnets, cotisations et retraits
    $r->ajouter('GET', '/carnets', [CarnetsControleur::class, 'index'], ['droit' => 'carnets.voir']);
    $r->ajouter('GET', '/carnets/nouveau', [CarnetsControleur::class, 'nouveau'], ['droit' => 'carnets.vendre']);
    $r->ajouter('POST', '/carnets/nouveau', [CarnetsControleur::class, 'vendre'], ['droit' => 'carnets.vendre']);
    $r->ajouter('GET', '/carnets/{id}', [CarnetsControleur::class, 'fiche'], ['droit' => 'carnets.voir']);
    $r->ajouter('POST', '/carnets/{id}/cotiser', [CarnetsControleur::class, 'cotiser'], ['droit' => 'cotisations.saisir']);
    $r->ajouter('POST', '/carnets/{id}/retirer', [CarnetsControleur::class, 'retirer'], ['droit' => 'retraits.faire']);
    $r->ajouter('POST', '/carnets/{id}/fermer', [CarnetsControleur::class, 'fermer'], ['droit' => 'retraits.faire']);
    $r->ajouter('POST', '/carnets/{id}/supprimer', [CarnetsControleur::class, 'supprimer'], ['droit' => 'carnets.supprimer']);
    $r->ajouter('GET', '/collecte', [CotisationsControleur::class, 'collecte'], ['droit' => 'cotisations.saisir']);
    $r->ajouter('POST', '/collecte', [CotisationsControleur::class, 'collecter'], ['droit' => 'cotisations.saisir']);
    $r->ajouter('GET', '/cotisations', [CotisationsControleur::class, 'index'], ['droit' => ['cotisations.saisir', 'cotisations.valider', 'cotisations.corriger']]);
    $r->ajouter('GET', '/cotisations/{id}', [CotisationsControleur::class, 'modifier'], ['droit' => ['cotisations.saisir', 'cotisations.corriger']]);
    $r->ajouter('POST', '/cotisations/{id}', [CotisationsControleur::class, 'corriger'], ['droit' => ['cotisations.saisir', 'cotisations.corriger']]);
    $r->ajouter('POST', '/cotisations/{id}/supprimer', [CotisationsControleur::class, 'supprimer'], ['droit' => ['cotisations.saisir', 'cotisations.corriger']]);
    $r->ajouter('GET', '/retraits', [RetraitsControleur::class, 'index'], ['droit' => 'retraits.voir']);
    $r->ajouter('POST', '/retraits/{id}/supprimer', [RetraitsControleur::class, 'supprimer'], ['droit' => 'retraits.annuler']);
    $r->ajouter('POST', '/carnets/{id}/rouvrir', [CarnetsControleur::class, 'rouvrir'], ['droit' => 'retraits.annuler']);

    // Controle : recolteur par recolteur, puis historique
    $r->ajouter('GET', '/controle/historique', [ControleControleur::class, 'historique'], ['droit' => ['carnets.valider', 'cotisations.valider', 'remboursements.valider'], 'toujours' => true]);
    $r->ajouter('GET', '/controle/{type}', [ControleControleur::class, 'liste'], ['droit' => ['carnets.valider', 'cotisations.valider', 'remboursements.valider']]);
    $r->ajouter('POST', '/controle/{type}', [ControleControleur::class, 'traiter'], ['droit' => ['carnets.valider', 'cotisations.valider', 'remboursements.valider']]);

    // Credits
    $r->ajouter('GET', '/credits', [CreditsControleur::class, 'index'], ['droit' => 'credits.voir', 'module' => 'credit']);
    $r->ajouter('GET', '/credits/nouveau', [CreditsControleur::class, 'nouveau'], ['droit' => 'credits.accorder', 'module' => 'credit']);
    $r->ajouter('POST', '/credits/nouveau', [CreditsControleur::class, 'accorder'], ['droit' => 'credits.accorder', 'module' => 'credit']);
    $r->ajouter('GET', '/credits/{id}', [CreditsControleur::class, 'fiche'], ['droit' => 'credits.voir', 'module' => 'credit']);
    $r->ajouter('POST', '/credits/{id}/rembourser', [CreditsControleur::class, 'rembourser'], ['droit' => ['remboursements.saisir', 'remboursements.valider'], 'module' => 'credit']);
    $r->ajouter('POST', '/credits/{id}/rembourser-epargne', [CreditsControleur::class, 'rembourserParEpargne'], ['droit' => 'retraits.faire', 'module' => 'credit']);
    $r->ajouter('POST', '/credits/{id}/penaliser', [CreditsControleur::class, 'penaliser'], ['droit' => 'credits.accorder', 'module' => 'credit']);
    $r->ajouter('POST', '/credits/{type}/{id}/supprimer', [CreditsControleur::class, 'supprimer'], ['droit' => ['credits.accorder', 'remboursements.valider', 'parametres.credit'], 'module' => 'credit']);
    $r->ajouter('GET', '/eligibilite', [CreditsControleur::class, 'demandes'], ['droit' => ['eligibilite.proposer', 'eligibilite.decider', 'credits.accorder'], 'module' => 'credit']);
    $r->ajouter('POST', '/eligibilite', [CreditsControleur::class, 'proposer'], ['droit' => 'eligibilite.proposer', 'module' => 'credit']);
    $r->ajouter('GET', '/eligibilite/{id}', [CreditsControleur::class, 'demande'], ['droit' => ['eligibilite.proposer', 'eligibilite.decider', 'credits.accorder'], 'module' => 'credit']);
    $r->ajouter('POST', '/eligibilite/{id}', [CreditsControleur::class, 'decider'], ['droit' => ['eligibilite.decider', 'credits.accorder'], 'module' => 'credit']);
    $r->ajouter('GET', '/remboursements', [CreditsControleur::class, 'remboursements'], ['droit' => ['remboursements.saisir', 'remboursements.valider'], 'module' => 'credit']);
    $r->ajouter('GET', '/parametres/credit', [ReglesCreditControleur::class, 'index'], ['droit' => 'parametres.credit', 'module' => 'credit']);
    $r->ajouter('GET', '/parametres/credit/nouveau', [ReglesCreditControleur::class, 'nouveau'], ['droit' => 'parametres.credit', 'module' => 'credit']);
    $r->ajouter('POST', '/parametres/credit/nouveau', [ReglesCreditControleur::class, 'creer'], ['droit' => 'parametres.credit', 'module' => 'credit']);
    $r->ajouter('GET', '/parametres/credit/{id}', [ReglesCreditControleur::class, 'modifier'], ['droit' => 'parametres.credit', 'module' => 'credit']);
    $r->ajouter('POST', '/parametres/credit/{id}', [ReglesCreditControleur::class, 'enregistrer'], ['droit' => 'parametres.credit', 'module' => 'credit']);

    // Caisse
    $r->ajouter('GET', '/caisse', [CaisseControleur::class, 'journal'], ['droit' => 'caisse.voir']);
    $r->ajouter('POST', '/caisse/mouvements', [CaisseControleur::class, 'mouvement'], ['droit' => 'caisse.mouvements']);
    $r->ajouter('POST', '/caisse/transferts', [CaisseControleur::class, 'transferer'], ['droit' => 'caisse.transferts']);
    $r->ajouter('POST', '/caisse/{type}/{id}/supprimer', [CaisseControleur::class, 'supprimer'], ['droit' => ['caisse.mouvements', 'caisse.transferts', 'remises.gerer', 'agents.payer', 'repartition.payer']]);
    $r->ajouter('POST', '/caisse/emprunts', [CaisseControleur::class, 'emprunter'], ['droit' => 'caisse.mouvements']);
    $r->ajouter('POST', '/caisse/emprunts/{id}/rembourser', [CaisseControleur::class, 'rembourserEmprunt'], ['droit' => 'caisse.mouvements']);
    $r->ajouter('POST', '/remises/manquants', [CaisseControleur::class, 'reglerManquant'], ['droit' => 'remises.gerer']);
    $r->ajouter('GET', '/remises', [CaisseControleur::class, 'remises'], ['droit' => 'remises.gerer']);
    $r->ajouter('POST', '/remises', [CaisseControleur::class, 'remettre'], ['droit' => 'remises.gerer']);
    $r->ajouter('GET', '/paiements-recolteurs', [CaisseControleur::class, 'paiements'], ['droit' => 'agents.payer']);
    $r->ajouter('POST', '/paiements-recolteurs', [CaisseControleur::class, 'payer'], ['droit' => 'agents.payer']);
    $r->ajouter('GET', '/repartition', [CaisseControleur::class, 'repartition'], ['droit' => 'repartition.voir']);
    $r->ajouter('POST', '/repartition', [CaisseControleur::class, 'repartir'], ['droit' => 'repartition.payer']);
    $r->ajouter('GET', '/parametres/partage', [PartageControleur::class, 'index'], ['droit' => 'parametres.partage']);
    $r->ajouter('POST', '/parametres/partage', [PartageControleur::class, 'partage'], ['droit' => 'parametres.partage']);
    $r->ajouter('POST', '/parametres/partage/manquants', [PartageControleur::class, 'manquants'], ['droit' => 'parametres.partage']);
    $r->ajouter('POST', '/parametres/partage/repartition', [PartageControleur::class, 'repartition'], ['droit' => 'parametres.partage']);

    // Rapports
    $r->ajouter('GET', '/rapports', [RapportsControleur::class, 'index'], ['droit' => 'rapports.voir', 'toujours' => true]);

    // Parametres : organisation
    $r->ajouter('GET', '/parametres/organisation', [OrganisationControleur::class, 'formulaire'], ['droit' => 'parametres.organisation']);
    $r->ajouter('POST', '/parametres/organisation', [OrganisationControleur::class, 'enregistrer'], ['droit' => 'parametres.organisation']);

    // Parametres : profils et droits
    $r->ajouter('GET', '/parametres/profils', [ProfilsControleur::class, 'index'], ['droit' => 'profils.gerer']);
    $r->ajouter('GET', '/parametres/profils/nouveau', [ProfilsControleur::class, 'nouveau'], ['droit' => 'profils.gerer']);
    $r->ajouter('POST', '/parametres/profils/nouveau', [ProfilsControleur::class, 'creer'], ['droit' => 'profils.gerer']);
    $r->ajouter('GET', '/parametres/profils/{id}', [ProfilsControleur::class, 'modifier'], ['droit' => 'profils.gerer']);
    $r->ajouter('POST', '/parametres/profils/{id}', [ProfilsControleur::class, 'enregistrer'], ['droit' => 'profils.gerer']);
    $r->ajouter('POST', '/parametres/profils/{id}/supprimer', [ProfilsControleur::class, 'supprimer'], ['droit' => 'profils.gerer']);

    // Parametres : utilisateurs
    $r->ajouter('GET', '/parametres/utilisateurs', [UtilisateursControleur::class, 'index'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('POST', '/parametres/appareils/{id}/desactiver', [UtilisateursControleur::class, 'desactiverAppareil'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('GET', '/parametres/utilisateurs/nouveau', [UtilisateursControleur::class, 'nouveau'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('POST', '/parametres/utilisateurs/nouveau', [UtilisateursControleur::class, 'creer'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('GET', '/parametres/utilisateurs/{id}', [UtilisateursControleur::class, 'modifier'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('POST', '/parametres/utilisateurs/{id}', [UtilisateursControleur::class, 'enregistrer'], ['droit' => 'utilisateurs.gerer']);
    $r->ajouter('POST', '/parametres/utilisateurs/{id}/supprimer', [UtilisateursControleur::class, 'supprimer'], ['droit' => 'utilisateurs.gerer']);

    // Parametres : recolteurs
    $r->ajouter('GET', '/indicateurs', [RapportsControleur::class, 'gestion'], ['droit' => 'rapports.gestion']);
    $r->ajouter('GET', '/indicateurs/export', [RapportsControleur::class, 'exporterGestion'], ['droit' => 'rapports.gestion']);
    $r->ajouter('GET', '/comptabilite/etats', [ComptabiliteControleur::class, 'etats'], ['droit' => 'compta.voir']);
    $r->ajouter('GET', '/comptabilite/balance', [ComptabiliteControleur::class, 'balance'], ['droit' => 'compta.voir']);
    $r->ajouter('GET', '/comptabilite/journal', [ComptabiliteControleur::class, 'journal'], ['droit' => 'compta.voir']);
    $r->ajouter('GET', '/comptabilite/grand-livre', [ComptabiliteControleur::class, 'grandLivre'], ['droit' => 'compta.voir']);
    $r->ajouter('GET', '/comptabilite/export', [ComptabiliteControleur::class, 'exporter'], ['droit' => 'compta.voir']);
    $r->ajouter('GET', '/comptabilite/ecritures', [ComptabiliteControleur::class, 'ecritures'], ['droit' => ['compta.voir', 'compta.saisir']]);
    $r->ajouter('POST', '/comptabilite/ecritures', [ComptabiliteControleur::class, 'saisir'], ['droit' => 'compta.saisir']);
    $r->ajouter('POST', '/comptabilite/ecritures/{id}/supprimer', [ComptabiliteControleur::class, 'supprimerEcriture'], ['droit' => 'compta.saisir']);
    $r->ajouter('GET', '/comptabilite/plan', [ComptabiliteControleur::class, 'plan'], ['droit' => ['compta.voir', 'compta.plan']]);
    $r->ajouter('POST', '/comptabilite/plan/roles', [ComptabiliteControleur::class, 'affecter'], ['droit' => 'compta.plan']);
    $r->ajouter('GET', '/comptabilite/plan/{id}', [ComptabiliteControleur::class, 'compte'], ['droit' => 'compta.plan']);
    $r->ajouter('POST', '/comptabilite/plan/{id}', [ComptabiliteControleur::class, 'enregistrerCompte'], ['droit' => 'compta.plan']);
    $r->ajouter('POST', '/comptabilite/plan/{id}/supprimer', [ComptabiliteControleur::class, 'supprimerCompte'], ['droit' => 'compta.plan']);
    $r->ajouter('GET', '/parametres/agences', [AgencesControleur::class, 'index'], ['droit' => 'agences.gerer']);
    $r->ajouter('GET', '/parametres/agences/nouvelle', [AgencesControleur::class, 'nouveau'], ['droit' => 'agences.gerer']);
    $r->ajouter('POST', '/parametres/agences/nouvelle', [AgencesControleur::class, 'creer'], ['droit' => 'agences.gerer']);
    $r->ajouter('GET', '/parametres/agences/{id}', [AgencesControleur::class, 'modifier'], ['droit' => 'agences.gerer']);
    $r->ajouter('POST', '/parametres/agences/{id}', [AgencesControleur::class, 'enregistrer'], ['droit' => 'agences.gerer']);
    $r->ajouter('POST', '/parametres/agences/{id}/supprimer', [AgencesControleur::class, 'supprimer'], ['droit' => 'agences.gerer']);
    $r->ajouter('GET', '/parametres/recolteurs', [RecolteursControleur::class, 'index'], ['droit' => 'recolteurs.gerer']);
    $r->ajouter('GET', '/parametres/recolteurs/nouveau', [RecolteursControleur::class, 'nouveau'], ['droit' => 'recolteurs.gerer']);
    $r->ajouter('POST', '/parametres/recolteurs/nouveau', [RecolteursControleur::class, 'creer'], ['droit' => 'recolteurs.gerer']);
    $r->ajouter('GET', '/parametres/recolteurs/{id}', [RecolteursControleur::class, 'modifier'], ['droit' => 'recolteurs.gerer']);
    $r->ajouter('POST', '/parametres/recolteurs/{id}', [RecolteursControleur::class, 'enregistrer'], ['droit' => 'recolteurs.gerer']);
    $r->ajouter('POST', '/parametres/recolteurs/{id}/supprimer', [RecolteursControleur::class, 'supprimer'], ['droit' => 'recolteurs.gerer']);

    // Parametres : types de carnet
    $r->ajouter('GET', '/parametres/types-carnet', [TypesCarnetControleur::class, 'index'], ['droit' => 'parametres.carnets']);
    $r->ajouter('GET', '/parametres/types-carnet/nouveau', [TypesCarnetControleur::class, 'nouveau'], ['droit' => 'parametres.carnets']);
    $r->ajouter('POST', '/parametres/types-carnet/nouveau', [TypesCarnetControleur::class, 'creer'], ['droit' => 'parametres.carnets']);
    $r->ajouter('GET', '/parametres/types-carnet/{id}', [TypesCarnetControleur::class, 'modifier'], ['droit' => 'parametres.carnets']);
    $r->ajouter('POST', '/parametres/types-carnet/{id}', [TypesCarnetControleur::class, 'enregistrer'], ['droit' => 'parametres.carnets']);
    $r->ajouter('POST', '/parametres/types-carnet/{id}/supprimer', [TypesCarnetControleur::class, 'supprimer'], ['droit' => 'parametres.carnets']);

    // Abonnement et support (toujours accessibles : c'est par la qu'on sort d'un blocage)
    $r->ajouter('GET', '/abonnement', [AbonnementControleur::class, 'index'], ['droit' => 'abonnement.gerer', 'toujours' => true]);
    $r->ajouter('POST', '/abonnement', [AbonnementControleur::class, 'demander'], ['droit' => 'abonnement.gerer', 'toujours' => true]);
    $r->ajouter('GET', '/support', [AbonnementControleur::class, 'support'], ['droit' => 'support.utiliser', 'toujours' => true]);
    $r->ajouter('POST', '/support', [AbonnementControleur::class, 'ouvrir'], ['droit' => 'support.utiliser', 'toujours' => true]);
    $r->ajouter('GET', '/support/{id}', [AbonnementControleur::class, 'ticket'], ['droit' => 'support.utiliser', 'toujours' => true]);
    $r->ajouter('POST', '/support/{id}', [AbonnementControleur::class, 'repondre'], ['droit' => 'support.utiliser', 'toujours' => true]);

    // Espace AvecXY (administrateurs de la plateforme)
    $pf = ['plateforme' => true];
    $r->ajouter('GET', '/plateforme/connexion', [PlateformeControleur::class, 'formulaire'], $pf + ['public' => true]);
    $r->ajouter('POST', '/plateforme/connexion', [PlateformeControleur::class, 'connecter'], $pf + ['public' => true]);
    $r->ajouter('POST', '/plateforme/deconnexion', [PlateformeControleur::class, 'deconnecter'], $pf + ['public' => true]);
    $r->ajouter('GET', '/plateforme', [PlateformeControleur::class, 'index'], $pf);
    $r->ajouter('POST', '/plateforme/organisations', [PlateformeControleur::class, 'ouvrir'], $pf);
    $r->ajouter('GET', '/plateforme/organisations/{id}', [PlateformeControleur::class, 'organisation'], $pf);
    $r->ajouter('POST', '/plateforme/organisations/{id}/activer', [PlateformeControleur::class, 'activer'], $pf);
    $r->ajouter('POST', '/plateforme/organisations/{id}/refuser', [PlateformeControleur::class, 'refuser'], $pf);
    $r->ajouter('POST', '/plateforme/organisations/{id}/nom-court', [PlateformeControleur::class, 'nomCourt'], $pf);
    $r->ajouter('POST', '/plateforme/organisations/{id}/utilisateurs/{utilisateur}/mot-de-passe', [PlateformeControleur::class, 'reinitialiser'], $pf);
    $r->ajouter('POST', '/plateforme/organisations/{id}/statut', [PlateformeControleur::class, 'suspendre'], $pf);
    $r->ajouter('GET', '/plateforme/forfaits', [PlateformeControleur::class, 'forfaits'], $pf);
    $r->ajouter('POST', '/plateforme/forfaits', [PlateformeControleur::class, 'enregistrerForfait'], $pf);
    $r->ajouter('GET', '/plateforme/reglages', [PlateformeControleur::class, 'reglages'], $pf);
    $r->ajouter('POST', '/plateforme/reglages', [PlateformeControleur::class, 'enregistrerReglages'], $pf);
    $r->ajouter('POST', '/plateforme/adresse-mobile', [PlateformeControleur::class, 'enregistrerAdresseMobile'], $pf);
    $r->ajouter('POST', '/plateforme/contact', [PlateformeControleur::class, 'enregistrerContact'], $pf);
    $r->ajouter('POST', '/plateforme/mise-a-jour', [InstallationControleur::class, 'mettreAJour'], $pf);
    $r->ajouter('POST', '/plateforme/mot-de-passe', [PlateformeControleur::class, 'motDePasse'], $pf);
    $r->ajouter('GET', '/plateforme/notifications', [NotificationsControleur::class, 'plateforme'], $pf);
    $r->ajouter('GET', '/plateforme/support', [PlateformeControleur::class, 'support'], $pf);
    $r->ajouter('GET', '/plateforme/support/{id}', [PlateformeControleur::class, 'ticket'], $pf);
    $r->ajouter('POST', '/plateforme/support/{id}', [PlateformeControleur::class, 'repondre'], $pf);

    // Espace client (epargnants) : module du forfait, active par l'organisation
    $ec = ['portail' => true];
    $r->ajouter('GET', '/espace-client/connexion', [PortailControleur::class, 'formulaire'], $ec + ['public' => true]);
    $r->ajouter('POST', '/espace-client/connexion', [PortailControleur::class, 'connecter'], $ec + ['public' => true]);
    $r->ajouter('POST', '/espace-client/deconnexion', [PortailControleur::class, 'deconnecter'], $ec + ['public' => true]);
    $r->ajouter('GET', '/espace-client', [PortailControleur::class, 'accueil'], $ec);
    $r->ajouter('GET', '/espace-client/carnets', [PortailControleur::class, 'carnets'], $ec);
    $r->ajouter('GET', '/espace-client/comptes', [PortailControleur::class, 'comptes'], $ec);
    $r->ajouter('GET', '/espace-client/carnets/{id}', [PortailControleur::class, 'carnet'], $ec);
    $r->ajouter('GET', '/espace-client/historique', [PortailControleur::class, 'historique'], $ec);
    $r->ajouter('GET', '/espace-client/credits', [PortailControleur::class, 'credits'], $ec);
    $r->ajouter('POST', '/espace-client/credits', [PortailControleur::class, 'demanderCredit'], $ec);
    $r->ajouter('GET', '/espace-client/credits/{id}', [PortailControleur::class, 'ficheCredit'], $ec);
    $r->ajouter('GET', '/espace-client/notifications', [NotificationsControleur::class, 'client'], $ec);
    $r->ajouter('GET', '/espace-client/code', [PortailControleur::class, 'code'], $ec + ['toujours' => true]);
    $r->ajouter('POST', '/espace-client/code', [PortailControleur::class, 'changerCode'], $ec + ['toujours' => true]);
    $r->ajouter('POST', '/espace-client/lier', [PortailControleur::class, 'lier'], $ec);
    $r->ajouter('POST', '/espace-client/basculer', [PortailControleur::class, 'basculer'], $ec);
    $r->ajouter('POST', '/clients/{id}/espace-client', [PortailControleur::class, 'ouvrirAcces'], ['droit' => 'espace_client.gerer', 'module' => 'espace_client']);
    $r->ajouter('POST', '/clients/{id}/espace-client/fermer', [PortailControleur::class, 'fermerAcces'], ['droit' => 'espace_client.gerer']);
    $r->ajouter('POST', '/parametres/organisation/espace-client', [PortailControleur::class, 'reglerModule'], ['droit' => 'espace_client.gerer']);
    $r->ajouter('POST', '/parametres/espace-client/demandes', [PortailControleur::class, 'reglerDemandes'], ['droit' => 'espace_client.gerer']);
    $r->ajouter('GET', '/parametres/espace-client', [PortailControleur::class, 'reglage'], ['droit' => 'espace_client.gerer']);
    $r->ajouter('POST', '/parametres/espace-client/generer', [PortailControleur::class, 'generer'], ['droit' => 'espace_client.gerer', 'module' => 'espace_client']);

    // Reprise de l'existant (organisation qui travaillait sur papier) et saisie de clients en masse
    $rp = ['droit' => 'parametres.organisation'];
    $r->ajouter('GET', '/parametres/reprise', [RepriseControleur::class, 'index'], $rp);
    $r->ajouter('GET', '/parametres/reprise/modele', [RepriseControleur::class, 'modele'], $rp);
    $r->ajouter('POST', '/parametres/reprise/analyser', [RepriseControleur::class, 'analyser'], $rp);
    $r->ajouter('POST', '/parametres/reprise/importer', [RepriseControleur::class, 'importer'], $rp);
    $r->ajouter('POST', '/parametres/reprise/abandonner', [RepriseControleur::class, 'abandonner'], $rp);
    $r->ajouter('POST', '/parametres/reprise/vide', [RepriseControleur::class, 'partirDeVide'], $rp);
    $r->ajouter('POST', '/parametres/reprise/{id}/annuler', [RepriseControleur::class, 'annuler'], $rp);
    $r->ajouter('GET', '/clients-en-masse', [ClientsControleur::class, 'masse'], ['droit' => 'clients.creer']);
    $r->ajouter('POST', '/clients-en-masse', [ClientsControleur::class, 'creerEnMasse'], ['droit' => 'clients.creer']);

    // Corbeille
    $r->ajouter('GET', '/parametres/corbeille', [CorbeilleControleur::class, 'index'], ['droit' => 'corbeille.voir']);
    $r->ajouter('POST', '/parametres/corbeille/{type}/{id}/restaurer', [CorbeilleControleur::class, 'restaurer'], ['droit' => 'corbeille.restaurer']);
};

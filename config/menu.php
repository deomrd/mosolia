<?php
// Menu de gauche (accordeon). Un element sans 'chemin' est un module pas encore livre :
// il apparait grise avec la mention "bientot". 'droit' masque l'element a qui ne l'a pas,
// 'module' le masque quand le forfait de l'organisation ne comprend pas ce module.
return [
    ['cle' => 'terrain', 'elements' => [
        ['cle' => 'collecte', 'droit' => 'cotisations.saisir', 'chemin' => '/collecte'],
        ['cle' => 'clients', 'droit' => 'clients.voir', 'chemin' => '/clients'],
        ['cle' => 'clients_masse', 'droit' => 'clients.creer', 'chemin' => '/clients-en-masse'],
        ['cle' => 'carnets', 'droit' => 'carnets.voir', 'chemin' => '/carnets'],
        ['cle' => 'cotisations', 'droit' => ['cotisations.saisir', 'cotisations.valider', 'cotisations.corriger'], 'chemin' => '/cotisations'],
        ['cle' => 'retraits', 'droit' => 'retraits.voir', 'chemin' => '/retraits'],
    ]],
    ['cle' => 'controle', 'elements' => [
        ['cle' => 'controle_carnets', 'droit' => 'carnets.valider', 'chemin' => '/controle/carnets'],
        ['cle' => 'controle_cotisations', 'droit' => 'cotisations.valider', 'chemin' => '/controle/cotisations'],
        ['cle' => 'controle_paiements', 'droit' => 'remboursements.valider', 'chemin' => '/controle/paiements', 'module' => 'credit'],
        ['cle' => 'controle_historique', 'droit' => ['carnets.valider', 'cotisations.valider', 'remboursements.valider'], 'chemin' => '/controle/historique'],
    ]],
    ['cle' => 'credit', 'module' => 'credit', 'elements' => [
        ['cle' => 'credits', 'droit' => 'credits.voir', 'chemin' => '/credits'],
        ['cle' => 'eligibilite', 'droit' => ['eligibilite.proposer', 'eligibilite.decider', 'credits.accorder'], 'chemin' => '/eligibilite'],
        ['cle' => 'remboursements', 'droit' => ['remboursements.saisir', 'remboursements.valider'], 'chemin' => '/remboursements'],
    ]],
    ['cle' => 'caisse', 'elements' => [
        ['cle' => 'journal_caisse', 'droit' => 'caisse.voir', 'chemin' => '/caisse'],
        ['cle' => 'remises', 'droit' => 'remises.gerer', 'chemin' => '/remises'],
        ['cle' => 'paiement_agents', 'droit' => 'agents.payer', 'chemin' => '/paiements-recolteurs'],
        ['cle' => 'repartition', 'droit' => 'repartition.voir', 'chemin' => '/repartition'],
        ['cle' => 'rapports', 'droit' => 'rapports.voir', 'chemin' => '/rapports'],
        ['cle' => 'rapports_gestion', 'droit' => 'rapports.gestion', 'chemin' => '/indicateurs'],
    ]],
    ['cle' => 'comptabilite', 'elements' => [
        ['cle' => 'compta_etats', 'droit' => 'compta.voir', 'chemin' => '/comptabilite/etats'],
        ['cle' => 'compta_balance', 'droit' => 'compta.voir', 'chemin' => '/comptabilite/balance'],
        ['cle' => 'compta_journal', 'droit' => 'compta.voir', 'chemin' => '/comptabilite/journal'],
        ['cle' => 'compta_grand_livre', 'droit' => 'compta.voir', 'chemin' => '/comptabilite/grand-livre'],
        ['cle' => 'compta_ecritures', 'droit' => ['compta.voir', 'compta.saisir'], 'chemin' => '/comptabilite/ecritures'],
        ['cle' => 'compta_plan', 'droit' => ['compta.voir', 'compta.plan'], 'chemin' => '/comptabilite/plan'],
    ]],
    ['cle' => 'parametres', 'elements' => [
        ['cle' => 'organisation', 'droit' => 'parametres.organisation', 'chemin' => '/parametres/organisation'],
        ['cle' => 'types_carnet', 'droit' => 'parametres.carnets', 'chemin' => '/parametres/types-carnet'],
        ['cle' => 'partage', 'droit' => 'parametres.partage', 'chemin' => '/parametres/partage'],
        ['cle' => 'regles_credit', 'droit' => 'parametres.credit', 'chemin' => '/parametres/credit', 'module' => 'credit'],
        ['cle' => 'espace_client', 'droit' => 'espace_client.gerer', 'chemin' => '/parametres/espace-client'],
        ['cle' => 'reprise', 'droit' => 'parametres.organisation', 'chemin' => '/parametres/reprise'],
        ['cle' => 'agences', 'droit' => 'agences.gerer', 'chemin' => '/parametres/agences'],
        ['cle' => 'recolteurs', 'droit' => 'recolteurs.gerer', 'chemin' => '/parametres/recolteurs'],
        ['cle' => 'profils', 'droit' => 'profils.gerer', 'chemin' => '/parametres/profils'],
        ['cle' => 'utilisateurs', 'droit' => 'utilisateurs.gerer', 'chemin' => '/parametres/utilisateurs'],
        ['cle' => 'abonnement', 'droit' => 'abonnement.gerer', 'chemin' => '/abonnement'],
        ['cle' => 'corbeille', 'droit' => 'corbeille.voir', 'chemin' => '/parametres/corbeille'],
        ['cle' => 'mon_compte', 'chemin' => '/mon-compte'],
        ['cle' => 'support', 'droit' => 'support.utiliser', 'chemin' => '/support'],
    ]],
];

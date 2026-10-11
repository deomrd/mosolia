<?php
namespace AvecXY\Domaine;

/**
 * Liste de tous les droits de l'application, ranges par module.
 * Ajouter un droit ici puis lancer bin/migrer.php suffit a le rendre
 * cochable dans les profils. Les libelles sont dans ressources/langues
 * (cles "droit.<code>" et "module.<module>").
 */
final class CatalogueDroits
{
    public const DROITS = [
        'tableau' => ['tableau.voir'],
        'clients' => ['clients.voir', 'clients.voir_tous', 'clients.creer', 'clients.modifier', 'clients.supprimer'],
        'carnets' => ['carnets.voir', 'carnets.vendre', 'carnets.valider', 'carnets.supprimer'],
        'cotisations' => ['cotisations.saisir', 'cotisations.valider', 'cotisations.corriger'],
        'retraits' => ['retraits.voir', 'retraits.faire', 'retraits.annuler'],
        'credits' => [
            'credits.voir', 'credits.accorder', 'credits.deroger', 'eligibilite.proposer', 'eligibilite.decider',
            'remboursements.saisir', 'remboursements.valider',
        ],
        'caisse' => ['caisse.voir', 'caisse.mouvements', 'caisse.transferts', 'remises.gerer', 'agents.payer', 'repartition.voir', 'repartition.payer'],
        'comptabilite' => ['compta.voir', 'compta.saisir', 'compta.plan'],
        'rapports' => ['rapports.voir', 'rapports.gestion', 'montants.benefices', 'audit.voir'],
        'parametres' => [
            'parametres.organisation', 'parametres.carnets', 'parametres.partage', 'parametres.credit',
            'recolteurs.gerer', 'utilisateurs.gerer', 'profils.gerer', 'espace_client.gerer', 'abonnement.gerer', 'agences.gerer',
        ],
        'corbeille' => ['corbeille.voir', 'corbeille.restaurer', 'corbeille.vider'],
        'support' => ['support.utiliser'],
    ];

    /** Profils proposes a l'ouverture d'une organisation (modifiables ensuite). */
    public const PROFILS_MODELES = [
        'ADMIN' => ['nom' => 'ADMINISTRATEUR', 'est_admin' => 1, 'est_agent' => 0, 'droits' => []],
        'COMPTABLE' => ['nom' => 'COMPTABLE', 'est_admin' => 0, 'est_agent' => 0, 'droits' => [
            'tableau.voir', 'clients.voir', 'clients.voir_tous', 'carnets.voir', 'carnets.valider', 'cotisations.valider', 'retraits.voir', 'retraits.annuler',
            'credits.voir', 'eligibilite.decider', 'remboursements.valider', 'caisse.voir', 'repartition.voir',
            'rapports.voir', 'rapports.gestion', 'montants.benefices', 'compta.voir', 'compta.saisir', 'support.utiliser',
        ]],
        'CAISSIER' => ['nom' => 'CAISSIER', 'est_admin' => 0, 'est_agent' => 0, 'droits' => [
            'tableau.voir', 'clients.voir', 'clients.voir_tous', 'carnets.voir', 'carnets.valider', 'cotisations.valider', 'retraits.voir',
            'retraits.faire', 'credits.voir', 'remboursements.valider', 'caisse.voir', 'remises.gerer', 'rapports.voir',
        ]],
        'RECOLTEUR' => ['nom' => 'RECOLTEUR', 'est_admin' => 0, 'est_agent' => 1, 'droits' => [
            'tableau.voir', 'clients.voir', 'clients.creer', 'clients.modifier', 'carnets.voir', 'carnets.vendre',
            'cotisations.saisir', 'credits.voir', 'eligibilite.proposer', 'remboursements.saisir',
        ]],
    ];

    /** @return string[] tous les codes */
    public static function codes(): array
    {
        return array_merge(...array_values(self::DROITS));
    }
}

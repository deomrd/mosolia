<?php
// Donnees necessaires au demarrage : devises, reglages de la plateforme, catalogue des droits
// et un forfait d'essai. Les vrais forfaits se definissent ensuite dans avecxy_plans.

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Noyau\BaseDeDonnees;

return static function (BaseDeDonnees $bd): void {
    foreach ([
        ['CDF', 'Franc congolais', 'FC', 0],
        ['USD', 'Dollar americain', '$', 2],
        ['RWF', 'Franc rwandais', 'FRw', 0],
        ['BIF', 'Franc burundais', 'FBu', 0],
    ] as [$code, $nom, $symbole, $decimales]) {
        $bd->inserer('avecxy_devises', ['code' => $code, 'nom' => $nom, 'symbole' => $symbole, 'decimales' => $decimales, 'actif' => 1]);
    }

    foreach ([
        ['abonnement.jours_rappel', '7', 'ENTIER', 'Jours avant l\'echeance ou le rappel apparait'],
        ['abonnement.jours_grace', '5', 'ENTIER', 'Jours de tolerance apres l\'echeance avant la lecture seule'],
    ] as [$cle, $valeur, $type, $libelle]) {
        $bd->inserer('avecxy_plateforme_parametres', ['cle' => $cle, 'valeur' => $valeur, 'type' => $type, 'libelle' => $libelle, 'modifie_le' => $bd->maintenant()]);
    }

    foreach (CatalogueDroits::DROITS as $module => $codes) {
        foreach ($codes as $code) {
            $bd->inserer('avecxy_permissions', ['code' => $code, 'module' => $module, 'libelle' => $code]);
        }
    }

    $bd->inserer('avecxy_plans', [
        'code' => 'ESSAI', 'nom' => 'Essai gratuit', 'description' => 'Periode d\'essai',
        'prix' => 0, 'devise_code' => 'USD', 'duree_jours' => 14,
        'max_agents' => 2, 'max_clients' => 50, 'max_utilisateurs' => 3,
        'modules' => '{"credit":true}', 'ordre' => 0, 'actif' => 1, 'cree_le' => $bd->maintenant(),
    ]);
};

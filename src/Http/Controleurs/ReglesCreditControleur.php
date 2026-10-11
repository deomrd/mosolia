<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Regles de credit : conditions du pret, limites, eligibilite. Tout est fixe par l'organisation. */
final class ReglesCreditControleur extends Controleur
{
    public const CHAMPS = [
        'nom', 'taux_interet', 'duree_jours', 'taux_penalite', 'base_penalite', 'frequence_penalite', 'jours_tolerance',
        'montant_minimum', 'montant_maximum', 'multiple_epargne_max', 'plafond_encours_pct_caisse', 'credits_simultanes_max',
        'carnets_fermes_minimum', 'anciennete_jours_minimum', 'carnet_actif_requis', 'sans_retard_anterieur',
        'accord_agent_requis', 'validation_manuelle_requise', 'derogation_autorisee', 'actif', 'reconduction', 'remboursement_epargne', 'mode_remboursement', 'echeances_nombre', 'echeances_intervalle',
        'mode_calcul', 'echeances_unite', 'frais_dossier_type', 'frais_dossier_valeur', 'frais_dossier_mode', 'garantie_pct',
    ];

    public function index(): Reponse
    {
        return $this->page('parametres/credit', ['titre' => t('menu.regles_credit'), 'produits' => $this->noyau->credits()->produits($this->contexte()->organisationId())]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire(0, [
            'nom' => '', 'taux_interet' => '10', 'duree_jours' => '30', 'taux_penalite' => '0', 'base_penalite' => 'RESTANT', 'frequence_penalite' => 'MANUELLE',
            'jours_tolerance' => '0', 'montant_minimum' => '', 'montant_maximum' => '', 'multiple_epargne_max' => '', 'plafond_encours_pct_caisse' => '',
            'credits_simultanes_max' => '1', 'carnets_fermes_minimum' => '0', 'anciennete_jours_minimum' => '0', 'carnet_actif_requis' => '0',
            'sans_retard_anterieur' => '1', 'accord_agent_requis' => '1', 'validation_manuelle_requise' => '1', 'derogation_autorisee' => '1', 'actif' => '1', 'reconduction' => '1', 'remboursement_epargne' => '1', 'mode_remboursement' => 'LIBRE', 'echeances_nombre' => '4', 'echeances_intervalle' => '7',
            'mode_calcul' => 'FIXE', 'echeances_unite' => 'JOUR', 'frais_dossier_type' => 'AUCUN', 'frais_dossier_valeur' => '', 'frais_dossier_mode' => 'RETENU', 'garantie_pct' => '',
        ]);
    }

    public function modifier(string $id): Reponse
    {
        $p = $this->noyau->credits()->produit($this->contexte()->organisationId(), (int) $id);
        if ($p === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->formulaire((int) $id, array_map(static fn ($v) => $v === null ? '' : (string) $v, array_intersect_key($p, array_flip(self::CHAMPS))));
    }

    public function creer(): Reponse
    {
        return $this->enregistrer('0');
    }

    public function enregistrer(string $id): Reponse
    {
        $saisie = [];
        foreach (self::CHAMPS as $champ) {
            $saisie[$champ] = $this->requete->texte($champ);
        }
        try {
            $this->noyau->serviceCredits()->enregistrerProduit($this->contexte(), (int) $id, $saisie);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaire((int) $id, $saisie);
        }
        $this->succes('credits.regles_enregistrees');
        return $this->rediriger('/parametres/credit');
    }

    private function formulaire(int $id, array $p): Reponse
    {
        return $this->page('parametres/credit_formulaire', [
            'titre' => t('menu.regles_credit'), 'id' => $id, 'p' => $p,
            'exemple' => $id > 0 ? $this->noyau->serviceCredits()->simulation($this->contexte(), $p, '') : null,
        ]);
    }
}

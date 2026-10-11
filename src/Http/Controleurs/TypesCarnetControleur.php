<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class TypesCarnetControleur extends Controleur
{
    private const CHAMPS = [
        'nom', 'mode', 'duree_jours', 'prix', 'nombre_parts', 'taille_tranche', 'parts_benefice_par_tranche',
        'prelevement_benefice', 'mise_minimum', 'mise_maximum', 'retrait', 'fermeture', 'depot_minimum',
        'frais_tenue_type', 'frais_tenue_valeur', 'frais_tenue_frequence', 'par_defaut', 'actif',
        'blocage', 'taux_base', 'interets_versement', 'retrait_anticipe', 'penalite_anticipe', 'solde_minimum', 'parts_reserve', 'renouvellement',
    ];

    public function index(): Reponse
    {
        return $this->page('parametres/types_carnet', [
            'titre' => t('menu.types_carnet'),
            'types' => $this->noyau->typesCarnet()->liste($this->contexte()->organisationId()),
        ]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire([
            'id' => 0, 'nom' => '', 'mode' => 'PARTS', 'duree_jours' => 31, 'prix' => null, 'nombre_parts' => 31,
            'taille_tranche' => 31, 'parts_benefice_par_tranche' => 1, 'prelevement_benefice' => 'DEBUT_TRANCHE',
            'mise_minimum' => null, 'mise_maximum' => null, 'retrait' => 'TOTAL', 'fermeture' => 'PREMIER_RETRAIT',
            'depot_minimum' => null, 'frais_tenue_type' => 'AUCUN', 'frais_tenue_valeur' => null,
            'frais_tenue_frequence' => 'MENSUEL', 'par_defaut' => 0, 'actif' => 1,
            'blocage' => 'RETRAITS', 'taux_base' => 'AN', 'interets_versement' => 'FIN', 'retrait_anticipe' => 'INTERDIT',
            'penalite_anticipe' => null, 'solde_minimum' => null, 'parts_reserve' => null,
            'options_mois' => ['3', '6', '12'], 'options_taux' => ['', '', ''],
        ]);
    }

    public function modifier(string $id): Reponse
    {
        $t = $this->noyau->typesCarnet()->parId($this->contexte()->organisationId(), (int) $id);
        if ($t === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $options = TypeCarnet::options($t);
        $t['mode'] = TypeCarnet::modeAffiche($t);
        $t['blocage'] ??= 'RETRAITS';
        $t['options_mois'] = array_map(static fn ($o) => (string) $o['mois'], $options);
        $t['options_taux'] = array_map(static fn ($o) => Montant::pourChamp((string) $o['taux']), $options);
        return $this->formulaire($t);
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
        $saisie['actif'] = $this->requete->texte('actif', '0');
        $saisie['options_mois'] = array_map('strval', $this->requete->tableau('options_mois'));
        $saisie['options_taux'] = array_map('strval', $this->requete->tableau('options_taux'));
        try {
            $this->noyau->serviceTypesCarnet()->enregistrer($this->contexte(), (int) $id, $saisie);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaire(['id' => (int) $id] + $saisie, true);
        }
        $this->succes('carnets.enregistre');
        return $this->rediriger('/parametres/types-carnet');
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceTypesCarnet()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('corbeille.envoye', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/types-carnet');
    }

    /** @param bool $brut true quand on reaffiche une saisie refusee (montants tels que tapes) */
    private function formulaire(array $type, bool $brut = false): Reponse
    {
        return $this->page('parametres/type_carnet_formulaire', [
            'titre' => (int) $type['id'] === 0 ? t('carnets.nouveau') : (string) $type['nom'],
            'ty' => $type,
            'brut' => $brut,
            'devise' => $this->contexte()->organisation['devise_symbole'],
        ]);
    }
}

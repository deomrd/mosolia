<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Agences de l'organisation : chacune a ses recolteurs, ses clients et sa caisse. */
final class AgencesControleur extends Controleur
{
    public function index(): Reponse
    {
        $org = $this->contexte()->organisationId();
        return $this->page('parametres/agences', [
            'titre' => t('menu.agences'),
            'agences' => $this->noyau->serviceAgences()->liste($org, true),
            'siege' => $this->noyau->caisse()->solde($org, null, 0),
        ]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire(['id' => 0, 'code' => '', 'nom' => '', 'adresse' => '', 'telephone' => '', 'actif' => 1]);
    }

    public function modifier(string $id): Reponse
    {
        $a = $this->noyau->serviceAgences()->parId($this->contexte()->organisationId(), (int) $id);
        return $a === null ? $this->noyau->pageErreur(404, 'erreur.404') : $this->formulaire($a);
    }

    public function creer(): Reponse
    {
        return $this->enregistrer('0');
    }

    public function enregistrer(string $id): Reponse
    {
        $d = [];
        foreach (['code', 'nom', 'adresse', 'telephone'] as $champ) {
            $d[$champ] = $this->requete->texte($champ);
        }
        $d['actif'] = $this->requete->texte('actif', '0');
        try {
            $this->noyau->serviceAgences()->enregistrer($this->contexte(), (int) $id, $d);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaire(['id' => (int) $id] + $d);
        }
        $this->succes('agences.enregistree');
        return $this->rediriger('/parametres/agences');
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceAgences()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('corbeille.envoye', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/agences');
    }

    private function formulaire(array $a): Reponse
    {
        return $this->page('parametres/agence_formulaire', ['titre' => (int) $a['id'] === 0 ? t('agences.nouvelle') : (string) $a['nom'], 'a' => $a]);
    }
}

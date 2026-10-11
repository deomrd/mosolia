<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class UtilisateursControleur extends Controleur
{
    public function index(): Reponse
    {
        $acces = $this->noyau->session->lire('identifiants_utilisateur');
        $this->noyau->session->oublier('identifiants_utilisateur');
        return $this->page('parametres/utilisateurs', [
            'titre' => t('menu.utilisateurs'), 'acces' => is_array($acces) ? $acces : null,
            'appareils' => $this->noyau->serviceMobile()->appareils($this->contexte()->organisationId()),
            'utilisateurs' => $this->noyau->utilisateurs()->liste($this->contexte()->organisationId()),
        ]);
    }

    /** Desactive un telephone connecte a l'application mobile (perte, vol, depart d'un recolteur). */
    public function desactiverAppareil(string $id): Reponse
    {
        try {
            $this->noyau->serviceMobile()->desactiverAppareil($this->contexte(), (int) $id);
            $this->succes('mobile.desactive');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/utilisateurs');
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire(['id' => 0, 'nom' => '', 'identifiant' => '', 'role_id' => 0, 'statut' => 'ACTIF', 'langue' => ''], []);
    }

    public function modifier(string $id): Reponse
    {
        $u = $this->noyau->utilisateurs()->parId($this->contexte()->organisationId(), (int) $id);
        if ($u === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->formulaire($u, $this->noyau->utilisateurs()->reglesPersonnelles((int) $id));
    }

    public function creer(): Reponse
    {
        return $this->enregistrer('0');
    }

    public function enregistrer(string $id): Reponse
    {
        $donnees = [
            'nom' => $this->requete->texte('nom'),
            'identifiant' => $this->requete->texte('identifiant'),
            'role_id' => $this->requete->entier('role_id'),
            'statut' => $this->requete->texte('statut', 'ACTIF'),
            'langue' => $this->requete->texte('langue'),
            'mot_de_passe' => (string) ($this->requete->post['mot_de_passe'] ?? ''),
        ];
        if (isset($this->requete->post['agence_id'])) {
            $donnees['agence_id'] = $this->requete->entier('agence_id');
        }
        $regles = array_filter($this->requete->tableau('regles'), 'is_string');
        try {
            $this->noyau->serviceUtilisateurs()->enregistrer($this->contexte(), (int) $id, $donnees, $regles);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaire(['id' => (int) $id] + $donnees, array_filter($regles));
        }
        $this->succes('utilisateurs.enregistre');
        if ($this->noyau->serviceUtilisateurs()->acces !== null) {
            $this->noyau->session->ecrire('identifiants_utilisateur', $this->noyau->serviceUtilisateurs()->acces);
        }
        return $this->rediriger('/parametres/utilisateurs');
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceUtilisateurs()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('utilisateurs.supprime', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/utilisateurs');
    }

    private function formulaire(array $utilisateur, array $regles): Reponse
    {
        $profils = $this->noyau->profils()->liste($this->contexte()->organisationId());
        $droitsParProfil = [];
        foreach ($profils as $p) {
            $droitsParProfil[(int) $p['id']] = (int) $p['est_admin'] === 1 ? CatalogueDroits::codes() : $this->noyau->profils()->droits((int) $p['id']);
        }
        return $this->page('parametres/utilisateur_formulaire', [
            'titre' => (int) $utilisateur['id'] === 0 ? t('utilisateurs.nouveau') : (string) $utilisateur['nom'],
            'utilisateur' => $utilisateur,
            'regles' => $regles,
            'profils' => $profils,
            'droitsParProfil' => $droitsParProfil,
            'catalogue' => CatalogueDroits::DROITS,
            'agences' => $this->contexte()->agenceRestreinte() === null ? $this->noyau->serviceAgences()->liste($this->contexte()->organisationId()) : [],
        ]);
    }
}

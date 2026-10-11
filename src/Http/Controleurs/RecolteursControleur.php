<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class RecolteursControleur extends Controleur
{
    public function index(): Reponse
    {
        return $this->page('parametres/recolteurs', [
            'titre' => t('menu.recolteurs'),
            'recolteurs' => $this->noyau->recolteurs()->liste($this->contexte()->organisationId()),
        ]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire(['id' => 0, 'code' => '', 'nom' => '', 'telephone' => '', 'adresse' => '', 'statut' => 'ACTIF'], 0);
    }

    public function modifier(string $id): Reponse
    {
        $r = $this->noyau->recolteurs()->parId($this->contexte()->organisationId(), (int) $id);
        if ($r === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->formulaire($r, (int) ($this->noyau->recolteurs()->utilisateurLie((int) $id)['id'] ?? 0));
    }

    public function creer(): Reponse
    {
        return $this->enregistrer('0');
    }

    public function enregistrer(string $id): Reponse
    {
        $d = [
            'nom' => $this->requete->texte('nom'),
            'telephone' => $this->requete->texte('telephone'),
            'adresse' => $this->requete->texte('adresse'),
            'statut' => $this->requete->texte('statut', 'ACTIF'),
            'utilisateur_id' => $this->requete->entier('utilisateur_id'),
        ];
        if (isset($this->requete->post['agence_id'])) {
            $d['agence_id'] = $this->requete->entier('agence_id');
        }
        try {
            $r = $this->noyau->serviceRecolteurs()->enregistrer($this->contexte(), (int) $id, $d);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            $existant = (int) $id > 0 ? $this->noyau->recolteurs()->parId($this->contexte()->organisationId(), (int) $id) : null;
            return $this->formulaire(['id' => (int) $id, 'code' => $existant['code'] ?? ''] + $d, $d['utilisateur_id']);
        }
        $this->succes($r['nouveau'] ? 'recolteurs.cree' : 'recolteurs.enregistre', ['code' => $r['code']]);
        return $this->rediriger('/parametres/recolteurs');
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceRecolteurs()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('corbeille.envoye', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/recolteurs');
    }

    private function formulaire(array $recolteur, int $utilisateurId): Reponse
    {
        return $this->page('parametres/recolteur_formulaire', [
            'titre' => (int) $recolteur['id'] === 0 ? t('recolteurs.nouveau') : (string) $recolteur['nom'],
            'r' => $recolteur,
            'utilisateurId' => $utilisateurId,
            'comptes' => $this->noyau->recolteurs()->utilisateursRattachables($this->contexte()->organisationId(), (int) $recolteur['id']),
            'agences' => $this->contexte()->agenceRestreinte() === null ? $this->noyau->serviceAgences()->liste($this->contexte()->organisationId()) : [],
        ]);
    }
}

<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class ProfilsControleur extends Controleur
{
    public function index(): Reponse
    {
        return $this->page('parametres/profils', [
            'titre' => t('menu.profils'),
            'profils' => $this->noyau->profils()->liste($this->contexte()->organisationId()),
        ]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaire(['id' => 0, 'nom' => '', 'est_admin' => 0, 'est_agent' => 0, 'systeme' => 0], []);
    }

    public function modifier(string $id): Reponse
    {
        $profil = $this->noyau->profils()->parId($this->contexte()->organisationId(), (int) $id);
        if ($profil === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->formulaire($profil, $this->noyau->profils()->droits((int) $id));
    }

    public function creer(): Reponse
    {
        return $this->enregistrer('0');
    }

    public function enregistrer(string $id): Reponse
    {
        $droits = array_keys(array_filter($this->requete->tableau('droits')));
        try {
            $this->noyau->serviceProfils()->enregistrer(
                $this->contexte(), (int) $id, $this->requete->texte('nom'), $this->requete->texte('est_agent') === '1', $droits
            );
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            $existant = (int) $id > 0 ? $this->noyau->profils()->parId($this->contexte()->organisationId(), (int) $id) : null;
            return $this->formulaire([
                'id' => (int) $id, 'nom' => $this->requete->texte('nom'),
                'est_admin' => (int) ($existant['est_admin'] ?? 0),
                'est_agent' => $this->requete->texte('est_agent') === '1' ? 1 : 0,
                'systeme' => (int) ($existant['systeme'] ?? 0),
            ], $droits);
        }
        $this->succes('profils.enregistre');
        return $this->rediriger('/parametres/profils');
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceProfils()->supprimer($this->contexte(), (int) $id);
            $this->succes('profils.supprime', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/profils');
    }

    private function formulaire(array $profil, array $droits): Reponse
    {
        return $this->page('parametres/profil_formulaire', [
            'titre' => (int) $profil['id'] === 0 ? t('profils.nouveau') : (string) $profil['nom'],
            'profil' => $profil,
            'droits' => $droits,
            'catalogue' => CatalogueDroits::DROITS,
        ]);
    }
}

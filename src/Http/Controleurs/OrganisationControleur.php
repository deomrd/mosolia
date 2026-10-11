<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class OrganisationControleur extends Controleur
{
    public function formulaire(): Reponse
    {
        return $this->afficher($this->contexte()->organisation);
    }

    public function enregistrer(): Reponse
    {
        $champs = ['nom', 'nom_court', 'telephone', 'email', 'adresse', 'ville_defaut', 'langue', 'fuseau', 'couleur_principale'];
        $donnees = [];
        foreach ($champs as $champ) {
            $donnees[$champ] = $this->requete->texte($champ);
        }
        try {
            $this->noyau->serviceOrganisation()->modifierInfos($this->contexte(), $donnees);
            $this->noyau->serviceOrganisation()->reglerDoublons($this->contexte(), $this->requete->texte('doublon_portee', 'RECOLTEUR'), $this->requete->texte('doublon_action', 'DEMANDER'));
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->afficher($donnees + $this->contexte()->organisation);
        }
        $this->succes('organisation.enregistree');
        return $this->rediriger('/parametres/organisation');
    }

    private function afficher(array $organisation): Reponse
    {
        return $this->page('parametres/organisation', [
            'titre' => t('menu.organisation'),
            'o' => $organisation,
            'fuseaux' => \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA),
            'doublonPortee' => $this->noyau->parametres()->lire($this->contexte()->organisationId(), 'clients.doublon_portee'),
            'doublonAction' => $this->noyau->parametres()->lire($this->contexte()->organisationId(), 'clients.doublon_action'),
        ]);
    }
}

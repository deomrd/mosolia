<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceSecurite;
use AvecXY\Domaine\CleAppareil;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class CompteControleur extends Controleur
{
    public function formulaire(): Reponse
    {
        $c = $this->contexte();
        return $this->page('compte/formulaire', [
            'titre' => t('menu.mon_compte'), 'u' => $c->utilisateur,
            'cles' => $this->noyau->serviceSecurite()->cles($c->utilisateurId()), 'clesPossibles' => ServiceSecurite::disponible(),
        ]);
    }

    public function enregistrer(): Reponse
    {
        $p = $this->requete->post;
        $actuel = (string) ($p['mot_de_passe_actuel'] ?? '');
        try {
            // le PIN d'abord : il se verifie avec le mot de passe encore en vigueur
            if ((string) ($p['pin'] ?? '') !== '') {
                $this->noyau->serviceSecurite()->definirPin($this->contexte(), $actuel, (string) $p['pin'], (string) ($p['pin_confirmation'] ?? ''));
            }
            $this->noyau->serviceUtilisateurs()->modifierMonCompte(
                $this->contexte(), $this->requete->texte('nom'), $this->requete->texte('langue'),
                $actuel, (string) ($p['mot_de_passe'] ?? ''), (string) ($p['mot_de_passe_confirmation'] ?? ''),
            );
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/mon-compte');
        }
        $this->succes('compte.enregistre');
        return $this->rediriger('/');
    }

    /** Ajout d'une empreinte ou d'un visage, etape 1 : le defi et les reglages pour l'appareil. */
    public function defiCle(): Reponse
    {
        $c = $this->contexte();
        $s = $this->noyau->serviceSecurite();
        $defi = $s->nouveauDefi();
        $this->noyau->session->ecrire('defi_cle', $defi);
        return Reponse::json([
            'challenge' => $defi, 'rpId' => $this->requete->domaine(), 'rpNom' => $c->organisation['nom'],
            'utilisateur' => ['id' => CleAppareil::versTexte('u' . $c->utilisateurId()), 'nom' => $c->utilisateur['identifiant'], 'affichage' => $c->utilisateur['nom']],
            'exclure' => array_column($s->cles($c->utilisateurId()), 'identifiant_cle'),
        ]);
    }

    public function ajouterCle(): Reponse
    {
        $r = $this->requete;
        $defi = (string) $this->noyau->session->lire('defi_cle', '');
        $this->noyau->session->oublier('defi_cle');
        try {
            $this->noyau->serviceSecurite()->enregistrerCle($this->contexte(), $defi, $r->origine(), $r->domaine(), $r->texte('clientData'), $r->texte('attestation'), $r->texte('nom'));
        } catch (ErreurMetier $e) {
            return Reponse::json(['ok' => false, 'message' => t($e->cle, $e->valeurs)], 400);
        }
        $this->succes('cle.ajoutee');
        return Reponse::json(['ok' => true, 'suite' => url('/mon-compte')]);
    }

    public function supprimerCle(string $id): Reponse
    {
        $this->noyau->serviceSecurite()->supprimerCle($this->contexte(), (int) $id);
        $this->succes('cle.retiree');
        return $this->rediriger('/mon-compte');
    }
}

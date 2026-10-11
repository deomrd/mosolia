<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;
use AvecXY\Noyau\Traducteur;

final class ConnexionControleur extends Controleur
{
    public function formulaire(): Reponse
    {
        if ((int) $this->noyau->session->lire('utilisateur_id', 0) > 0) {
            return $this->rediriger('/');
        }
        return $this->page('connexion/formulaire', ['identifiant' => '', 'mode' => ($this->requete->get['mode'] ?? '') === 'pin' ? 'pin' : 'mot_de_passe'], 'gabarit/public');
    }

    public function connecter(): Reponse
    {
        $identifiant = $this->requete->texte('identifiant');
        try {
            $id = $this->noyau->authentification()->connecter($identifiant, (string) ($this->requete->post['mot_de_passe'] ?? ''), $this->requete->ip);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->page('connexion/formulaire', ['identifiant' => $identifiant, 'mode' => 'mot_de_passe'], 'gabarit/public');
        }
        $u = $this->noyau->utilisateurs()->parIdentifiant(\AvecXY\Domaine\Texte::identifiant($identifiant));
        $this->ouvrirSession($u);
        return $this->rediriger('/');
    }

    /**
     * Passage depuis l'application Mosolia : un code a usage unique ouvre la session (personnel ou espace client)
     * puis mene a la page demandee. Un code inconnu, deja utilise ou perime mene a la connexion habituelle.
     */
    public function passage(string $code): Reponse
    {
        $p = $this->noyau->serviceMobile()->utiliserPassage($code);
        $this->noyau->session->ecrire('dans_application', 1);
        if ($p === null) {
            return $this->rediriger('/connexion');
        }
        if ($p['type'] === 'CLIENT') {
            $this->noyau->session->regenerer();
            $this->noyau->session->oublier('utilisateur_id');
            $this->noyau->session->ecrire('portail_client_id', (int) $p['compte_id']);
            $this->noyau->session->ecrire('dans_application', 1);
            return $this->rediriger((string) $p['cible']);
        }
        $u = $this->noyau->bd()->un("SELECT * FROM avecxy_utilisateurs WHERE id = ? AND statut = 'ACTIF' AND supprime_le IS NULL", [(int) $p['utilisateur_id']]);
        if ($u === null) {
            return $this->rediriger('/connexion');
        }
        $this->ouvrirSession($u);
        $this->noyau->session->ecrire('dans_application', 1);
        return $this->rediriger((string) $p['cible']);
    }

    private function ouvrirSession(array $u): void
    {
        $this->noyau->session->regenerer();
        $this->noyau->session->oublier('plateforme_admin_id');
        $this->noyau->session->oublier('defi_cle');
        $this->noyau->session->ecrire('utilisateur_id', (int) $u['id']);
        $this->noyau->session->ecrire('organisation_id', (int) $u['organisation_id']);
        $this->noyau->session->oublier('langue');
    }

    /** Connexion avec le PIN (bouton de la page de connexion). */
    public function connecterParPin(): Reponse
    {
        $identifiant = $this->requete->texte('identifiant');
        try {
            $u = $this->noyau->serviceSecurite()->connecterParPin($identifiant, (string) ($this->requete->post['pin'] ?? ''), $this->requete->ip);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->page('connexion/formulaire', ['identifiant' => $identifiant, 'mode' => 'pin'], 'gabarit/public');
        }
        $this->ouvrirSession($u);
        return $this->rediriger('/');
    }

    /** Empreinte ou visage, etape 1 : le defi a faire signer par l'appareil. */
    public function defiCle(): Reponse
    {
        $defi = $this->noyau->serviceSecurite()->nouveauDefi();
        $this->noyau->session->ecrire('defi_cle', $defi);
        $this->noyau->session->ecrire('defi_cle_identifiant', \AvecXY\Domaine\Texte::identifiant($this->requete->texte('identifiant')));
        return Reponse::json([
            'challenge' => $defi, 'rpId' => $this->requete->domaine(),
            'cles' => $this->noyau->serviceSecurite()->clesDuCompte($this->requete->texte('identifiant')),
        ]);
    }

    /** Empreinte ou visage, etape 2 : verification de la signature. */
    public function connecterParCle(): Reponse
    {
        $r = $this->requete;
        $defi = (string) $this->noyau->session->lire('defi_cle', '');
        $this->noyau->session->oublier('defi_cle');   // un defi ne sert qu'une fois
        try {
            $u = $this->noyau->serviceSecurite()->connecterParCle($defi, $r->origine(), $r->domaine(), $r->texte('id'), $r->texte('clientData'), $r->texte('donnees'), $r->texte('signature'), (string) $this->noyau->session->lire('defi_cle_identifiant', ''));
        } catch (ErreurMetier $e) {
            return Reponse::json(['ok' => false, 'message' => t($e->cle, $e->valeurs)], 400);
        }
        $this->ouvrirSession($u);
        return Reponse::json(['ok' => true, 'suite' => url('/')]);
    }

    public function deconnecter(): Reponse
    {
        $this->noyau->session->detruire();
        return $this->rediriger('/connexion');
    }

    /** Bouton FR / EN : memorise sur le compte si connecte, sinon dans la session. */
    public function langue(): Reponse
    {
        $langue = $this->requete->texte('langue');
        if (in_array($langue, Traducteur::LANGUES, true)) {
            $uid = (int) $this->noyau->session->lire('utilisateur_id', 0);
            $oid = (int) $this->noyau->session->lire('organisation_id', 0);
            if ($uid > 0 && $oid > 0) {
                $this->noyau->utilisateurs()->modifier($oid, $uid, ['langue' => $langue]);
            } else {
                $this->noyau->session->ecrire('langue', $langue);
            }
        }
        $retour = $this->requete->texte('retour', '/');
        // retour interne uniquement : jamais d'adresse externe
        if (!preg_match('#^/[A-Za-z0-9/_-]*$#', $retour)) {
            $retour = '/';
        }
        return $this->rediriger($retour);
    }
}

<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceSupport;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Cote organisation : abonnement (etat, forfaits, demande d'activation) et support. Toujours accessible, meme en lecture seule. */
final class AbonnementControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $p = $this->noyau->plateforme();
        return $this->page('abonnement/index', [
            'titre' => t('menu.abonnement'), 'usage' => $p->usage($c->organisationId()), 'paiements' => $p->paiements($c->organisationId()),
            'plans' => array_filter($p->plans(true), static fn ($pl) => (float) $pl['prix'] > 0),
            'enAttente' => $p->demandeEnAttente($c->organisationId()),
        ]);
    }

    public function demander(): Reponse
    {
        try {
            $this->noyau->serviceSupport()->demanderForfait($this->contexte(), $this->requete->entier('plan'), $this->requete->texte('moyen'), $this->requete->texte('reference'));
            $this->succes('abonnement.demande_envoyee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/abonnement');
    }

    public function support(): Reponse
    {
        return $this->page('support/index', [
            'titre' => t('menu.support'), 'tickets' => $this->noyau->plateforme()->tickets($this->contexte()->organisationId()), 'categories' => ServiceSupport::CATEGORIES,
        ]);
    }

    public function ouvrir(): Reponse
    {
        $r = $this->requete;
        try {
            $id = $this->noyau->serviceSupport()->ouvrir($this->contexte(), $r->texte('sujet'), $r->texte('categorie'), $r->texte('urgent') === '1', $r->texte('message'));
            $this->succes('support.envoye');
            return $this->rediriger('/support/' . $id);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/support');
        }
    }

    public function ticket(string $id): Reponse
    {
        $t = $this->noyau->plateforme()->ticket((int) $id, $this->contexte()->organisationId());
        return $t === null ? $this->noyau->pageErreur(404, 'erreur.404')
            : $this->page('support/ticket', ['titre' => $t['sujet'], 't' => $t, 'messages' => $this->noyau->plateforme()->messages((int) $id)]);
    }

    public function repondre(string $id): Reponse
    {
        try {
            $this->noyau->serviceSupport()->repondre($this->contexte(), (int) $id, $this->requete->texte('message'), $this->requete->texte('fermer') === '1');
            $this->succes('support.envoye');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/support/' . (int) $id);
    }
}

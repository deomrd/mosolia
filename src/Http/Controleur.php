<?php
namespace AvecXY\Http;

use AvecXY\Application\Contexte;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Noyau\Noyau;
use AvecXY\Noyau\Reponse;
use AvecXY\Noyau\Requete;

/** Base des controleurs : ils lisent la requete, appellent un service, renvoient une page. */
abstract class Controleur
{
    protected Requete $requete;

    public function __construct(protected readonly Noyau $noyau)
    {
        $this->requete = $noyau->requete;
    }

    protected function contexte(): Contexte
    {
        return $this->noyau->contexte ?? throw new \LogicException('Aucun utilisateur connecte.');
    }

    protected function page(string $vue, array $donnees = [], string $gabarit = 'gabarit/application'): Reponse
    {
        return Reponse::html($this->noyau->vue->rendre($vue, $donnees, $gabarit));
    }

    protected function rediriger(string $chemin): Reponse
    {
        return Reponse::redirection(url($chemin));
    }

    protected function succes(string $cle, array $valeurs = []): void
    {
        $this->noyau->session->message('succes', t($cle, $valeurs));
    }

    protected function erreur(ErreurMetier $e): void
    {
        $this->noyau->session->message('erreur', t($e->cle, $e->valeurs));
    }
}

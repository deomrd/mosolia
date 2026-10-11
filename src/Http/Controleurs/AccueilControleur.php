<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Page publique qui presente l'application. Les forfaits viennent de la base. */
final class AccueilControleur extends Controleur
{
    public function index(): Reponse
    {
        $o = $this->noyau->organisations();
        return Reponse::html($this->noyau->vue->rendre('accueil/index', [
            'plans' => $this->noyau->plateforme()->plans(true),
            'contact' => [
                'email' => $o->parametrePlateforme('contact.email', ''), 'telephone' => $o->parametrePlateforme('contact.telephone', ''),
                'whatsapp' => $o->parametrePlateforme('contact.whatsapp', ''),
            ],
        ], 'gabarit/accueil'));
    }
}

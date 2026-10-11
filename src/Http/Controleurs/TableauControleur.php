<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class TableauControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $agent = $c->recolteurRestreint();
        $g = $this->requete->get;
        // une date precise (jour=) ou une periode (du= et au=) ; par defaut le mois en cours
        $jour = Dates::ou((string) ($g['jour'] ?? ''), '');
        $du = $jour !== '' ? $jour : Dates::ou((string) ($g['du'] ?? ''), date('Y-m-01'));
        $au = $jour !== '' ? $jour : Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $credit = $c->module('credit');
        $controles = $this->noyau->controles();
        $attente = [
            'carnets' => Montant::enCentimes($controles->total($org, 'carnets', $agent)),
            'cotisations' => Montant::enCentimes($controles->total($org, 'cotisations', $agent)),
            'paiements' => $credit ? Montant::enCentimes($controles->total($org, 'paiements', $agent)) : 0,
        ];
        return $this->page('tableau/index', [
            'titre' => t('menu.tableau'), 'du' => $du, 'au' => $au,
            'x' => $this->noyau->rapports()->tableau($org, $agent, $du, $au),
            'attente' => $attente, 'credit' => $credit,
            'enMain' => $c->peut('caisse.voir') ? $this->noyau->caisse()->solde($org, null, $c->agenceRestreinte()) : null,
            'echeances' => $credit && $c->peut('credits.voir')
                ? $this->noyau->rapports()->echeancesProches($org, $agent, max(0, (int) $this->noyau->parametres()->lire($org, 'credits.jours_alerte'))) : [],
            'deduire' => $this->noyau->serviceCaisse()->manquantsDeduits($org),
            'recolteur' => $agent !== null && $agent > 0 ? $this->noyau->recolteurs()->parId($org, $agent) : null,
        ]);
    }
}

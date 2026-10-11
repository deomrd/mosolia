<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceCaisse;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Reglage du partage recolteur / organisation et de la repartition mensuelle. */
final class PartageControleur extends Controleur
{
    public function index(): Reponse
    {
        $org = $this->contexte()->organisationId();
        $parSource = array_fill_keys(ServiceCaisse::SOURCES, []);
        foreach ($this->noyau->caisse()->reglesPartage($org) as $r) {
            $parSource[$r['source']][] = $r;
        }
        return $this->page('parametres/partage', [
            'titre' => t('menu.partage'), 'parSource' => $parSource, 'repartitions' => $this->noyau->caisse()->reglesRepartition($org),
            'chargeEmprunts' => $this->noyau->parametres()->lire($org, 'emprunts.charge'), 'reportEmprunts' => $this->noyau->parametres()->lire($org, 'emprunts.report'),
            'regleManquants' => $this->noyau->parametres()->lire($org, 'manquants.regle'),
        ]);
    }

    public function partage(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->definirPartage($this->contexte(), $r->texte('source'), $r->texte('pourcentage'), $r->texte('date'));
            $this->succes('partage.enregistre');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/partage');
    }

    public function manquants(): Reponse
    {
        $this->noyau->serviceCaisse()->reglerManquants($this->contexte(), $this->requete->texte('regle'));
        $this->noyau->serviceCaisse()->reglerCharges($this->contexte(), $this->requete->texte('charge'), $this->requete->texte('report') === '1');
        $this->succes('partage.enregistre');
        return $this->rediriger('/parametres/partage');
    }

    public function repartition(): Reponse
    {
        $r = $this->requete;
        $libelles = $r->tableau('libelle');
        $pcts = $r->tableau('pourcentage');
        $lignes = [];
        foreach ($libelles as $i => $l) {
            $lignes[] = ['libelle' => is_string($l) ? $l : '', 'pourcentage' => is_string($pcts[$i] ?? null) ? $pcts[$i] : ''];
        }
        try {
            $this->noyau->serviceCaisse()->definirRepartition($this->contexte(), $r->texte('nom'), $r->texte('date'), $lignes);
            $this->succes('repartition.regle_enregistree');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/partage');
    }
}

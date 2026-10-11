<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class CarnetsControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $q = trim((string) ($this->requete->get['q'] ?? ''));
        $statut = (string) ($this->requete->get['statut'] ?? 'ACTIF');
        $page = max(1, (int) ($this->requete->get['page'] ?? 1));
        $r = $this->noyau->carnets()->liste($c->organisationId(), $c->recolteurRestreint(), $q, $statut, $page);
        return $this->page('carnets/index', ['titre' => t('menu.carnets'), 'carnets' => $r['lignes'], 'total' => $r['total'], 'page' => $page, 'q' => $q, 'statut' => $statut]);
    }

    public function fiche(string $id): Reponse
    {
        $c = $this->contexte();
        $carnet = $this->noyau->carnets()->parId($c->organisationId(), (int) $id, $c->recolteurRestreint());
        if ($carnet === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        if (($carnet['blocage'] ?? null) !== null && $carnet['statut'] === 'ACTIF') {
            // compte bloque : les interets echus sont verses avant l'affichage (sinon le lendemain, par les travaux du jour)
            $verse = $this->noyau->bd()->transaction(fn (): int => $this->noyau->serviceInterets()->verser($c->organisationId(), (int) $id, $c->decimales()));
            if ($verse > 0) {
                $carnet = $this->noyau->carnets()->parId($c->organisationId(), (int) $id, $c->recolteurRestreint()) ?? $carnet;
            }
        }
        $operations = $this->noyau->operations();
        $cotisations = $operations->cotisationsDuCarnet((int) $id);
        foreach ($cotisations as &$o) {
            $o['modifiable'] = $carnet['statut'] === 'ACTIF' && $this->noyau->serviceCotisations()->peutModifier($c, $o);
        }
        return $this->page('carnets/fiche', [
            'titre' => '#' . $carnet['numero'] . ' ' . $carnet['client_nom'], 'k' => $carnet, 'cotisations' => $cotisations,
            'retraits' => $operations->retraitsDuCarnet((int) $id), 'frais' => $operations->fraisDuCarnet((int) $id),
            'enAttente' => $operations->partsEnAttente((int) $id),
            'retrait' => $this->noyau->serviceRetraits()->proposition($carnet, $c->decimales()),
            'interets' => $operations->interetsDuCarnet((int) $id),
            'couru' => $this->noyau->serviceInterets()->couru($carnet, $c->decimales()),
        ]);
    }

    public function nouveau(): Reponse
    {
        $c = $this->contexte();
        $client = $this->noyau->clients()->parId($c->organisationId(), (int) ($this->requete->get['client'] ?? 0), $c->recolteurRestreint());
        if ($client === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->page('carnets/vendre', [
            'titre' => t('carnets.vendre'), 'client' => $client,
            'types' => array_filter($this->noyau->typesCarnet()->liste($c->organisationId()), static fn ($t) => (int) $t['actif'] === 1),
        ]);
    }

    public function vendre(): Reponse
    {
        $clientId = $this->requete->entier('client_id');
        try {
            $id = $this->noyau->serviceCarnets()->vendre(
                $this->contexte(), $clientId, $this->requete->entier('type_id'), $this->requete->texte('mise'),
                $this->requete->texte('date'), $this->requete->texte('versement'), $this->requete->texte('option_blocage')
            );
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/carnets/nouveau?client=' . $clientId);
        }
        $this->succes('carnets.vendu');
        return $this->rediriger('/carnets/' . $id);
    }

    public function cotiser(string $id): Reponse
    {
        try {
            $r = $this->noyau->serviceCotisations()->saisir($this->contexte(), (int) $id, $this->requete->texte('valeur'), $this->requete->texte('date'), $this->requete->texte('commentaire'));
            $this->succes($r['statut'] === 'VALIDE' ? 'cotisations.enregistree' : 'cotisations.en_attente', ['montant' => argent(Montant::depuisCentimes($r['montant']))]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/carnets/' . (int) $id);
    }

    public function retirer(string $id): Reponse
    {
        try {
            $r = $this->noyau->serviceRetraits()->faire($this->contexte(), (int) $id, $this->requete->texte('date'), $this->requete->texte('montant'), $this->requete->texte('commentaire'));
            $this->succes($r['ferme'] ? 'retraits.fait_ferme' : 'retraits.fait', ['montant' => argent(Montant::depuisCentimes($r['net']))]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/carnets/' . (int) $id);
    }

    public function fermer(string $id): Reponse
    {
        try {
            $this->noyau->serviceCarnets()->fermer($this->contexte(), (int) $id, $this->requete->texte('date', date('Y-m-d')));
            $this->succes('carnets.ferme');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/carnets/' . (int) $id);
    }

    /** Rouvre un carnet cloture a la main, sans retrait (un carnet cloture par un retrait se rouvre en annulant ce retrait). */
    public function rouvrir(string $id): Reponse
    {
        try {
            $this->noyau->serviceCarnets()->rouvrir($this->contexte(), (int) $id);
            $this->succes('carnets.rouvert');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/carnets/' . (int) $id);
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceCarnets()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('corbeille.envoye', ['nom' => $nom]);
            return $this->rediriger('/carnets');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/carnets/' . (int) $id);
        }
    }
}

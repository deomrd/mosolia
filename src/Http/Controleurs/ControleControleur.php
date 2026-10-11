<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceControle;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Infrastructure\DepotControle;
use AvecXY\Noyau\Reponse;

/** Menu Controle : carnets, cotisations, paiements (recolteur par recolteur) et historique. */
final class ControleControleur extends Controleur
{
    private function autorise(string $type): bool
    {
        $c = $this->contexte();
        return isset(ServiceControle::DROITS[$type]) && $c->peut(ServiceControle::DROITS[$type]) && ($type !== 'paiements' || $c->module('credit'));
    }

    public function liste(string $type): Reponse
    {
        if (!$this->autorise($type)) {
            return $this->noyau->pageErreur(403, 'erreur.403');
        }
        $org = $this->contexte()->organisationId();
        $resume = $this->noyau->controles()->resume($org, $type);
        $agent = (int) ($this->requete->get['recolteur'] ?? 0);
        if ($agent === 0 && count($resume) === 1) {
            $agent = (int) $resume[0]['id'];
        }
        return $this->page('controle/liste', [
            'titre' => t('menu.controle_' . $type), 'type' => $type, 'resume' => $resume, 'agent' => $agent,
            'recolteur' => $agent > 0 ? $this->noyau->recolteurs()->parId($org, $agent) : null,
            'lignes' => $agent > 0 ? $this->noyau->controles()->lignes($org, $type, $agent) : [],
        ]);
    }

    public function traiter(string $type): Reponse
    {
        $r = $this->requete;
        $agent = $r->entier('recolteur');
        $valider = $r->texte('action') !== 'REJETER';
        try {
            $res = $this->noyau->serviceControle()->traiter($this->contexte(), $type, $agent, $r->tableau('ids'), $valider, $r->texte('depose'));
            if ($res['valides'] + $res['rejetes'] > 0) {
                $this->succes($valider ? 'controle.valides' : 'controle.rejetes', ['n' => $res['valides'] + $res['rejetes'], 'montant' => argent($res['montant'] / 100)]);
            }
            if ($res['ecart'] !== null && $res['ecart'] !== 0) {
                $this->noyau->session->message($res['ecart'] < 0 ? 'erreur' : 'info', t($res['ecart'] < 0 ? 'collecte.manquant' : 'collecte.excedent', ['montant' => argent(abs($res['ecart']) / 100)]));
            }
            if ($res['erreurs']) {
                $this->noyau->session->message('erreur', implode(' — ', $res['erreurs']));
            }
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/controle/' . (in_array($type, DepotControle::TYPES, true) ? $type : 'cotisations') . '?recolteur=' . $agent);
    }

    public function historique(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $du = Dates::ou((string) ($g['du'] ?? ''), date('Y-m-d'));
        $au = Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $agent = (int) ($g['recolteur'] ?? 0);
        return $this->page('controle/historique', [
            'titre' => t('menu.controle_historique'), 'du' => $du, 'au' => $au, 'agent' => $agent,
            'lignes' => $this->noyau->controles()->historique($c->organisationId(), $du, $au, $agent),
            'recolteurs' => $this->noyau->recolteurs()->liste($c->organisationId()),
        ]);
    }
}

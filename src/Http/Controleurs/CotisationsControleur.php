<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class CotisationsControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $f = [
            'q' => trim((string) ($g['q'] ?? '')), 'statut' => (string) ($g['statut'] ?? ''), 'recolteur' => (int) ($g['recolteur'] ?? 0),
            'du' => Dates::ou((string) ($g['du'] ?? ''), date('Y-m-01')), 'au' => Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d')),
        ];
        $page = max(1, (int) ($g['page'] ?? 1));
        $r = $this->noyau->operations()->cotisations($c->organisationId(), $c->recolteurRestreint(), $f, $page);
        foreach ($r['lignes'] as &$o) {
            $o['modifiable'] = $this->noyau->serviceCotisations()->peutModifier($c, $o);
        }
        return $this->page('cotisations/index', [
            'titre' => t('menu.cotisations'), 'operations' => $r['lignes'], 'total' => $r['total'], 'somme' => $r['somme'], 'page' => $page, 'f' => $f,
            'recolteurs' => $c->recolteurRestreint() === null ? $this->noyau->recolteurs()->liste($c->organisationId()) : [],
        ]);
    }

    /** Collecte du jour : tous les carnets actifs d'un recolteur sur un seul ecran. */
    public function collecte(): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $restreint = $c->recolteurRestreint();
        $agentId = $restreint ?? (int) ($this->requete->get['recolteur'] ?? 0);
        return $this->page('cotisations/collecte', [
            'titre' => t('menu.collecte'), 'agentId' => $agentId, 'restreint' => $restreint !== null,
            'recolteurs' => $restreint === null ? array_filter($this->noyau->recolteurs()->liste($org), static fn ($r) => $r['statut'] === 'ACTIF') : [],
            'carnets' => $agentId > 0 ? $this->noyau->carnets()->actifsDuRecolteur($org, $agentId) : [],
        ]);
    }

    public function collecter(): Reponse
    {
        $agentId = $this->requete->entier('agent_id');
        try {
            $argent = array_key_exists('argent_en_main', $this->requete->post) ? $this->requete->texte('argent_en_main') : null;
            $r = $this->noyau->serviceCotisations()->collecter($this->contexte(), $agentId, $this->requete->texte('date'), $this->requete->tableau('valeurs'), $argent);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/collecte?recolteur=' . $agentId);
        }
        if ($r['nombre'] > 0) {
            $this->succes('collecte.enregistree', ['n' => $r['nombre'], 'total' => argent(Montant::depuisCentimes($r['total']))]);
        }
        if ($r['ecart'] !== null && $r['ecart'] !== 0) {
            $this->noyau->session->message('info', t($r['ecart'] < 0 ? 'collecte.manquant' : 'collecte.excedent', ['montant' => argent(Montant::depuisCentimes(abs($r['ecart'])))]));
        }
        if ($r['erreurs']) {
            $this->noyau->session->message('erreur', implode(' — ', $r['erreurs']));
        } elseif ($r['nombre'] === 0) {
            $this->noyau->session->message('erreur', t('collecte.vide'));
        }
        return $this->rediriger('/collecte?recolteur=' . $agentId);
    }

    public function modifier(string $id): Reponse
    {
        $c = $this->contexte();
        $o = $this->noyau->operations()->cotisation($c->organisationId(), (int) $id, $c->recolteurRestreint());
        if ($o === null || !$this->noyau->serviceCotisations()->peutModifier($c, $o)) {
            return $this->noyau->pageErreur(403, 'erreur.403');
        }
        return $this->page('cotisations/corriger', ['titre' => t('cotisations.corriger'), 'o' => $o]);
    }

    public function corriger(string $id): Reponse
    {
        $o = $this->noyau->operations()->cotisation($this->contexte()->organisationId(), (int) $id);
        try {
            $this->noyau->serviceCotisations()->corriger($this->contexte(), (int) $id, $this->requete->texte('valeur'), $this->requete->texte('date'));
            $this->succes('cotisations.corrigee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/cotisations/' . (int) $id);
        }
        return $this->rediriger($o ? '/carnets/' . $o['carnet_id'] : '/cotisations');
    }

    public function supprimer(string $id): Reponse
    {
        $o = $this->noyau->operations()->cotisation($this->contexte()->organisationId(), (int) $id);
        try {
            $this->noyau->serviceCotisations()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('cotisations.supprimee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger($o ? '/carnets/' . $o['carnet_id'] : '/cotisations');
    }
}

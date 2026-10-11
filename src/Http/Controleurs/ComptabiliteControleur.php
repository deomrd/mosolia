<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceComptabilite;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Comptabilite generale : etats, balance, journal, grand livre, ecritures manuelles, plan de comptes. */
final class ComptabiliteControleur extends Controleur
{
    /** Periode et agence choisies (un utilisateur d'agence est limite a la sienne). */
    private function filtres(): array
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $du = Dates::ou((string) ($g['du'] ?? ''), date('Y-01-01'));
        $au = Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $agences = $c->agenceRestreinte() === null ? $this->noyau->serviceAgences()->liste($c->organisationId()) : [];
        $choix = (string) ($g['agence'] ?? '');
        $agence = $c->agenceRestreinte() ?? ($choix === '' || !$agences ? null : max(0, (int) $choix));
        return ['du' => $du, 'au' => $au, 'agence' => $agence, 'agences' => $agences];
    }

    private function service(): ServiceComptabilite
    {
        return $this->noyau->serviceComptabilite();
    }

    public function etats(): Reponse
    {
        $f = $this->filtres();
        return $this->page('comptabilite/etats', ['titre' => t('menu.compta_etats'), 'f' => $f, 'etats' => $this->service()->etats($this->contexte()->organisationId(), $f['du'], $f['au'], $f['agence'])]);
    }

    public function balance(): Reponse
    {
        $f = $this->filtres();
        return $this->page('comptabilite/balance', ['titre' => t('menu.compta_balance'), 'f' => $f, 'etats' => $this->service()->etats($this->contexte()->organisationId(), $f['du'], $f['au'], $f['agence'])]);
    }

    public function journal(): Reponse
    {
        $f = $this->filtres();
        $org = $this->contexte()->organisationId();
        $this->service()->plan($org);
        return $this->page('comptabilite/journal', ['titre' => t('menu.compta_journal'), 'f' => $f, 'lignes' => $this->noyau->comptabilite()->lignes($org, $f['du'], $f['au'], $f['agence'])]);
    }

    public function grandLivre(): Reponse
    {
        $f = $this->filtres();
        $org = $this->contexte()->organisationId();
        $comptes = $this->service()->plan($org);
        $compteId = (int) ($this->requete->get['compte'] ?? 0) ?: (int) ($comptes[0]['id'] ?? 0);
        $compte = $this->noyau->comptabilite()->compte($org, $compteId);
        $report = 0;
        if ($compte !== null) {
            $t = $this->noyau->comptabilite()->totaux($org, $f['du'], $f['au'], $f['agence'])[$compteId] ?? null;
            $report = $t === null ? 0 : ServiceComptabilite::sens($compte['nature'], \AvecXY\Domaine\Montant::enCentimes($t['avant_debit']), \AvecXY\Domaine\Montant::enCentimes($t['avant_credit']));
        }
        return $this->page('comptabilite/grand_livre', [
            'titre' => t('menu.compta_grand_livre'), 'f' => $f, 'comptes' => $comptes, 'compte' => $compte, 'report' => $report,
            'lignes' => $compte === null ? [] : $this->noyau->comptabilite()->lignes($org, $f['du'], $f['au'], $f['agence'], $compteId),
        ]);
    }

    public function exporter(): Reponse
    {
        $f = $this->filtres();
        $org = $this->contexte()->organisationId();
        $contenu = $this->service()->classeur($org, $f['du'], $f['au'], $f['agence'], (string) $this->contexte()->organisation['nom']);
        return Reponse::fichier('comptabilite_' . $f['du'] . '_' . $f['au'] . '.xlsx', $contenu, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function ecritures(): Reponse
    {
        $f = $this->filtres();
        $org = $this->contexte()->organisationId();
        return $this->page('comptabilite/ecritures', [
            'titre' => t('menu.compta_ecritures'), 'f' => $f, 'comptes' => $this->service()->plan($org, false),
            'ecritures' => $this->noyau->comptabilite()->ecritures($org, $f['du'], $f['au'], $f['agence']),
        ]);
    }

    public function saisir(): Reponse
    {
        $r = $this->requete;
        $comptes = $r->tableau('compte');
        $debits = $r->tableau('debit');
        $credits = $r->tableau('credit');
        $lignes = [];
        foreach ($comptes as $i => $compte) {
            $lignes[] = ['compte_id' => (int) $compte, 'debit' => (string) ($debits[$i] ?? ''), 'credit' => (string) ($credits[$i] ?? '')];
        }
        try {
            $this->service()->saisir($this->contexte(), $r->texte('date'), $r->texte('piece'), $r->texte('libelle'), $r->entier('agence_id'), $lignes);
            $this->succes('compta.ecriture_enregistree');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/comptabilite/ecritures');
    }

    public function supprimerEcriture(string $id): Reponse
    {
        try {
            $this->service()->supprimerEcriture($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('compta.ecriture_supprimee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/comptabilite/ecritures');
    }

    public function plan(): Reponse
    {
        return $this->page('comptabilite/plan', ['titre' => t('menu.compta_plan'), 'comptes' => $this->service()->plan($this->contexte()->organisationId())]);
    }

    public function compte(string $id): Reponse
    {
        $org = $this->contexte()->organisationId();
        $this->service()->plan($org);
        $compte = (int) $id === 0 ? ['id' => 0, 'numero' => '', 'libelle' => '', 'nature' => 'CHARGE', 'actif' => 1, 'cle' => null]
            : $this->noyau->comptabilite()->compte($org, (int) $id);
        return $compte === null ? $this->noyau->pageErreur(404, 'erreur.404')
            : $this->page('comptabilite/compte', ['titre' => (int) $id === 0 ? t('compta.nouveau_compte') : $compte['numero'] . ' ' . $compte['libelle'], 'compte' => $compte]);
    }

    public function enregistrerCompte(string $id): Reponse
    {
        $d = ['numero' => $this->requete->texte('numero'), 'libelle' => $this->requete->texte('libelle'), 'nature' => $this->requete->texte('nature'), 'actif' => $this->requete->texte('actif', '0')];
        try {
            $this->service()->enregistrerCompte($this->contexte(), (int) $id, $d);
            $this->succes('compta.compte_enregistre');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/comptabilite/plan/' . (int) $id);
        }
        return $this->rediriger('/comptabilite/plan');
    }

    public function supprimerCompte(string $id): Reponse
    {
        try {
            $numero = $this->service()->supprimerCompte($this->contexte(), (int) $id);
            $this->succes('compta.compte_supprime', ['numero' => $numero]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/comptabilite/plan');
    }

    public function affecter(): Reponse
    {
        $roles = array_map('intval', array_filter($this->requete->tableau('role'), 'is_string'));
        try {
            $this->service()->affecter($this->contexte(), $roles);
            $this->succes('compta.roles_enregistres');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/comptabilite/plan');
    }
}

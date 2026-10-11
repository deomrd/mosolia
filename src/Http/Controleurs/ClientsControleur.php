<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\DoublonAConfirmer;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class ClientsControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $q = trim((string) ($this->requete->get['q'] ?? ''));
        $filtre = (int) ($this->requete->get['recolteur'] ?? 0);
        $page = max(1, (int) ($this->requete->get['page'] ?? 1));
        $r = $this->noyau->clients()->liste($c->organisationId(), $c->recolteurRestreint(), $q, $filtre, $page);
        return $this->page('clients/index', [
            'titre' => t('menu.clients'), 'clients' => $r['lignes'], 'total' => $r['total'], 'page' => $page, 'q' => $q, 'filtre' => $filtre,
            'recolteurs' => $c->recolteurRestreint() === null ? $this->noyau->recolteurs()->liste($c->organisationId()) : [],
        ]);
    }

    public function nouveau(): Reponse
    {
        return $this->formulaireCreation([
            'nom' => '', 'telephone' => '', 'adresse' => '', 'activite' => '', 'agent_id' => 0,
            'sexe' => '', 'date_naissance' => '', 'lieu_naissance' => '', 'piece_type' => '', 'piece_numero' => '', 'piece_expire_le' => '', 'contact_urgence' => '',
            'type_id' => 0, 'mise' => '', 'date' => date('Y-m-d'), 'versement' => '', 'option_blocage' => '',
        ], []);
    }

    public function creer(): Reponse
    {
        $d = [];
        foreach (array_merge(['nom', 'telephone', 'adresse', 'activite', 'mise', 'date', 'versement', 'option_blocage'], \AvecXY\Application\ServiceClients::CHAMPS_DOSSIER) as $champ) {
            $d[$champ] = $this->requete->texte($champ);
        }
        $d['agent_id'] = $this->requete->entier('agent_id');
        $d['type_id'] = $this->requete->entier('type_id');
        $carnet = $d['type_id'] > 0 ? ['type_id' => $d['type_id'], 'mise' => $d['mise'], 'date' => $d['date'], 'versement' => $d['versement'], 'option_blocage' => $d['option_blocage']] : null;
        try {
            $r = $this->noyau->serviceClients()->creer($this->contexte(), $d, $carnet, $this->requete->texte('doublon_choix'));
        } catch (DoublonAConfirmer $e) {
            return $this->formulaireCreation($d, $e->homonymes);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaireCreation($d, []);
        }
        $this->succes($r['fusionne'] ? 'clients.fusionne' : 'clients.cree', ['code' => $r['code']]);
        if (!$r['fusionne'] && $this->noyau->servicePortail()->actifPour($this->contexte())) {
            // nouveau client : ses identifiants de l'espace client sont crees tout de suite et montres une fois
            try {
                $pin = $this->noyau->servicePortail()->ouvrirAcces($this->contexte(), (int) $r['client_id']);
                $this->noyau->session->ecrire('identifiants_client', ['code' => $r['code'], 'nom' => \AvecXY\Domaine\Texte::nom($d['nom']), 'pin' => $pin]);
            } catch (ErreurMetier) {
                // l'acces pourra etre ouvert depuis la fiche
            }
        }
        return $this->rediriger('/clients/' . $r['client_id']);
    }

    public function fiche(string $id): Reponse
    {
        $c = $this->contexte();
        $client = $this->noyau->clients()->parId($c->organisationId(), (int) $id, $c->recolteurRestreint());
        if ($client === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $identifiants = $this->noyau->session->lire('identifiants_client');
        $this->noyau->session->oublier('identifiants_client');
        return $this->page('clients/fiche', [
            'titre' => $client['code'] . ' ' . $client['nom'], 'client' => $client,
            'identifiants' => is_array($identifiants) && $identifiants['code'] === $client['code'] ? $identifiants : null,
            'carnets' => $this->noyau->carnets()->duClient($c->organisationId(), (int) $id),
            'credits' => $c->module('credit') && $c->peut('credits.voir') ? $this->noyau->credits()->credits($c->organisationId(), null, '', '', (int) $id) : [],
            'espaceClient' => $this->noyau->servicePortail()->actifPour($c) && $c->peut('espace_client.gerer'),
            'acces' => $this->noyau->portail()->acces((int) $id),
        ]);
    }

    public function modifier(string $id): Reponse
    {
        $c = $this->contexte();
        $client = $this->noyau->clients()->parId($c->organisationId(), (int) $id, $c->recolteurRestreint());
        return $client === null ? $this->noyau->pageErreur(404, 'erreur.404')
            : $this->page('clients/modifier', ['titre' => $client['nom'], 'client' => $client]);
    }

    public function enregistrer(string $id): Reponse
    {
        $d = [];
        foreach (array_merge(['nom', 'telephone', 'adresse', 'activite', 'statut'], \AvecXY\Application\ServiceClients::CHAMPS_DOSSIER) as $champ) {
            $d[$champ] = $this->requete->texte($champ);
        }
        try {
            $this->noyau->serviceClients()->modifier($this->contexte(), (int) $id, $d);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/clients/' . (int) $id . '/modifier');
        }
        $this->succes('clients.enregistre');
        return $this->rediriger('/clients/' . (int) $id);
    }

    /** Envoi de la photo du client. */
    public function envoyerPhoto(string $id): Reponse
    {
        try {
            $this->noyau->serviceClients()->photo($this->contexte(), (int) $id, $_FILES['photo'] ?? [], $this->noyau->racine . '/stockage/photos');
            $this->succes('clients.photo_enregistree');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/clients/' . (int) $id);
    }

    /** Photo du client, seulement pour qui peut voir ce client. */
    public function photo(string $id): Reponse
    {
        $c = $this->contexte();
        $client = $this->noyau->clients()->parId($c->organisationId(), (int) $id, $c->recolteurRestreint());
        $chemin = $client && !empty($client['photo']) ? $this->noyau->racine . '/stockage/photos/' . basename((string) $client['photo']) : '';
        if ($chemin === '' || !is_file($chemin)) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($chemin, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
        return Reponse::binaire((string) file_get_contents($chemin), $type);
    }

    public function supprimer(string $id): Reponse
    {
        try {
            $nom = $this->noyau->serviceClients()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('corbeille.envoye', ['nom' => $nom]);
            return $this->rediriger('/clients');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/clients/' . (int) $id);
        }
    }

    // ---------------- plusieurs clients d'un coup ----------------

    public function masse(): Reponse
    {
        return $this->pageMasse(array_fill(0, 15, ['nom' => '', 'telephone' => '', 'agent_id' => 0, 'type_id' => 0, 'mise' => '']), date('Y-m-d'), null);
    }

    public function creerEnMasse(): Reponse
    {
        $c = $this->contexte();
        $date = $this->requete->texte('date', date('Y-m-d'));
        $saisies = $this->requete->tableau('lignes');
        $portail = $this->noyau->servicePortail()->actifPour($c);
        $crees = [];
        $restantes = [];
        foreach ($saisies as $i => $l) {
            if (!is_array($l)) {
                continue;
            }
            $l = ['nom' => trim((string) ($l['nom'] ?? '')), 'telephone' => trim((string) ($l['telephone'] ?? '')), 'agent_id' => (int) ($l['agent_id'] ?? 0),
                'type_id' => (int) ($l['type_id'] ?? 0), 'mise' => trim((string) ($l['mise'] ?? ''))];
            if ($l['nom'] === '') {
                continue;
            }
            try {
                $carnet = $l['type_id'] > 0 ? ['type_id' => $l['type_id'], 'mise' => $l['mise'], 'date' => $date, 'versement' => ''] : null;
                $r = $this->noyau->serviceClients()->creer($c, $l + ['adresse' => '', 'activite' => ''], $carnet);
                $pin = '';
                if ($portail && !$r['fusionne']) {
                    try {
                        $pin = $this->noyau->servicePortail()->ouvrirAcces($c, (int) $r['client_id']);
                    } catch (ErreurMetier) {
                    }
                }
                $crees[] = ['id' => $r['client_id'], 'code' => $r['code'], 'nom' => \AvecXY\Domaine\Texte::nom($l['nom']), 'pin' => $pin, 'fusionne' => $r['fusionne']];
            } catch (DoublonAConfirmer $e) {
                $restantes[] = $l + ['erreur' => t('clients.masse_doublon', ['code' => $e->homonymes[0]['code'] ?? ''])];
            } catch (ErreurMetier $e) {
                $restantes[] = $l + ['erreur' => t($e->cle, $e->valeurs)];
            }
        }
        if (!$crees && !$restantes) {
            $this->noyau->session->message('erreur', t('clients.masse_vide'));
            return $this->rediriger('/clients-en-masse');
        }
        // les lignes en erreur restent affichees pour etre corrigees ; les autres sont enregistrees
        while (count($restantes) < 5) {
            $restantes[] = ['nom' => '', 'telephone' => '', 'agent_id' => 0, 'type_id' => 0, 'mise' => ''];
        }
        return $this->pageMasse($restantes, $date, $crees);
    }

    private function pageMasse(array $lignes, string $date, ?array $crees): Reponse
    {
        $c = $this->contexte();
        return $this->page('clients/masse', [
            'titre' => t('clients.masse'), 'lignes' => $lignes, 'date' => $date, 'crees' => $crees,
            'recolteurs' => $c->recolteurRestreint() === null ? array_filter($this->noyau->recolteurs()->liste($c->organisationId()), static fn ($r) => $r['statut'] === 'ACTIF') : [],
            'types' => array_filter($this->noyau->typesCarnet()->liste($c->organisationId()), static fn ($t) => (int) $t['actif'] === 1),
            'peutImporter' => $c->peut('parametres.organisation'),
        ]);
    }

    private function formulaireCreation(array $d, array $homonymes): Reponse
    {
        $c = $this->contexte();
        return $this->page('clients/formulaire', [
            'titre' => t('clients.nouveau'), 'd' => $d, 'homonymes' => $homonymes,
            'recolteurs' => $c->recolteurRestreint() === null ? array_filter($this->noyau->recolteurs()->liste($c->organisationId()), static fn ($r) => $r['statut'] === 'ACTIF') : [],
            'types' => array_filter($this->noyau->typesCarnet()->liste($c->organisationId()), static fn ($t) => (int) $t['actif'] === 1),
        ]);
    }
}

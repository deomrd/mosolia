<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Espace client : consultation seule. Le compte connecte est dans $noyau->comptePortail. */
final class PortailControleur extends Controleur
{
    private function vue(string $vue, array $donnees = []): Reponse
    {
        return $this->page('portail/' . $vue, $donnees, 'gabarit/portail');
    }

    private function clientId(): int
    {
        return (int) $this->noyau->comptePortail['id'];
    }

    public function formulaire(): Reponse
    {
        return $this->noyau->comptePortail !== null ? $this->rediriger('/espace-client') : $this->vue('connexion', ['code' => '']);
    }

    public function connecter(): Reponse
    {
        $code = strtoupper($this->requete->texte('code'));
        try {
            $id = $this->noyau->servicePortail()->connecter($code, (string) ($this->requete->post['secret'] ?? ''), $this->requete->ip);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->vue('connexion', ['code' => $code]);
        }
        $this->noyau->session->regenerer();
        $this->noyau->session->ecrire('portail_client_id', $id);
        return $this->rediriger('/espace-client');
    }

    public function deconnecter(): Reponse
    {
        $this->noyau->session->oublier('portail_client_id');
        $this->noyau->session->regenerer();
        return $this->rediriger('/espace-client/connexion');
    }

    /** Le forfait de l'organisation comprend-il le credit ? */
    private function credit(): bool
    {
        return !empty($this->noyau->portail()->modulesDuForfait((int) $this->noyau->comptePortail['organisation_id'])['credit']);
    }

    private function demandesPermises(): bool
    {
        return $this->credit() && $this->noyau->parametres()->lire((int) $this->noyau->comptePortail['organisation_id'], 'credits.demande_client') === '1';
    }

    public function accueil(): Reponse
    {
        $p = $this->noyau->portail();
        $carnets = $p->carnets($this->clientId());
        $credits = $this->credit() ? $p->credits($this->clientId()) : [];
        $epargne = 0;
        $du = 0;
        foreach ($carnets as $k) {
            $epargne += $k['statut'] === 'ACTIF' ? Montant::enCentimes($k['epargne']) : 0;
        }
        foreach ($credits as $cr) {
            $du += in_array($cr['statut'], ['EN_COURS', 'EN_RETARD'], true) ? Montant::enCentimes($cr['restant']) : 0;
        }
        return $this->vue('accueil', [
            'carnetsAffiches' => array_slice(array_values(array_filter($carnets, static fn ($k) => $k['statut'] === 'ACTIF')) ?: $carnets, 0, 4),
            'carnets' => $carnets, 'credits' => $credits, 'epargne' => $epargne, 'du' => $du, 'attente' => $p->enAttente($this->clientId()),
            'dernieres' => $p->operations($this->clientId(), 0, 8), 'credit' => $this->credit(),
            'demandes' => $this->credit() ? array_slice($p->demandes($this->clientId()), 0, 3) : [],
            'comptes' => $p->comptesDuGroupe($this->noyau->comptePortail['groupe_id'], $this->clientId()),
        ]);
    }

    public function carnets(): Reponse
    {
        return $this->vue('carnets', ['carnets' => $this->noyau->portail()->carnets($this->clientId())]);
    }

    /** Comptes rattaches : le meme epargnant chez un autre recolteur ou dans une autre organisation. */
    public function comptes(): Reponse
    {
        return $this->vue('comptes', ['comptes' => $this->noyau->portail()->comptesDuGroupe($this->noyau->comptePortail['groupe_id'], $this->clientId())]);
    }

    /** Releve d'un carnet : chaque mouvement de l'epargne, du plus ancien au plus recent, avec le solde apres chacun. */
    public function carnet(string $id): Reponse
    {
        $k = $this->noyau->portail()->carnet($this->clientId(), (int) $id);
        if ($k === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $lignes = array_reverse($this->noyau->portail()->operations($this->clientId(), (int) $id));
        // le meme jour, l'achat du carnet passe avant le premier versement
        usort($lignes, static fn ($x, $y) => [$x['jour'], $x['type'] === 'CARNET' ? 0 : 1] <=> [$y['jour'], $y['type'] === 'CARNET' ? 0 : 1]);
        $mouvement = static fn (array $l): int => $l['statut'] !== 'VALIDE' ? 0 : match ($l['type']) {
            'COTISATION', 'INTERET' => Montant::enCentimes($l['epargne']),
            'RETRAIT', 'FRAIS' => -Montant::enCentimes($l['epargne']),
            default => 0,
        };
        // le solde de depart est celui qui mene exactement a l'epargne d'aujourd'hui (il vaut le solde repris des cahiers, ou zero)
        $solde = Montant::enCentimes($k['epargne']) - array_sum(array_map($mouvement, $lignes));
        $releve = ['ouverture' => $solde, 'lignes' => [], 'entrees' => 0, 'sorties' => 0, 'verse' => 0];
        foreach ($lignes as $l) {
            $m = $mouvement($l);
            $solde += $m;
            $valide = $l['statut'] === 'VALIDE';
            $releve['entrees'] += max(0, $m);
            $releve['sorties'] += max(0, -$m);
            $releve['verse'] += $valide && in_array($l['type'], ['COTISATION', 'CARNET'], true) ? Montant::enCentimes($l['montant']) : 0;
            $releve['lignes'][] = $l + ['mouvement' => $m, 'solde' => $solde, 'valide' => $valide];
        }
        $releve['cloture'] = $solde;
        return $this->vue('carnet', ['k' => $k, 'releve' => $releve]);
    }

    /** Releve general : toutes les operations du client sur une periode, par type, avec les totaux. */
    public function historique(): Reponse
    {
        $g = $this->requete->get;
        $du = \AvecXY\Domaine\Dates::ou((string) ($g['du'] ?? ''), '');
        $au = \AvecXY\Domaine\Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        $type = in_array($g['type'] ?? '', ['COTISATION', 'RETRAIT', 'FRAIS', 'INTERET', 'CARNET', 'REMBOURSEMENT', 'CREDIT'], true) ? $g['type'] : '';
        $lignes = array_values(array_filter($this->noyau->portail()->operations($this->clientId()), static fn ($l) => ($du === '' || $l['jour'] >= $du) && $l['jour'] <= $au && ($type === '' || $l['type'] === $type)));
        $totaux = ['COTISATION' => 0, 'RETRAIT' => 0, 'FRAIS' => 0, 'INTERET' => 0, 'CARNET' => 0, 'REMBOURSEMENT' => 0, 'CREDIT' => 0, 'attente' => 0];
        foreach ($lignes as $l) {
            $totaux[$l['statut'] === 'VALIDE' ? $l['type'] : 'attente'] += Montant::enCentimes($l['montant']);
        }
        return $this->vue('historique', ['historique' => $lignes, 'du' => $du, 'au' => $au, 'type' => $type, 'totaux' => $totaux, 'credit' => $this->credit()]);
    }

    /** Credits, demandes et eligibilite. */
    public function credits(): Reponse
    {
        if (!$this->credit()) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $compte = $this->noyau->comptePortail;
        $org = (int) $compte['organisation_id'];
        $p = $this->noyau->portail();
        $client = $this->noyau->bd()->un('SELECT * FROM avecxy_clients WHERE id = ?', [$this->clientId()]);
        $produits = [];
        foreach ($p->produitsDeCredit($org) as $produit) {
            $produits[] = $produit + ['eligibilite' => $this->noyau->serviceCredits()->eligibiliteDuClient($org, $client, $produit)];
        }
        return $this->vue('credits', [
            'credits' => $p->credits($this->clientId()), 'demandes' => $p->demandes($this->clientId()), 'produits' => $produits,
            'demandesPermises' => $this->demandesPermises(),
        ]);
    }

    public function ficheCredit(string $id): Reponse
    {
        $p = $this->noyau->portail();
        $cr = $this->credit() ? $p->credit($this->clientId(), (int) $id) : null;
        if ($cr === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        // releve du credit : ce qui augmente la dette, ce qui la diminue, et le reste du apres chaque ligne
        $ecritures = [
            ['jour' => $cr['date_credit'], 'type' => 'capital', 'montant' => Montant::enCentimes($cr['montant']), 'valide' => true, 'rang' => 0],
            ['jour' => $cr['date_credit'], 'type' => 'interet', 'montant' => Montant::enCentimes($cr['montant_interet']), 'valide' => true, 'rang' => 1],
        ];
        if (Montant::enCentimes($cr['reprise_rembourse']) > 0) {
            $ecritures[] = ['jour' => $cr['date_credit'], 'type' => 'avant', 'montant' => -Montant::enCentimes($cr['reprise_rembourse']), 'valide' => true, 'rang' => 2];
        }
        foreach ($p->penalitesDuCredit((int) $id) as $pen) {
            $ecritures[] = ['jour' => $pen['date_penalite'], 'type' => (int) $cr['reconduction'] === 1 ? 'reconduction' : 'penalite', 'montant' => Montant::enCentimes($pen['montant']), 'valide' => true, 'rang' => 3];
        }
        foreach ($p->paiementsDuCredit((int) $id) as $r) {
            $ecritures[] = ['jour' => $r['date_paiement'], 'type' => 'paiement', 'montant' => -Montant::enCentimes($r['montant']), 'valide' => $r['statut'] === 'VALIDE', 'rang' => 4];
        }
        $ecritures = array_values(array_filter($ecritures, static fn ($e) => $e['montant'] !== 0));
        usort($ecritures, static fn ($x, $y) => [$x['jour'], $x['rang']] <=> [$y['jour'], $y['rang']]);
        $reste = 0;
        $attente = 0;
        foreach ($ecritures as &$e) {
            if ($e['valide']) {
                $reste += $e['montant'];
            } else {
                $attente -= $e['montant'];
            }
            $e['reste'] = $reste;
        }
        unset($e);
        return $this->vue('credit', [
            'cr' => $cr, 'ecritures' => $ecritures, 'attente' => $attente,
            'calendrier' => \AvecXY\Domaine\Echeancier::situation($p->echeances((int) $id), Montant::enCentimes($cr['total_rembourse']), date('Y-m-d'), (int) $cr['jours_tolerance']),
        ]);
    }

    public function demanderCredit(): Reponse
    {
        $compte = $this->noyau->comptePortail;
        $org = (int) $compte['organisation_id'];
        try {
            if (!$this->demandesPermises()) {
                throw new ErreurMetier('portail.demandes_fermees');
            }
            $r = $this->noyau->serviceCredits()->demanderParClient($org, $this->clientId(), $this->requete->entier('produit'), $this->requete->texte('montant'), (int) $compte['devise_decimales']);
            // le recolteur du client et les personnes qui decident sont prevenus
            $n = $this->noyau->notifications();
            $n->utilisateurs($org, array_merge($n->comptesDuRecolteur($org, (int) $r['client']['agent_id']), $n->comptesAyant($org, ['eligibilite.decider', 'credits.accorder'])),
                'demande_credit_client', ['client' => $r['client']['code'] . ' ' . $r['client']['nom'], 'montant' => argent($r['montant'] / 100), 'produit' => (string) $r['produit']['nom']], '/eligibilite/' . $r['id']);
            $this->succes('portail.demande_envoyee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/espace-client/credits');
    }

    public function code(): Reponse
    {
        return $this->vue('code', ['oblige' => (int) $this->noyau->comptePortail['doit_changer_code'] === 1]);
    }

    public function changerCode(): Reponse
    {
        $p = $this->requete->post;
        try {
            $this->noyau->servicePortail()->changerCode($this->clientId(), (string) ($p['actuel'] ?? ''), (string) ($p['nouveau'] ?? ''), (string) ($p['confirmation'] ?? ''));
            $this->succes('portail.code_change');
            return $this->rediriger('/espace-client');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/espace-client/code');
        }
    }

    public function lier(): Reponse
    {
        try {
            $this->noyau->servicePortail()->lier($this->clientId(), $this->requete->texte('code'), (string) ($this->requete->post['secret'] ?? ''), $this->requete->ip);
            $this->succes('portail.compte_lie');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/espace-client/comptes');
    }

    /** Passe a un autre compte rattache, sans se reconnecter. */
    public function basculer(): Reponse
    {
        $cible = $this->requete->entier('compte');
        foreach ($this->noyau->portail()->comptesDuGroupe($this->noyau->comptePortail['groupe_id'], $this->clientId()) as $compte) {
            if ((int) $compte['id'] === $cible && $this->noyau->servicePortail()->compteUtilisable($compte)) {
                $this->noyau->session->ecrire('portail_client_id', $cible);
            }
        }
        return $this->rediriger('/espace-client');
    }

    // ---------------- cote organisation (fiche client et reglage) ----------------

    public function ouvrirAcces(string $id): Reponse
    {
        try {
            $secret = $this->noyau->servicePortail()->ouvrirAcces($this->contexte(), (int) $id);
            $client = $this->noyau->clients()->parId($this->contexte()->organisationId(), (int) $id);
            // affiches une seule fois, dans une fenetre, pour etre remis au client
            $this->noyau->session->ecrire('identifiants_client', ['code' => $client['code'], 'nom' => $client['nom'], 'pin' => $secret]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/clients/' . (int) $id);
    }

    public function fermerAcces(string $id): Reponse
    {
        try {
            $this->noyau->servicePortail()->fermerAcces($this->contexte(), (int) $id);
            $this->succes('portail.acces_ferme');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/clients/' . (int) $id);
    }

    public function reglerModule(): Reponse
    {
        try {
            $this->noyau->servicePortail()->activerModule($this->contexte(), $this->requete->texte('activer') === '1');
            $this->succes('organisation.enregistree');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/espace-client');
    }

    /** L'organisation autorise ou non les demandes de credit depuis l'espace client. */
    public function reglerDemandes(): Reponse
    {
        $c = $this->contexte();
        $this->noyau->parametres()->ecrire($c->organisationId(), 'credits.demande_client', $this->requete->texte('autoriser') === '1' ? '1' : '0', $c->utilisateurId());
        $this->succes('organisation.enregistree');
        return $this->rediriger('/parametres/espace-client');
    }

    /** Reglage de l'espace client : activation, adresse a donner aux clients, identifiants des anciens clients. */
    public function reglage(): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $liste = $this->noyau->session->lire('identifiants_liste');
        $this->noyau->session->oublier('identifiants_liste');
        $actif = $this->noyau->servicePortail()->actifPour($c);
        return $this->page('parametres/espace_client', [
            'titre' => t('portail.titre'), 'dansForfait' => $c->module('espace_client'), 'actif' => $actif,
            'adresse' => url_absolue('/espace-client'),
            'avec' => $this->noyau->portail()->compterAcces($org), 'sans' => count($this->noyau->portail()->clientsSansAcces($org)),
            'liste' => is_array($liste) ? $liste : null, 'creditDansForfait' => $c->module('credit'),
            'demandesClient' => $this->noyau->parametres()->lire($org, 'credits.demande_client') === '1',
        ]);
    }

    /** Bouton « Generer les identifiants » : pour tous les clients qui n'en ont pas encore. */
    public function generer(): Reponse
    {
        try {
            $r = $this->noyau->servicePortail()->genererPourTous($this->contexte());
            if ($r['liste']) {
                $this->noyau->session->ecrire('identifiants_liste', $r);
                $this->succes('portail.generes', ['n' => count($r['liste'])]);
            } else {
                $this->noyau->session->message('info', t('portail.rien_a_generer'));
            }
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/espace-client');
    }
}

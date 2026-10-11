<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class CreditsControleur extends Controleur
{
    private const DROIT_SUPPRESSION = [
        'credits' => 'credits.accorder', 'penalites' => 'credits.accorder', 'remboursements' => 'remboursements.valider', 'produits-credit' => 'parametres.credit',
    ];

    public function index(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $statut = in_array($g['statut'] ?? 'OUVERTS', ['OUVERTS', 'EN_RETARD', 'REMBOURSE', 'TOUS'], true) ? ($g['statut'] ?? 'OUVERTS') : 'OUVERTS';
        $q = trim((string) ($g['q'] ?? ''));
        return $this->page('credits/index', [
            'titre' => t('menu.credits'), 'statut' => $statut, 'q' => $q,
            'credits' => $this->noyau->credits()->credits($c->organisationId(), $c->recolteurRestreint(), $statut === 'TOUS' ? '' : $statut, mb_strtoupper($q)),
            'totaux' => $this->noyau->credits()->totaux($c->organisationId(), $c->recolteurRestreint()),
        ]);
    }

    /** Formulaire d'octroi. Avec ?client=&produit=&montant= il affiche le controle regle par regle avant de valider. */
    public function nouveau(): Reponse
    {
        $g = $this->requete->get;
        return $this->formulaire([
            'client' => strtoupper(trim((string) ($g['client'] ?? ''))), 'produit' => (int) ($g['produit'] ?? 0),
            'montant' => trim((string) ($g['montant'] ?? '')), 'taux' => trim((string) ($g['taux'] ?? '')), 'duree' => trim((string) ($g['duree'] ?? '')), 'date' => date('Y-m-d'), 'motif_derogation' => '', 'commentaire' => '',
        ]);
    }

    private function formulaire(array $s): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $produits = $this->noyau->credits()->produits($org, true);
        $apercu = null;
        if ($s['client'] !== '' && $s['produit'] > 0 && $s['montant'] !== '') {
            $client = $this->noyau->credits()->clientParCode($org, $s['client'], $c->recolteurRestreint());
            $produit = $this->noyau->credits()->produit($org, $s['produit']);
            try {
                $montant = Montant::enCentimes(Montant::lire($s['montant'], $c->decimales()));
                if ($client === null) {
                    throw new ErreurMetier('credits.client_introuvable');
                }
                if ($produit !== null) {
                    [$taux, $duree] = \AvecXY\Application\ServiceCredits::conditions($produit, $s['taux'], $s['duree']);
                    $plan = \AvecXY\Application\ServiceCredits::plan($produit, $montant, $taux, $s['date'], $c->decimales());
                    $apercu = $this->noyau->serviceCredits()->controler($org, $client, $produit, $montant) + [
                        'client' => $client, 'produit' => $produit, 'montant' => $montant, 'taux' => $taux, 'duree' => $duree,
                        'interet' => $plan['interet'], 'plan' => $plan, 'calendrier' => $plan['echeances'],
                    ];
                }
            } catch (ErreurMetier $e) {
                $this->noyau->session->message('erreur', t($e->cle, $e->valeurs));
            }
        }
        return $this->page('credits/nouveau', ['titre' => t('credits.accorder'), 's' => $s, 'produits' => $produits, 'apercu' => $apercu]);
    }

    public function accorder(): Reponse
    {
        $r = $this->requete;
        $s = [
            'client' => strtoupper($r->texte('client')), 'produit' => $r->entier('produit'), 'montant' => $r->texte('montant'), 'taux' => $r->texte('taux'), 'duree' => $r->texte('duree'),
            'date' => $r->texte('date'), 'motif_derogation' => $r->texte('motif_derogation'), 'commentaire' => $r->texte('commentaire'),
        ];
        try {
            $cr = $this->noyau->serviceCredits()->accorder($this->contexte(), $s['client'], $s['produit'], $s['montant'], $s['date'], $s['motif_derogation'], $s['commentaire'], $s['taux'], $s['duree']);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->formulaire($s);
        }
        $this->succes('credits.accorde', ['numero' => $cr['numero']]);
        return $this->rediriger('/credits/' . $cr['id']);
    }

    public function fiche(string $id): Reponse
    {
        $c = $this->contexte();
        $cr = $this->noyau->credits()->credit($c->organisationId(), (int) $id, $c->recolteurRestreint());
        if ($cr === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        return $this->page('credits/fiche', [
            'titre' => t('credits.credit') . ' #' . $cr['numero'], 'cr' => $cr,
            'remboursements' => $this->noyau->credits()->remboursementsDuCredit((int) $id),
            'penalites' => $this->noyau->credits()->penalitesDuCredit((int) $id),
            'carnetsEpargne' => $this->carnetsPourRembourser($cr),
            'calendrier' => self::calendrier($cr, \AvecXY\Domaine\Echeancier::situation($this->noyau->credits()->echeances((int) $id), Montant::enCentimes($cr['total_rembourse']), date('Y-m-d'), (int) $cr['jours_tolerance'])),
        ]);
    }

    public function rembourser(string $id): Reponse
    {
        $r = $this->requete;
        try {
            $res = $this->noyau->serviceCredits()->rembourser($this->contexte(), (int) $id, $r->texte('montant'), $r->texte('date'), $r->texte('commentaire'));
            $this->succes($res['statut'] === 'VALIDE' ? 'remboursements.enregistre' : 'remboursements.en_attente', ['montant' => argent($res['montant'] / 100)]);
            $cr = $this->noyau->credits()->credit($this->contexte()->organisationId(), (int) $id);
            if ($cr !== null) {
                $this->noyau->notifications()->client($this->contexte()->organisationId(), (int) $cr['client_id'], $res['statut'] === 'VALIDE' ? 'client_paiement_saisi_valide' : 'client_paiement_saisi',
                    ['montant' => argent($res['montant'] / 100), 'reference' => (string) $cr['numero'], 'reste' => argent($cr['restant'])], '/espace-client/credits/' . (int) $id);
            }
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/credits/' . (int) $id);
    }

    /** Carnets ouverts du client avec ce que leur regle permet de retirer, si les regles du credit autorisent ce paiement. */
    private function carnetsPourRembourser(array $cr): array
    {
        $c = $this->contexte();
        $produit = $this->noyau->credits()->ligne(\AvecXY\Infrastructure\DepotCredits::TABLES['produits-credit'], $c->organisationId(), (int) $cr['credit_produit_id']);
        if ((int) ($produit['remboursement_epargne'] ?? 0) !== 1 || !$c->peut('retraits.faire') || !in_array($cr['statut'], ['EN_COURS', 'EN_RETARD'], true)) {
            return [];
        }
        $liste = [];
        foreach ($this->noyau->carnets()->duClient($c->organisationId(), (int) $cr['client_id']) as $k) {
            if ($k['statut'] === 'ACTIF' && (float) $k['epargne'] > 0) {
                $liste[] = $k + ['proposition' => $this->noyau->serviceRetraits()->proposition($k, $c->decimales())];
            }
        }
        return $liste;
    }

    public function rembourserParEpargne(string $id): Reponse
    {
        $r = $this->requete;
        try {
            $res = $this->noyau->serviceCredits()->rembourserParEpargne($this->contexte(), (int) $id, $r->entier('carnet'), $r->texte('date', date('Y-m-d')), $r->texte('montant'));
            $this->succes($res['remis'] > 0 ? 'credits.epargne_utilisee_surplus' : 'credits.epargne_utilisee', ['montant' => argent($res['rembourse'] / 100), 'remis' => argent($res['remis'] / 100)]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/credits/' . (int) $id);
    }

    public function penaliser(string $id): Reponse
    {
        try {
            $m = $this->noyau->serviceCredits()->penaliser($this->contexte(), (int) $id, $this->requete->texte('date'), $this->requete->texte('montant'));
            $this->succes('credits.penalite_ajoutee', ['montant' => argent($m / 100)]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/credits/' . (int) $id);
    }

    public function supprimer(string $type, string $id): Reponse
    {
        $droit = self::DROIT_SUPPRESSION[$type] ?? null;
        if ($droit === null || !$this->contexte()->peut($droit)) {
            return $this->noyau->pageErreur(403, 'erreur.403');
        }
        try {
            $this->noyau->serviceCredits()->supprimer($this->contexte(), $type, (int) $id, $this->requete->texte('motif'));
            $this->succes('caisse.ligne_supprimee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        $credit = $this->requete->entier('credit');
        return $this->rediriger($type === 'produits-credit' ? '/parametres/credit' : ($type === 'credits' || $credit === 0 ? '/credits' : '/credits/' . $credit));
    }

    // ---------------- demandes ----------------

    public function demandes(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $etat = in_array($g['etat'] ?? '', ['EN_ATTENTE', 'PARTIEL', 'TOTAL', 'TOUTES'], true) ? $g['etat'] : 'EN_ATTENTE';
        $q = trim((string) ($g['q'] ?? ''));
        $demandes = $this->noyau->credits()->demandes($c->organisationId(), $c->recolteurRestreint(), $etat === 'TOUTES' ? '' : $etat, mb_strtoupper($q));
        foreach ($demandes as &$d) {
            $d['eligible'] = !array_filter(json_decode((string) $d['controle'], true) ?: [], static fn ($l) => !$l['ok']);
        }
        return $this->page('credits/demandes', [
            'titre' => t('menu.eligibilite'), 'demandes' => $demandes, 'etat' => $etat, 'q' => $q,
            'produits' => $this->noyau->credits()->produits($c->organisationId(), true),
        ]);
    }

    public function proposer(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCredits()->proposer($this->contexte(), $r->texte('client'), $r->entier('produit'), $r->texte('montant'), $r->texte('commentaire'));
            $this->succes('eligibilite.proposee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/eligibilite');
    }

    /** Une demande ouverte : tous les details, et la decision pour qui en a le droit. */
    public function demande(string $id): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $d = $this->noyau->credits()->demandeDetail($org, (int) $id, $c->recolteurRestreint());
        if ($d === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $produit = $this->noyau->credits()->ligne(\AvecXY\Infrastructure\DepotCredits::TABLES['produits-credit'], $org, (int) $d['credit_produit_id']);
        $controle = json_decode((string) $d['controle'], true) ?: [];
        $faits = null;
        if ($d['statut'] === 'PROPOSE') {
            // controle refait a l'ouverture : la situation du client a pu changer depuis la demande
            $client = $this->noyau->bd()->un('SELECT * FROM avecxy_clients WHERE id = ?', [$d['client_id']]);
            $r = $this->noyau->serviceCredits()->controler($org, $client, $produit, Montant::enCentimes($d['montant_demande']));
            $controle = $r['lignes'];
            $faits = $r['faits'];
        }
        return $this->page('credits/demande', [
            'titre' => t('eligibilite.demande') . ' ' . $d['client_code'], 'd' => $d, 'produit' => $produit, 'controle' => $controle, 'faits' => $faits,
            'eligible' => !array_filter($controle, static fn ($l) => !$l['ok']),
            'peutDecider' => $c->peut('eligibilite.decider') || $c->peut('credits.accorder'),
        ]);
    }

    public function decider(string $id): Reponse
    {
        $r = $this->requete;
        try {
            $demande = $this->noyau->credits()->demande($this->contexte()->organisationId(), (int) $id);
            if ($r->texte('action') === 'REFUSER') {
                $this->noyau->serviceCredits()->refuser($this->contexte(), (int) $id, $r->texte('motif'));
                if ($demande !== null) {
                    $this->noyau->notifications()->client($this->contexte()->organisationId(), (int) $demande['client_id'], 'client_demande_refusee',
                        ['montant' => argent($demande['montant_demande']), 'motif' => $r->texte('motif') !== '' ? $r->texte('motif') : '—'], '/espace-client/credits');
                }
                $this->succes('eligibilite.rejetee');
                return $this->rediriger('/eligibilite');
            }
            $cr = $this->noyau->serviceCredits()->accorder($this->contexte(), '', 0, $r->texte('montant'), $r->texte('date', date('Y-m-d')), $r->texte('motif_derogation'), $r->texte('commentaire'), $r->texte('taux'), $r->texte('duree'), (int) $id);
            if ($demande !== null) {
                $accorde = $this->noyau->credits()->credit($this->contexte()->organisationId(), (int) $cr['id']);
                $this->noyau->notifications()->client($this->contexte()->organisationId(), (int) $demande['client_id'], 'client_credit_accorde',
                    ['montant' => argent($accorde['montant']), 'numero' => (string) $cr['numero'], 'echeance' => jour($accorde['date_echeance']), 'total' => argent($accorde['total_du'])], '/espace-client/credits/' . $cr['id']);
            }
            $this->succes('credits.accorde', ['numero' => $cr['numero']]);
            return $this->rediriger('/credits/' . $cr['id']);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/eligibilite/' . (int) $id);
        }
    }

    // ---------------- remboursements ----------------

    /** Uniquement les personnes qui ont commence a rembourser ; on ouvre leur credit pour voir l'historique et payer le reste. */
    public function remboursements(): Reponse
    {
        $c = $this->contexte();
        $q = mb_strtoupper(trim((string) ($this->requete->get['q'] ?? '')));
        return $this->page('credits/remboursements', [
            'titre' => t('menu.remboursements'), 'q' => $q,
            'lignes' => $this->noyau->credits()->enRemboursement($c->organisationId(), $c->recolteurRestreint(), $q),
        ]);
    }

    /** Lignes du calendrier en centimes, avec le capital restant du apres chaque echeance (tableau d'amortissement). */
    private static function calendrier(array $cr, array $situation): array
    {
        $restant = Montant::enCentimes($cr['montant']);
        foreach ($situation['lignes'] as &$l) {
            $capital = $l['capital'] === null ? null : Montant::enCentimes($l['capital']);
            if ($capital !== null && $restant !== null) {
                $restant -= $capital;
            } else {
                $restant = null;
            }
            $l = ['numero' => (int) $l['numero'], 'date_prevue' => $l['date_prevue'], 'capital' => $capital,
                'interet' => $l['interet'] === null ? null : Montant::enCentimes($l['interet']), 'montant' => Montant::enCentimes($l['montant']),
                'restant' => $restant, 'paye' => $l['paye'], 'etat' => $l['etat'], 'id' => $l['id'] ?? null];
        }
        return $situation;
    }
}

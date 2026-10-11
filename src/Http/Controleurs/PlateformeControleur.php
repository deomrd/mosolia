<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServicePlateforme;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\EtatAbonnement;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Espace AvecXY : organisations, forfaits, abonnements, support. Reserve aux administrateurs de la plateforme. */
final class PlateformeControleur extends Controleur
{
    private function vue(string $vue, array $donnees = []): Reponse
    {
        return $this->page('plateforme/' . $vue, $donnees, 'gabarit/plateforme');
    }

    private function adminId(): int
    {
        return (int) $this->noyau->adminPlateforme['id'];
    }

    /** Execute l'action, affiche le message, revient a la page. */
    private function agir(callable $action, string $succes, string $retour, array $valeurs = []): Reponse
    {
        try {
            $r = $action();
            $this->succes($succes, $valeurs + (is_string($r) ? ['date' => jour($r)] : []));
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger($retour);
    }

    // ---------------- connexion ----------------

    public function formulaire(): Reponse
    {
        return $this->noyau->adminPlateforme !== null ? $this->rediriger('/plateforme') : $this->vue('connexion', ['email' => '']);
    }

    public function connecter(): Reponse
    {
        $email = $this->requete->texte('email');
        try {
            $id = $this->noyau->servicePlateforme()->connecter($email, (string) ($this->requete->post['mot_de_passe'] ?? ''), $this->requete->ip);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->vue('connexion', ['email' => $email]);
        }
        // une seule identite par session : on quitte tout compte d'organisation ouvert dans ce navigateur
        $this->noyau->session->regenerer();
        $this->noyau->session->oublier('utilisateur_id');
        $this->noyau->session->oublier('organisation_id');
        $this->noyau->session->ecrire('plateforme_admin_id', $id);
        return $this->rediriger('/plateforme');
    }

    public function deconnecter(): Reponse
    {
        $this->noyau->session->detruire();
        return $this->rediriger('/plateforme/connexion');
    }

    public function motDePasse(): Reponse
    {
        $r = $this->requete;
        return $this->agir(fn () => $this->noyau->servicePlateforme()->changerMotDePasse($this->adminId(), (string) ($r->post['actuel'] ?? ''), (string) ($r->post['nouveau'] ?? '')), 'compte.enregistre', '/plateforme/reglages');
    }

    // ---------------- organisations ----------------

    private function etat(array $o): EtatAbonnement
    {
        $org = $this->noyau->organisations();
        return EtatAbonnement::calculer(
            $org->dernierAbonnement((int) $o['id']), (string) $o['statut'], date('Y-m-d'),
            (int) $org->parametrePlateforme('abonnement.jours_rappel', '7'), (int) $org->parametrePlateforme('abonnement.jours_grace', '5')
        );
    }

    public function index(): Reponse
    {
        $organisations = $this->noyau->plateforme()->organisations();
        foreach ($organisations as &$o) {
            $o['abonnement'] = $this->noyau->organisations()->dernierAbonnement((int) $o['id']);
            $o['etat'] = $this->etat($o);
        }
        $etats = array_count_values(array_map(static fn ($o) => $o['etat']->etat, $organisations));
        $toutes = count($organisations);
        // filtres de la liste : texte, etat, forfait, bientot expirees
        $g = $this->requete->get;
        $f = ['q' => mb_strtoupper(trim((string) ($g['q'] ?? ''))), 'etat' => (string) ($g['etat'] ?? ''), 'forfait' => (int) ($g['forfait'] ?? 0)];
        $liste = array_values(array_filter($organisations, static function ($o) use ($f): bool {
            if ($f['q'] !== '' && !str_contains(mb_strtoupper($o['nom'] . ' ' . $o['code'] . ' ' . ($o['nom_court'] ?? '')), $f['q'])) {
                return false;
            }
            if ($f['forfait'] > 0 && (int) ($o['abonnement']['plan_id'] ?? 0) !== $f['forfait']) {
                return false;
            }
            return match ($f['etat']) {
                '' => true,
                'DEMANDE' => (int) $o['demandes'] > 0,
                'EXPIRE_BIENTOT' => $o['etat']->etat === 'RAPPEL' || ($o['etat']->joursRestants !== null && $o['etat']->joursRestants >= 0 && $o['etat']->joursRestants <= 15),
                'BLOQUEE' => in_array($o['etat']->etat, ['LECTURE_SEULE', 'SUSPENDU'], true),
                default => $o['etat']->etat === $f['etat'],
            };
        }));
        return $this->vue('index', [
            'f' => $f, 'toutes' => $toutes, 'totalClients' => array_sum(array_column($organisations, 'clients')), 'totalUtilisateurs' => array_sum(array_column($organisations, 'utilisateurs')),
            'etats' => $etats, 'demandes' => array_sum(array_column($organisations, 'demandes')), 'encaisse' => $this->noyau->plateforme()->encaisseDuMois(),
            'organisations' => $liste, 'ticketsOuverts' => $this->noyau->plateforme()->compterTicketsOuverts(),
            'plans' => $this->noyau->plateforme()->plans(true), 'devises' => $this->noyau->organisations()->devises(),
        ]);
    }

    public function ouvrir(): Reponse
    {
        $r = $this->requete;
        return $this->agir(function () use ($r): void {
            $this->noyau->serviceOrganisation()->ouvrir(
                $r->texte('nom'), $r->texte('devise'), $r->texte('admin_nom'), $r->texte('identifiant'), (string) ($r->post['mot_de_passe'] ?? ''), $r->texte('forfait'), $r->texte('langue', 'fr'), $r->texte('nom_court')
            );
        }, 'plateforme.organisation_creee', '/plateforme');
    }

    public function nomCourt(string $id): Reponse
    {
        return $this->agir(function () use ($id): void {
            if ($this->noyau->organisations()->parId((int) $id) === null) {
                throw new ErreurMetier('plateforme.organisation_introuvable');
            }
            $court = $this->noyau->serviceOrganisation()->definirNomCourt((int) $id, $this->requete->texte('nom_court'));
            $this->noyau->plateforme()->noter((int) $this->noyau->adminPlateforme['id'], (int) $id, 'plateforme.nom_court', 'organisation', (int) $id, ['nom_court' => $court]);
        }, 'organisation.enregistree', '/plateforme/organisations/' . (int) $id);
    }

    public function reinitialiser(string $id, string $utilisateur): Reponse
    {
        try {
            $acces = $this->noyau->servicePlateforme()->reinitialiserMotDePasse((int) $this->noyau->adminPlateforme['id'], (int) $id, (int) $utilisateur);
            $this->noyau->session->ecrire('identifiants_utilisateur', $acces);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/plateforme/organisations/' . (int) $id);
    }

    public function organisation(string $id): Reponse
    {
        $o = $this->noyau->organisations()->parId((int) $id);
        if ($o === null) {
            return $this->noyau->pageErreur(404, 'erreur.404');
        }
        $p = $this->noyau->plateforme();
        $acces = $this->noyau->session->lire('identifiants_utilisateur');
        $this->noyau->session->oublier('identifiants_utilisateur');
        $admin = $this->noyau->utilisateurs();
        return $this->vue('organisation', [
            'activite' => $p->activite((int) $id), 'utilisateurs' => $p->utilisateurs((int) $id), 'journal' => $p->journal((int) $id),
            'caisse' => $this->noyau->caisse()->solde((int) $id), 'acces' => is_array($acces) ? $acces : null,
            'nomCourtPropose' => empty($o['nom_court']) ? $this->noyau->serviceOrganisation()->nomCourtLibre((string) $o['nom'], (int) $id) : '',
            'o' => $o, 'etat' => $this->etat($o), 'abonnement' => $this->noyau->organisations()->dernierAbonnement((int) $id),
            'abonnements' => $p->abonnements((int) $id), 'paiements' => $p->paiements((int) $id), 'usage' => $p->usage((int) $id),
            'plans' => $p->plans(true), 'tickets' => $p->tickets((int) $id),
        ]);
    }

    public function activer(string $id): Reponse
    {
        $r = $this->requete;
        return $this->agir(fn () => $this->noyau->servicePlateforme()->activer($this->adminId(), (int) $id, $r->entier('plan'), $r->texte('montant'), $r->texte('moyen'), $r->texte('note'), $r->entier('paiement')),
            'plateforme.active', '/plateforme/organisations/' . (int) $id);
    }

    public function refuser(string $id): Reponse
    {
        $r = $this->requete;
        return $this->agir(fn () => $this->noyau->servicePlateforme()->refuserDemande($this->adminId(), (int) $id, $r->entier('paiement'), $r->texte('note')), 'plateforme.demande_refusee', '/plateforme/organisations/' . (int) $id);
    }

    public function suspendre(string $id): Reponse
    {
        $suspendre = $this->requete->texte('action') === 'suspendre';
        return $this->agir(fn () => $this->noyau->servicePlateforme()->suspendre($this->adminId(), (int) $id, $suspendre, $this->requete->texte('motif')),
            $suspendre ? 'plateforme.suspendue' : 'plateforme.reactivee', '/plateforme/organisations/' . (int) $id);
    }

    // ---------------- forfaits et reglages ----------------

    public function forfaits(): Reponse
    {
        $plans = $this->noyau->plateforme()->plans();
        $id = (int) ($this->requete->get['modifier'] ?? 0);
        $actuel = ['id' => 0, 'code' => '', 'nom' => '', 'description' => '', 'prix' => '', 'devise_code' => 'USD', 'duree_jours' => 30, 'max_agents' => '', 'max_clients' => '', 'max_utilisateurs' => '', 'modules' => '{}', 'ordre' => 0, 'actif' => 1];
        foreach ($plans as $p) {
            if ((int) $p['id'] === $id) {
                $actuel = $p;
            }
        }
        return $this->vue('forfaits', ['plans' => $plans, 'f' => $actuel, 'devises' => $this->noyau->organisations()->devises(), 'modules' => ServicePlateforme::MODULES]);
    }

    public function enregistrerForfait(): Reponse
    {
        $d = $this->requete->post;
        $d['modules'] = $this->requete->tableau('modules');
        return $this->agir(fn () => $this->noyau->servicePlateforme()->enregistrerForfait($this->adminId(), $this->requete->entier('id'), $d), 'plateforme.forfait_enregistre', '/plateforme/forfaits');
    }

    public function reglages(): Reponse
    {
        $o = $this->noyau->organisations();
        return $this->vue('reglages', ['contact' => ['email' => $o->parametrePlateforme('contact.email', ''), 'telephone' => $o->parametrePlateforme('contact.telephone', ''), 'whatsapp' => $o->parametrePlateforme('contact.whatsapp', '')], 'rappel' => $o->parametrePlateforme('abonnement.jours_rappel', '7'), 'grace' => $o->parametrePlateforme('abonnement.jours_grace', '5'),
            'enAttente' => $this->noyau->serviceInstallation()->enAttente(), 'adresseMobile' => $o->parametrePlateforme('mobile.adresse', '')]);
    }

    public function enregistrerReglages(): Reponse
    {
        return $this->agir(fn () => $this->noyau->servicePlateforme()->reglages($this->adminId(), $this->requete->texte('rappel'), $this->requete->texte('grace')), 'plateforme.reglages_enregistres', '/plateforme/reglages');
    }

    public function enregistrerAdresseMobile(): Reponse
    {
        return $this->agir(fn () => $this->noyau->servicePlateforme()->adresseMobile($this->adminId(), $this->requete->texte('adresse')), 'plateforme.reglages_enregistres', '/plateforme/reglages');
    }

    public function enregistrerContact(): Reponse
    {
        $r = $this->requete;
        return $this->agir(fn () => $this->noyau->servicePlateforme()->contact($this->adminId(), $r->texte('email'), $r->texte('telephone'), $r->texte('whatsapp')), 'plateforme.reglages_enregistres', '/plateforme/reglages');
    }

    // ---------------- support ----------------

    public function support(): Reponse
    {
        $tous = ($this->requete->get['tous'] ?? '') === '1';
        return $this->vue('support', ['tickets' => $this->noyau->plateforme()->tickets(null, !$tous), 'tous' => $tous]);
    }

    public function ticket(string $id): Reponse
    {
        $t = $this->noyau->plateforme()->ticket((int) $id);
        return $t === null ? $this->noyau->pageErreur(404, 'erreur.404') : $this->vue('ticket', ['t' => $t, 'messages' => $this->noyau->plateforme()->messages((int) $id)]);
    }

    public function repondre(string $id): Reponse
    {
        return $this->agir(fn () => $this->noyau->servicePlateforme()->repondre($this->adminId(), (int) $id, $this->requete->texte('message'), $this->requete->texte('resolu') === '1'), 'support.envoye', '/plateforme/support/' . (int) $id);
    }
}

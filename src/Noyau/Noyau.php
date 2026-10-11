<?php
namespace AvecXY\Noyau;

use AvecXY\Application\Audit;
use AvecXY\Application\Authentification;
use AvecXY\Application\Contexte;
use AvecXY\Application\RecalculCarnet;
use AvecXY\Application\ServiceCaisse;
use AvecXY\Application\ServiceCarnets;
use AvecXY\Application\ServiceClients;
use AvecXY\Application\ServiceControle;
use AvecXY\Application\ServiceCotisations;
use AvecXY\Application\ServiceCredits;
use AvecXY\Application\ServiceInstallation;
use AvecXY\Application\ServiceMobile;
use AvecXY\Application\ServiceNotifications;
use AvecXY\Application\ServiceOrganisation;
use AvecXY\Application\ServicePlateforme;
use AvecXY\Application\ServicePortail;
use AvecXY\Application\ServiceSupport;
use AvecXY\Application\ServicePartage;
use AvecXY\Application\ServiceProfils;
use AvecXY\Application\ServiceRecolteurs;
use AvecXY\Application\ServiceReprise;
use AvecXY\Application\ServiceAgences;
use AvecXY\Application\ServiceComptabilite;
use AvecXY\Application\ServiceIndicateurs;
use AvecXY\Infrastructure\DepotComptabilite;
use AvecXY\Application\ServiceInterets;
use AvecXY\Application\ServiceRetraits;
use AvecXY\Application\ServiceSecurite;
use AvecXY\Application\ServiceTaches;
use AvecXY\Application\ServiceTypesCarnet;
use AvecXY\Application\ServiceUtilisateurs;
use AvecXY\Domaine\EtatAbonnement;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotClients;
use AvecXY\Infrastructure\DepotControle;
use AvecXY\Infrastructure\DepotCredits;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotParametres;
use AvecXY\Infrastructure\DepotPlateforme;
use AvecXY\Infrastructure\DepotPortail;
use AvecXY\Infrastructure\DepotProfils;
use AvecXY\Infrastructure\DepotRapports;
use AvecXY\Infrastructure\DepotRecolteurs;
use AvecXY\Infrastructure\DepotTypesCarnet;
use AvecXY\Infrastructure\DepotUtilisateurs;
use Throwable;

/**
 * Coeur de l'application : assemble les services et traite une requete.
 *
 * Ordre des controles pour chaque page :
 *   1. la route existe            4. le mot de passe initial est change
 *   2. le formulaire est le notre 5. l'utilisateur a le droit demande
 *   3. l'utilisateur est connecte 6. l'abonnement autorise l'ecriture
 */
final class Noyau
{
    private static ?self $instance = null;

    public readonly array $config;
    public readonly Requete $requete;
    public readonly Session $session;
    public readonly Traducteur $traducteur;
    public readonly Vue $vue;
    public ?Contexte $contexte = null;
    /** Administrateur AvecXY connecte a l'espace plateforme (null ailleurs). */
    public ?array $adminPlateforme = null;
    /** Epargnant connecte a l'espace client, avec son organisation et sa devise (null ailleurs). */
    public ?array $comptePortail = null;

    /** @var array<string,object> services deja construits */
    private array $services = [];

    public function __construct(public readonly string $racine, ?Requete $requete = null)
    {
        $this->config = require $racine . '/config/application.php';
        date_default_timezone_set($this->config['fuseau']);
        $this->requete = $requete ?? Requete::depuisServeur();
        $this->session = new Session();
        $this->traducteur = new Traducteur($racine . '/ressources/langues', $this->config['langue']);
        $this->vue = new Vue($racine . '/ressources/vues');
        self::$instance = $this;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \LogicException('Noyau non demarre.');
    }

    // ------------------------------------------------------------------
    // Services (construits a la demande, une seule fois)
    // ------------------------------------------------------------------

    public function bd(): BaseDeDonnees
    {
        // AVECXY_CONFIG_BASE (variable d'environnement du serveur) designe un autre fichier : sert aux essais
        return $this->services['bd'] ??= BaseDeDonnees::depuisConfig(require (getenv('AVECXY_CONFIG_BASE') ?: $this->racine . '/config/base.php'));
    }

    public function notifications(): ServiceNotifications
    {
        return $this->services['notifications'] ??= new ServiceNotifications($this->bd());
    }

    public function audit(): Audit
    {
        return $this->services['audit'] ??= new Audit($this->bd(), $this->requete->ip);
    }

    public function utilisateurs(): DepotUtilisateurs
    {
        return $this->services['utilisateurs'] ??= new DepotUtilisateurs($this->bd());
    }

    public function profils(): DepotProfils
    {
        return $this->services['profils'] ??= new DepotProfils($this->bd());
    }

    public function organisations(): DepotOrganisations
    {
        return $this->services['organisations'] ??= new DepotOrganisations($this->bd());
    }

    public function recolteurs(): DepotRecolteurs
    {
        return $this->services['recolteurs'] ??= new DepotRecolteurs($this->bd());
    }

    public function typesCarnet(): DepotTypesCarnet
    {
        return $this->services['typesCarnet'] ??= new DepotTypesCarnet($this->bd());
    }

    public function serviceRecolteurs(): ServiceRecolteurs
    {
        return $this->services['sRecolteurs'] ??= new ServiceRecolteurs($this->bd(), $this->recolteurs(), $this->utilisateurs(), $this->audit());
    }

    public function serviceTypesCarnet(): ServiceTypesCarnet
    {
        return $this->services['sTypesCarnet'] ??= new ServiceTypesCarnet($this->bd(), $this->typesCarnet(), $this->audit());
    }

    public function parametres(): DepotParametres
    {
        return $this->services['parametres'] ??= new DepotParametres($this->bd());
    }

    public function clients(): DepotClients
    {
        return $this->services['clients'] ??= new DepotClients($this->bd());
    }

    public function carnets(): DepotCarnets
    {
        return $this->services['carnets'] ??= new DepotCarnets($this->bd());
    }

    public function operations(): DepotOperations
    {
        return $this->services['operations'] ??= new DepotOperations($this->bd());
    }

    public function caisse(): DepotCaisse
    {
        return $this->services['caisse'] ??= new DepotCaisse($this->bd());
    }

    public function partage(): ServicePartage
    {
        return $this->services['partage'] ??= new ServicePartage($this->operations());
    }

    public function recalcul(): RecalculCarnet
    {
        return $this->services['recalcul'] ??= new RecalculCarnet($this->bd(), $this->carnets(), $this->operations(), $this->partage());
    }

    public function serviceCotisations(): ServiceCotisations
    {
        return $this->services['sCotisations'] ??= new ServiceCotisations($this->bd(), $this->carnets(), $this->operations(), $this->caisse(), $this->recalcul(), $this->audit(), $this->notifications());
    }

    public function serviceRetraits(): ServiceRetraits
    {
        return $this->services['sRetraits'] ??= new ServiceRetraits($this->bd(), $this->carnets(), $this->operations(), $this->recalcul(), $this->audit(), $this->notifications(), $this->serviceInterets());
    }

    public function comptabilite(): DepotComptabilite
    {
        return $this->services['compta'] ??= new DepotComptabilite($this->bd());
    }

    public function serviceComptabilite(): ServiceComptabilite
    {
        return $this->services['sCompta'] ??= new ServiceComptabilite($this->bd(), $this->comptabilite(), $this->audit());
    }

    public function serviceIndicateurs(): ServiceIndicateurs
    {
        return $this->services['sIndicateurs'] ??= new ServiceIndicateurs($this->bd());
    }

    public function serviceAgences(): ServiceAgences
    {
        return $this->services['sAgences'] ??= new ServiceAgences($this->bd(), $this->caisse(), $this->audit());
    }

    public function serviceInterets(): ServiceInterets
    {
        return $this->services['sInterets'] ??= new ServiceInterets($this->bd(), $this->carnets(), $this->operations(), $this->recalcul());
    }

    public function serviceCarnets(): ServiceCarnets
    {
        return $this->services['sCarnets'] ??= new ServiceCarnets($this->bd(), $this->carnets(), $this->clients(), $this->typesCarnet(), $this->recalcul(), $this->serviceCotisations(), $this->serviceRetraits(), $this->audit(), $this->notifications());
    }

    public function serviceClients(): ServiceClients
    {
        return $this->services['sClients'] ??= new ServiceClients($this->bd(), $this->clients(), $this->carnets(), $this->recolteurs(), $this->parametres(), $this->serviceCarnets(), $this->audit());
    }

    public function serviceCaisse(): ServiceCaisse
    {
        return $this->services['sCaisse'] ??= new ServiceCaisse($this->bd(), $this->caisse(), $this->recolteurs(), $this->recalcul(), $this->partage(), $this->audit(), $this->parametres());
    }

    public function credits(): DepotCredits
    {
        return $this->services['credits'] ??= new DepotCredits($this->bd());
    }

    public function serviceCredits(): ServiceCredits
    {
        return $this->services['sCredits'] ??= new ServiceCredits($this->bd(), $this->credits(), $this->caisse(), $this->operations(), $this->partage(), $this->audit(), $this->serviceRetraits(), $this->carnets());
    }

    public function rapports(): DepotRapports
    {
        return $this->services['rapports'] ??= new DepotRapports($this->bd(), $this->caisse());
    }

    public function serviceTaches(): ServiceTaches
    {
        return $this->services['sTaches'] ??= new ServiceTaches($this->bd(), $this->parametres(), $this->operations(), $this->recalcul(), $this->serviceCredits(), $this->serviceInterets());
    }

    public function plateforme(): DepotPlateforme
    {
        return $this->services['plateforme'] ??= new DepotPlateforme($this->bd());
    }

    public function servicePlateforme(): ServicePlateforme
    {
        return $this->services['sPlateforme'] ??= new ServicePlateforme($this->bd(), $this->plateforme(), $this->organisations(), $this->utilisateurs(), $this->notifications());
    }

    public function serviceSupport(): ServiceSupport
    {
        return $this->services['sSupport'] ??= new ServiceSupport($this->bd(), $this->plateforme(), $this->organisations(), $this->audit(), $this->notifications());
    }

    public function portail(): DepotPortail
    {
        return $this->services['portail'] ??= new DepotPortail($this->bd());
    }

    public function servicePortail(): ServicePortail
    {
        return $this->services['sPortail'] ??= new ServicePortail($this->bd(), $this->portail(), $this->clients(), $this->organisations(), $this->utilisateurs(), $this->audit());
    }

    public function serviceInstallation(): ServiceInstallation
    {
        return $this->services['sInstallation'] ??= new ServiceInstallation($this->racine);
    }

    public function controles(): DepotControle
    {
        return $this->services['controles'] ??= new DepotControle($this->bd());
    }

    public function serviceControle(): ServiceControle
    {
        return $this->services['sControle'] ??= new ServiceControle($this->bd(), $this->controles(), $this->caisse(), $this->serviceCarnets(), $this->serviceCotisations(), $this->serviceCredits(), $this->audit(), $this->notifications());
    }

    public function serviceSecurite(): ServiceSecurite
    {
        return $this->services['sSecurite'] ??= new ServiceSecurite($this->bd(), $this->utilisateurs(), $this->audit());
    }

    public function serviceReprise(): ServiceReprise
    {
        return $this->services['sReprise'] ??= new ServiceReprise(
            $this->bd(), $this->recolteurs(), $this->typesCarnet(), $this->credits(), $this->clients(), $this->carnets(), $this->caisse(),
            $this->organisations(), $this->serviceRecolteurs(), $this->recalcul(), $this->serviceCredits(), $this->audit()
        );
    }

    public function serviceMobile(): ServiceMobile
    {
        return $this->services['sMobile'] ??= new ServiceMobile($this->bd(), $this);
    }

    public function authentification(): Authentification
    {
        return $this->services['auth'] ??= new Authentification($this->utilisateurs(), $this->profils(), $this->organisations(), $this->audit());
    }

    public function serviceProfils(): ServiceProfils
    {
        return $this->services['sProfils'] ??= new ServiceProfils($this->bd(), $this->profils(), $this->audit());
    }

    public function serviceUtilisateurs(): ServiceUtilisateurs
    {
        return $this->services['sUtilisateurs'] ??= new ServiceUtilisateurs($this->bd(), $this->utilisateurs(), $this->profils(), $this->audit());
    }

    public function serviceOrganisation(): ServiceOrganisation
    {
        return $this->services['sOrganisation'] ??= new ServiceOrganisation($this->bd(), $this->organisations(), $this->profils(), $this->utilisateurs(), $this->audit(), $this->parametres());
    }

    // ------------------------------------------------------------------
    // Traitement d'une requete
    // ------------------------------------------------------------------

    public function traiter(): Reponse
    {
        try {
            return $this->acheminer();
        } catch (Throwable $e) {
            $this->journaliser($e);
            $detail = $this->config['debogage'] ? $e->getMessage() . "\n" . $e->getTraceAsString() : '';
            return $this->pageErreur(500, 'erreur.500', $detail);
        }
    }

    private function acheminer(): Reponse
    {
        // application mobile : JSON et jeton, sans session ni cookie
        if (str_starts_with($this->requete->chemin . '/', '/api/')) {
            try {
                return (new \AvecXY\Http\Controleurs\ApiControleur($this))->traiter($this->requete->methode, $this->requete->chemin);
            } catch (Throwable $e) {
                $this->journaliser($e);
                return Reponse::json(['erreur' => 'Erreur du serveur.', 'code' => 'SERVEUR'] + ($this->config['debogage'] ? ['detail' => $e->getMessage()] : []), 500);
            }
        }
        $this->session->demarrer($this->requete->https);
        $langue = $this->session->lire('langue');
        if (is_string($langue)) {
            $this->traducteur->charger($langue);
        }

        $routeur = new Routeur();
        (require $this->racine . '/config/routes.php')($routeur);
        $chemin = $this->requete->chemin;
        if ($chemin === '/' && $this->requete->methode === 'GET' && (int) $this->session->lire('utilisateur_id', 0) === 0) {
            $chemin = '/bienvenue';   // visiteur : la page qui presente l'application
        }
        $route = $routeur->trouver($this->requete->methode, $chemin);
        if ($route === null) {
            $this->chargerContexte();
            return $this->pageErreur(404, 'erreur.404');
        }
        $options = $route['options'];

        // Application pas encore installee : les pages de connexion menent a l'installation.
        if ($this->requete->methode === 'GET' && in_array($this->requete->chemin, ['/', '/connexion', '/plateforme', '/plateforme/connexion', '/espace-client/connexion'], true)
            && (int) $this->session->lire('utilisateur_id', 0) === 0 && (int) $this->session->lire('plateforme_admin_id', 0) === 0
            && $this->serviceInstallation()->etat() !== ServiceInstallation::FAIT) {
            return Reponse::redirection(url('/installation'));
        }

        if ($this->requete->methode === 'POST' && !$this->session->jetonValide((string) ($this->requete->post['_jeton'] ?? ''))) {
            return $this->pageErreur(419, 'erreur.419');
        }

        if (!empty($options['plateforme'])) {
            // espace reserve a AvecXY : session distincte de celle des organisations
            $adminId = (int) $this->session->lire('plateforme_admin_id', 0);
            $this->adminPlateforme = $adminId > 0 ? $this->plateforme()->admin($adminId) : null;
            if ($this->adminPlateforme === null && empty($options['public'])) {
                return Reponse::redirection(url('/plateforme/connexion'));
            }
        } elseif (!empty($options['portail'])) {
            // espace client : l'epargnant, en consultation seule
            $clientId = (int) $this->session->lire('portail_client_id', 0);
            $compte = $clientId > 0 ? $this->portail()->compte($clientId) : null;
            $this->comptePortail = $this->servicePortail()->compteUtilisable($compte) ? $compte : null;
            if ($this->comptePortail === null && empty($options['public'])) {
                $this->session->oublier('portail_client_id');
                return Reponse::redirection(url('/espace-client/connexion'));
            }
            if ($this->comptePortail !== null) {
                if (in_array($this->comptePortail['langue'], Traducteur::LANGUES, true) && !is_string($langue)) {
                    $this->traducteur->charger($this->comptePortail['langue']);
                }
                if ((int) $this->comptePortail['doit_changer_code'] === 1 && empty($options['toujours']) && empty($options['public'])) {
                    return Reponse::redirection(url('/espace-client/code'));
                }
            }
        } elseif (empty($options['public'])) {
            if (!$this->chargerContexte()) {
                // on efface le compte d'organisation perime, sans toucher a une session plateforme ouverte
                $this->session->oublier('utilisateur_id');
                $this->session->oublier('organisation_id');
                return Reponse::redirection(url('/connexion'));
            }
            $c = $this->contexte;

            if ($c->abonnement->etat === EtatAbonnement::SUSPENDU && !$c->estAdmin()) {
                $this->session->detruire();
                $this->session->demarrer($this->requete->https);
                $this->session->message('erreur', t('abonnement.suspendu_personnel'));
                return Reponse::redirection(url('/connexion'));
            }
            if ((int) $c->utilisateur['doit_changer_mot_de_passe'] === 1 && empty($options['toujours'])) {
                $this->session->message('info', t('compte.changer_mot_de_passe'));
                return Reponse::redirection(url('/mon-compte'));
            }
            // chaque utilisateur cree son PIN (il servira aussi dans l'application mobile)
            if (empty($c->utilisateur['a_un_pin']) && empty($options['toujours'])) {
                $this->session->message('info', t('pin.a_creer'));
                return Reponse::redirection(url('/mon-compte'));
            }
            // 'droit' peut etre une liste : il suffit alors d'en avoir un
            if (isset($options['droit']) && !array_filter((array) $options['droit'], [$c, 'peut'])) {
                return $this->pageErreur(403, 'erreur.403');
            }
            if (isset($options['module']) && !$c->module($options['module'])) {
                return $this->pageErreur(403, 'erreur.module');
            }
            if ($this->requete->methode === 'POST' && empty($options['toujours']) && $c->abonnement->ecritureBloquee()) {
                $this->session->message('erreur', t('abonnement.ecriture_bloquee'));
                return Reponse::redirection(url('/'));
            }
            if (!$c->abonnement->ecritureBloquee()) {
                try {
                    $this->serviceTaches()->duJour($c->organisationId(), $c->decimales());
                } catch (Throwable $e) {
                    $this->journaliser($e);   // un travail automatique en echec ne bloque jamais la page
                }
            }
        }

        [$classe, $methode] = $route['action'];
        return (new $classe($this))->$methode(...array_values($route['params']));
    }

    /** Recharge l'utilisateur connecte depuis la base ; false s'il n'y en a pas. */
    private function chargerContexte(): bool
    {
        $uid = (int) $this->session->lire('utilisateur_id', 0);
        $oid = (int) $this->session->lire('organisation_id', 0);
        if ($uid === 0 || $oid === 0) {
            return false;
        }
        $this->contexte = $this->authentification()->contexte($oid, $uid);
        if ($this->contexte === null) {
            return false;
        }
        $this->traducteur->charger($this->contexte->utilisateur['langue'] ?? $this->contexte->organisation['langue']);
        if (in_array($this->contexte->organisation['fuseau'], timezone_identifiers_list(), true)) {
            date_default_timezone_set($this->contexte->organisation['fuseau']);
        }
        return true;
    }

    public function pageErreur(int $statut, string $cle, string $detail = ''): Reponse
    {
        $gabarit = $this->contexte !== null ? 'gabarit/application' : 'gabarit/public';
        $html = $this->vue->rendre('erreurs/erreur', [
            'titre' => t($cle . '.titre'), 'texte' => t($cle . '.texte'), 'statut' => $statut, 'detail' => $detail,
        ], $gabarit);
        return Reponse::html($html, $statut);
    }

    private function journaliser(Throwable $e): void
    {
        $ligne = sprintf("[%s] %s %s : %s dans %s:%d\n%s\n\n", date('Y-m-d H:i:s'), $this->requete->methode, $this->requete->chemin, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        @file_put_contents($this->racine . '/stockage/erreurs.log', $ligne, FILE_APPEND | LOCK_EX);
    }
}

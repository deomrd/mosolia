<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\EtatAbonnement;
use AvecXY\Noyau\Noyau;
use AvecXY\Noyau\Reponse;

/**
 * API de l'application mobile des recolteurs. Tout est en JSON ; le jeton voyage dans l'en-tete
 * "Authorization: Bearer ...". Ni session ni cookie : ces adresses ne passent pas par le controle des formulaires.
 */
final class ApiControleur
{
    public const VERSION = 1;

    public function __construct(private readonly Noyau $noyau)
    {
    }

    public function traiter(string $methode, string $chemin): Reponse
    {
        $corps = json_decode((string) file_get_contents('php://input'), true);
        $corps = is_array($corps) ? $corps : [];
        $langue = (string) ($corps['langue'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'fr');
        $this->noyau->traducteur->charger(str_starts_with(strtolower($langue), 'en') ? 'en' : 'fr');
        try {
            if ($methode === 'GET' && $chemin === '/api/v1') {
                return Reponse::json(['application' => $this->noyau->config['nom'], 'version' => self::VERSION, 'heure' => date('Y-m-d H:i:s'), 'adresse' => $this->noyau->serviceMobile()->adresse()]);
            }
            if ($methode === 'POST' && $chemin === '/api/v1/connexion') {
                $mobile = $this->noyau->serviceMobile();
                $identifiant = (string) ($corps['identifiant'] ?? '');
                $secret = (string) ($corps['mot_de_passe'] ?? '');
                // un seul ecran de connexion : un code client ouvre l'espace client, le reste est le personnel
                if (\AvecXY\Application\ServiceMobile::estCodeClient($identifiant)) {
                    $r = $mobile->connecterClient($identifiant, $secret, $this->noyau->requete->ip);
                    return Reponse::json(['profil' => 'CLIENT', 'passage' => $r['passage'], 'adresse' => $mobile->adresse()]);
                }
                $r = $mobile->connecter($identifiant, $secret, (string) ($corps['appareil_id'] ?? ''), (string) ($corps['appareil_nom'] ?? ''), $this->noyau->requete->ip);
                $c = $mobile->contexteDuJeton($r['jeton']) ?? throw new ErreurMetier('connexion.incorrecte');
                $this->noyau->contexte = $c;
                $profil = \AvecXY\Application\ServiceMobile::profil($c);
                return Reponse::json([
                    'jeton' => $r['jeton'], 'profil' => $profil, 'adresse' => $mobile->adresse(),
                    'utilisateur' => ['nom' => $c->utilisateur['nom'], 'identifiant' => $c->utilisateur['identifiant']],
                    'organisation' => ['nom' => $c->organisation['nom']],
                    'portefeuille' => $profil === 'GESTION' ? null : $mobile->portefeuille($c),
                ]);
            }
            $jeton = preg_match('/^Bearer\s+([a-f0-9]{64})$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m) ? strtolower($m[1]) : '';
            $c = $jeton === '' ? null : $this->noyau->serviceMobile()->contexteDuJeton($jeton);
            if ($c === null) {
                return Reponse::json(['erreur' => t('mobile.reconnexion'), 'code' => 'JETON'], 401);
            }
            $this->noyau->contexte = $c;   // les services et les montants affiches s'appuient dessus
            $this->noyau->traducteur->charger($c->utilisateur['langue'] ?? $c->organisation['langue']);
            if ($c->abonnement->etat === EtatAbonnement::SUSPENDU) {
                return Reponse::json(['erreur' => t('abonnement.suspendu_personnel'), 'code' => 'SUSPENDU'], 403);
            }
            // passage vers le site dans l'application (gestion), sans redemander le mot de passe
            if ($methode === 'POST' && $chemin === '/api/v1/passage') {
                $code = $this->noyau->serviceMobile()->creerPassage('PERSONNEL', $c->organisationId(), $c->utilisateurId(), null, (string) ($corps['cible'] ?? '/'));
                return Reponse::json(['passage' => $code, 'chemin' => '/passage/' . $code, 'profil' => \AvecXY\Application\ServiceMobile::profil($c)]);
            }
            if (empty($c->utilisateur['agent_id']) && in_array($chemin, ['/api/v1/portefeuille', '/api/v1/synchroniser'], true)) {
                return Reponse::json(['erreur' => t('mobile.reserve_recolteurs'), 'code' => 'GESTION', 'profil' => 'GESTION'], 403);
            }
            if ($methode === 'GET' && $chemin === '/api/v1/portefeuille') {
                return Reponse::json($this->noyau->serviceMobile()->portefeuille($c));
            }
            if ($methode === 'POST' && $chemin === '/api/v1/synchroniser') {
                if ($c->abonnement->ecritureBloquee()) {
                    return Reponse::json(['erreur' => t('abonnement.ecriture_bloquee'), 'code' => 'LECTURE_SEULE'], 403);
                }
                $operations = is_array($corps['operations'] ?? null) ? $corps['operations'] : [];
                $reponses = $this->noyau->serviceMobile()->synchroniser($c, $operations);
                return Reponse::json(['operations' => $reponses, 'portefeuille' => $this->noyau->serviceMobile()->portefeuille($c)]);
            }
            if ($methode === 'POST' && $chemin === '/api/v1/notifications/lues') {
                $this->noyau->notifications()->toutLire('UTILISATEUR', $c->utilisateurId());
                return Reponse::json(['ok' => true]);
            }
            if ($methode === 'POST' && $chemin === '/api/v1/deconnexion') {
                $this->noyau->serviceMobile()->deconnecter($jeton);
                return Reponse::json(['ok' => true]);
            }
            return Reponse::json(['erreur' => t('erreur.404.titre'), 'code' => 'INCONNU'], 404);
        } catch (ErreurMetier $e) {
            return Reponse::json(['erreur' => t($e->cle, $e->valeurs), 'code' => 'REFUS'], 422);
        }
    }
}

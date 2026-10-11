<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceInstallation;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/**
 * Installation dans le navigateur. Chaque action verifie l'etape reelle : une fois
 * l'administrateur de la plateforme cree, ces pages renvoient vers la connexion et ne font plus rien.
 */
final class InstallationControleur extends Controleur
{
    private function service(): ServiceInstallation
    {
        return $this->noyau->serviceInstallation();
    }

    public function index(): Reponse
    {
        $s = $this->service();
        $etat = $s->etat();
        if ($etat === ServiceInstallation::FAIT) {
            return $this->rediriger('/plateforme/connexion');
        }
        return $this->page('installation/index', [
            'etat' => $etat, 'erreurConnexion' => $s->erreur, 'configExiste' => is_file($s->fichierConfig()),
            'ecritureConfig' => is_writable(dirname($s->fichierConfig())), 'ecritureStockage' => is_writable($this->noyau->racine . '/stockage'),
            'php' => PHP_VERSION, 'phpOk' => PHP_VERSION_ID >= 80200, 'pdoOk' => extension_loaded('pdo_mysql'),
        ], 'gabarit/plateforme');
    }

    /** Execute l'action seulement si l'installation en est a cette etape. */
    private function etape(string $attendue, callable $action, string $succes): Reponse
    {
        if ($this->service()->etat() !== $attendue) {
            return $this->rediriger('/installation');
        }
        try {
            $action();
            $this->succes($succes);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/installation');
    }

    public function base(): Reponse
    {
        $r = $this->requete;
        return $this->etape(ServiceInstallation::CONFIG, fn () => $this->service()->configurer(
            $r->texte('hote'), $r->texte('port'), $r->texte('base'), $r->texte('utilisateur'), (string) ($r->post['mot_de_passe'] ?? '')
        ), 'installation.base_ok');
    }

    public function tables(): Reponse
    {
        return $this->etape(ServiceInstallation::TABLES, fn () => $this->service()->migrer(), 'installation.tables_ok');
    }

    public function admin(): Reponse
    {
        if ($this->service()->etat() !== ServiceInstallation::ADMIN) {
            return $this->rediriger('/installation');
        }
        $r = $this->requete;
        try {
            $this->service()->migrer();   // au cas ou des migrations restent a appliquer
            $id = $this->noyau->servicePlateforme()->creerAdmin($r->texte('nom'), $r->texte('email'), (string) ($r->post['mot_de_passe'] ?? ''));
        } catch (ErreurMetier $e) {
            $this->erreur($e);
            return $this->rediriger('/installation');
        }
        $this->noyau->session->regenerer();
        $this->noyau->session->ecrire('plateforme_admin_id', $id);
        $this->succes('installation.terminee');
        return $this->rediriger('/plateforme');
    }

    /** Mise a jour de la base depuis l'espace plateforme (apres avoir remplace le code). */
    public function mettreAJour(): Reponse
    {
        try {
            $n = count($this->service()->migrer());
            $this->succes($n > 0 ? 'installation.mise_a_jour_faite' : 'installation.deja_a_jour', ['n' => $n]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/plateforme/reglages');
    }
}

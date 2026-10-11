<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Infrastructure\Migrateur;
use AvecXY\Noyau\BaseDeDonnees;
use Throwable;

/**
 * Installation et mises a jour depuis le navigateur, sans terminal :
 * connexion a la base, creation des tables, premier administrateur de la plateforme.
 * La page d'installation se ferme d'elle-meme des qu'un administrateur existe.
 */
final class ServiceInstallation
{
    public const CONFIG = 'CONFIG';   // config/base.php absent ou connexion impossible
    public const TABLES = 'TABLES';   // tables a creer
    public const ADMIN = 'ADMIN';     // aucun administrateur de la plateforme
    public const FAIT = 'FAIT';

    public ?string $erreur = null;

    public function __construct(private readonly string $racine)
    {
    }

    public function fichierConfig(): string
    {
        return getenv('AVECXY_CONFIG_BASE') ?: $this->racine . '/config/base.php';
    }

    private function base(): ?BaseDeDonnees
    {
        if (!is_file($this->fichierConfig())) {
            return null;
        }
        try {
            return BaseDeDonnees::depuisConfig(require $this->fichierConfig());
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();
            return null;
        }
    }

    public function etat(): string
    {
        $bd = $this->base();
        if ($bd === null) {
            return self::CONFIG;
        }
        try {
            return (int) $bd->valeur('SELECT COUNT(*) FROM avecxy_plateforme_admins') > 0 ? self::FAIT : self::ADMIN;
        } catch (Throwable) {
            return self::TABLES;   // la table n'existe pas encore
        }
    }

    /** Verifie les identifiants en se connectant, puis ecrit config/base.php. */
    public function configurer(string $hote, string $port, string $base, string $utilisateur, string $motDePasse): void
    {
        $hote = trim($hote);
        $base = trim($base);
        $utilisateur = trim($utilisateur);
        if ($hote === '' || $utilisateur === '' || !preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $base) || !ctype_digit($port === '' ? '3306' : $port)) {
            throw new ErreurMetier('installation.champs_invalides');
        }
        $config = ['pilote' => 'mysql', 'hote' => $hote, 'port' => (int) ($port === '' ? 3306 : $port), 'base' => $base, 'utilisateur' => $utilisateur, 'mot_de_passe' => $motDePasse];
        try {
            BaseDeDonnees::depuisConfig($config);
        } catch (Throwable $e) {
            throw new ErreurMetier('installation.connexion_impossible', ['detail' => $e->getMessage()]);
        }
        $contenu = "<?php\n// Ecrit par la page d'installation. Ne partagez jamais ce fichier.\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($this->fichierConfig(), $contenu, LOCK_EX) === false) {
            throw new ErreurMetier('installation.ecriture_impossible');
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->fichierConfig(), true);
        }
    }

    /** @return string[] fichiers de migration pas encore appliques */
    public function enAttente(): array
    {
        $bd = $this->base();
        $fichiers = array_map('basename', array_merge(glob($this->racine . '/database/migrations/*.sql') ?: [], glob($this->racine . '/database/migrations/*.php') ?: []));
        sort($fichiers);
        if ($bd === null) {
            return $fichiers;
        }
        try {
            $faits = array_column($bd->tous('SELECT fichier FROM avecxy_migrations'), 'fichier');
        } catch (Throwable) {
            $faits = [];
        }
        return array_values(array_diff($fichiers, $faits));
    }

    /**
     * Cree ou met a jour les tables, puis ajoute au catalogue les droits apparus dans le code.
     * @return string[] migrations appliquees
     */
    public function migrer(): array
    {
        $bd = $this->base() ?? throw new ErreurMetier('installation.connexion_impossible', ['detail' => (string) $this->erreur]);
        @set_time_limit(300);
        try {
            $appliques = (new Migrateur($bd, $this->racine . '/database/migrations'))->migrer();
        } catch (Throwable $e) {
            throw new ErreurMetier('installation.migration_echouee', ['detail' => $e->getMessage()]);
        }
        $connus = array_column($bd->tous('SELECT code FROM avecxy_permissions'), 'code');
        foreach (CatalogueDroits::DROITS as $module => $codes) {
            foreach ($codes as $code) {
                if (!in_array($code, $connus, true)) {
                    $bd->inserer('avecxy_permissions', ['code' => $code, 'module' => $module, 'libelle' => $code]);
                }
            }
        }
        return $appliques;
    }
}

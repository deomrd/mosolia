<?php
namespace AvecXY\Noyau;

/** La requete HTTP en cours. */
final class Requete
{
    public function __construct(
        public readonly string $methode,
        public readonly string $chemin,   // chemin dans l'application, ex. /parametres/profils
        public readonly string $base,     // dossier d'installation, ex. "" ou /avecxy/public
        public readonly array $get,
        public readonly array $post,
        public readonly string $ip,
        public readonly bool $https,
    ) {
    }

    public static function depuisServeur(): self
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(dirname($script), '/');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Site pointe sur le dossier parent (renvoi vers public/ par .htaccess) :
        // l'adresse visible ne contient pas "/public".
        if (str_ends_with($base, '/public') && !str_starts_with($uri . '/', $base . '/')) {
            $base = substr($base, 0, -strlen('/public'));
        }
        $chemin = $uri;
        if ($base !== '' && str_starts_with($uri, $base)) {
            $chemin = substr($uri, strlen($base));
        }
        if (str_starts_with($chemin, '/index.php')) {
            $chemin = substr($chemin, strlen('/index.php'));
        }
        $chemin = '/' . trim($chemin, '/');
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            // hebergement derriere un proxy (le certificat est pose avant le serveur PHP)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $chemin,
            $base,
            $_GET,
            $_POST,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $https,
        );
    }

    /** Adresse du site vue par le navigateur (ex. https://exemple.com) et son domaine : servent a la connexion par empreinte. */
    public function origine(): string
    {
        return ($this->https ? 'https' : 'http') . '://' . $this->hote();
    }

    public function hote(): string
    {
        $hote = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $hote) ? $hote : 'localhost';
    }

    public function domaine(): string
    {
        return preg_replace('/:\d+$/', '', $this->hote());
    }

    public function texte(string $champ, string $defaut = ''): string
    {
        $v = $this->post[$champ] ?? $defaut;
        return is_string($v) ? trim($v) : $defaut;
    }

    public function entier(string $champ, int $defaut = 0): int
    {
        $v = $this->post[$champ] ?? $this->get[$champ] ?? null;
        return is_numeric($v) ? (int) $v : $defaut;
    }

    public function tableau(string $champ): array
    {
        $v = $this->post[$champ] ?? [];
        return is_array($v) ? $v : [];
    }
}

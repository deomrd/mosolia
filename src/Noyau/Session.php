<?php
namespace AvecXY\Noyau;

/** Session du navigateur : utilisateur connecte, messages, jeton anti-falsification. */
final class Session
{
    public function demarrer(bool $https): void
    {
        if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
            return;
        }
        session_name('avecxy');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function lire(string $cle, mixed $defaut = null): mixed
    {
        return $_SESSION[$cle] ?? $defaut;
    }

    public function ecrire(string $cle, mixed $valeur): void
    {
        $_SESSION[$cle] = $valeur;
    }

    public function oublier(string $cle): void
    {
        unset($_SESSION[$cle]);
    }

    /** Change l'identifiant de session a la connexion (protection contre le vol de session). */
    public function regenerer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function detruire(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function message(string $type, string $texte): void
    {
        $_SESSION['_messages'][] = ['type' => $type, 'texte' => $texte];
    }

    /** Renvoie les messages en attente et les efface. */
    public function messages(): array
    {
        $m = $_SESSION['_messages'] ?? [];
        unset($_SESSION['_messages']);
        return $m;
    }

    public function jetonCsrf(): string
    {
        if (empty($_SESSION['_jeton'])) {
            $_SESSION['_jeton'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_jeton'];
    }

    public function jetonValide(string $jeton): bool
    {
        return $jeton !== '' && hash_equals((string) ($_SESSION['_jeton'] ?? ''), $jeton);
    }
}

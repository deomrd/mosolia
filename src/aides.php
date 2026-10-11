<?php
// Fonctions courtes utilisees dans les vues.

use AvecXY\Noyau\Noyau;

/** Echappe une valeur pour l'afficher dans du HTML. */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Traduit une cle dans la langue de l'utilisateur. */
function t(string $cle, array $valeurs = []): string
{
    return Noyau::instance()->traducteur->traduire($cle, $valeurs);
}

/** Adresse interne, quel que soit le dossier ou l'application est installee. */
function url(string $chemin = '/'): string
{
    return Noyau::instance()->requete->base . '/' . ltrim($chemin, '/');
}

/** Adresse complete (avec le nom de domaine), a donner a quelqu'un : "https://exemple.com/espace-client". */
function url_absolue(string $chemin = '/'): string
{
    $requete = Noyau::instance()->requete;
    $hote = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($requete->https ? 'https://' : 'http://') . $hote . url($chemin);
}

/** Adresse d'un fichier public avec numero de version (evite le cache apres mise a jour). */
function ressource(string $chemin): string
{
    $fichier = dirname(__DIR__) . '/public/' . ltrim($chemin, '/');
    $version = is_file($fichier) ? filemtime($fichier) : 0;
    return url($chemin) . '?v=' . $version;
}

/** Champ cache anti-falsification a placer dans chaque formulaire POST. */
function champ_csrf(): string
{
    return '<input type="hidden" name="_jeton" value="' . e(Noyau::instance()->session->jetonCsrf()) . '">';
}

/** L'utilisateur connecte a-t-il ce droit ? */
function peut(string $droit): bool
{
    $contexte = Noyau::instance()->contexte;
    return $contexte !== null && $contexte->peut($droit);
}

/** Montant avec le symbole de la devise de l'organisation : "1 500 FC". */
function argent(mixed $montant): string
{
    $noyau = Noyau::instance();
    $o = $noyau->contexte?->organisation ?? $noyau->comptePortail;
    return \AvecXY\Domaine\Montant::afficher($montant, (int) ($o['devise_decimales'] ?? 0)) . ' ' . ($o['devise_symbole'] ?? '');
}

/** Date lisible : "07/10/2026". */
function jour(?string $date): string
{
    return $date === null || $date === '' ? '—' : date('d/m/Y', strtotime($date));
}

/** Adresse de la page courante avec ses filtres, en changeant certains parametres (pagination). */
function url_avec(array $changements): string
{
    $requete = Noyau::instance()->requete;
    $q = array_filter(array_merge($requete->get, $changements), static fn ($v) => $v !== '' && $v !== null);
    return url($requete->chemin) . ($q ? '?' . http_build_query($q) : '');
}

/**
 * La page est-elle affichee dans l'application Mosolia (Android) ? On y masque ce qui telecharge ou envoie
 * des fichiers (exports Excel, modeles, imports, photos) : ces operations se font sur ordinateur.
 */
function dans_application(): bool
{
    static $reponse = null;
    if ($reponse === null) {
        $session = \AvecXY\Noyau\Noyau::instance()->session;
        $reponse = (int) $session->lire('dans_application', 0) === 1;
        // reconnue une fois, l'application le reste pour toute la session (le service worker
        // de la page peut faire ses requetes sans l'agent de l'application)
        if (!$reponse && str_contains((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'MosoliaApp')) {
            $session->ecrire('dans_application', 1);
            $reponse = true;
        }
    }
    return $reponse;
}

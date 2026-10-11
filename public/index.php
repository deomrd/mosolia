<?php
// Point d'entree unique : toutes les pages passent par ici.
// Ce fichier reste lisible par un vieux PHP, pour afficher un message clair au lieu d'une erreur 500.
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>PHP trop ancien</h1><p>Mosolia demande PHP 8.2 ou plus récent. Ce serveur utilise PHP ' . htmlspecialchars(PHP_VERSION)
        . '.</p><p>Chez LWS : panneau du site, « Configuration PHP », choisir 8.2 ou plus, puis recharger cette page.</p>';
    exit;
}
// Erreur fatale avant que l'application ne prenne la main : on la note et on l'affiche simplement.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $ligne = date('Y-m-d H:i:s') . ' FATALE ' . $e['message'] . ' (' . $e['file'] . ':' . $e['line'] . ")\n";
    @file_put_contents(dirname(__DIR__) . '/stockage/erreurs.log', $ligne, FILE_APPEND);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8', true, 500);
    }
    echo '<h1>Erreur du serveur</h1><p>Le détail est noté dans <code>stockage/erreurs.log</code>.</p>';
});
require dirname(__DIR__) . '/src/autoload.php';

(new AvecXY\Noyau\Noyau(dirname(__DIR__)))->traiter()->envoyer();

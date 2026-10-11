<?php
// Serveur de developpement :  php -S 127.0.0.1:8080 -t public tests/serveur.php
$fichier = dirname(__DIR__) . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($fichier)) {
    return false; // css, js, images : servis tels quels
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/public/index.php';

<?php
// Chargement automatique des classes AvecXY\... depuis src/ (PSR-4, sans Composer).
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'AvecXY\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $fichier = __DIR__ . '/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});

require __DIR__ . '/aides.php';

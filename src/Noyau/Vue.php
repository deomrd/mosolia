<?php
namespace AvecXY\Noyau;

/** Rend un fichier de ressources/vues/ a l'interieur d'un gabarit. */
final class Vue
{
    public function __construct(private readonly string $dossier)
    {
    }

    public function rendre(string $vue, array $donnees = [], string $gabarit = 'gabarit/application'): string
    {
        $contenu = $this->partiel($vue, $donnees);
        return $this->partiel($gabarit, $donnees + ['contenu' => $contenu]);
    }

    public function partiel(string $vue, array $donnees = []): string
    {
        extract($donnees, EXTR_SKIP);
        ob_start();
        require $this->dossier . '/' . $vue . '.php';
        return (string) ob_get_clean();
    }
}

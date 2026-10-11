<?php
namespace AvecXY\Noyau;

/** Ce que l'application renvoie au navigateur. */
final class Reponse
{
    private function __construct(
        public readonly int $statut,
        public readonly string $corps,
        public readonly array $entetes,
    ) {
    }

    public static function html(string $corps, int $statut = 200): self
    {
        return new self($statut, $corps, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirection(string $adresse): self
    {
        return new self(302, '', ['Location' => $adresse]);
    }

    public static function json(array $donnees, int $statut = 200): self
    {
        return new self($statut, (string) json_encode($donnees, JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /** Contenu affiche tel quel (image privee). */
    public static function binaire(string $contenu, string $type): self
    {
        return new self(200, $contenu, ['Content-Type' => $type, 'Cache-Control' => 'private, no-store']);
    }

    /** Fichier a telecharger (export CSV). */
    public static function fichier(string $nom, string $contenu, string $type = 'text/csv; charset=utf-8'): self
    {
        return new self(200, $contenu, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nom) . '"']);
    }

    public function envoyer(): void
    {
        http_response_code($this->statut);
        foreach ($this->entetes as $nom => $valeur) {
            header($nom . ': ' . $valeur);
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        echo $this->corps;
    }
}

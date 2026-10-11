<?php
namespace AvecXY\Noyau;

/** Textes de l'interface en francais et en anglais (ressources/langues/). */
final class Traducteur
{
    public const LANGUES = ['fr', 'en'];

    private array $textes = [];
    private array $secours = [];

    public function __construct(private readonly string $dossier, public string $langue = 'fr')
    {
        $this->charger($langue);
    }

    public function charger(string $langue): void
    {
        $this->langue = in_array($langue, self::LANGUES, true) ? $langue : 'fr';
        $this->textes = require $this->dossier . '/' . $this->langue . '.php';
        $this->secours = $this->langue === 'fr' ? $this->textes : require $this->dossier . '/fr.php';
    }

    /** t('profils.supprime', ['nom' => 'CAISSIER']) remplace :nom dans le texte. */
    public function traduire(string $cle, array $valeurs = []): string
    {
        $texte = $this->textes[$cle] ?? $this->secours[$cle] ?? $cle;
        foreach ($valeurs as $nom => $valeur) {
            $texte = str_replace(':' . $nom, (string) $valeur, $texte);
        }
        return $texte;
    }

    public function existe(string $cle): bool
    {
        return isset($this->textes[$cle]) || isset($this->secours[$cle]);
    }
}

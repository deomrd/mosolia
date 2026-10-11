<?php
namespace AvecXY\Domaine;

/**
 * Calcule les droits reels d'un utilisateur :
 *   droits du profil + droits accordes a la personne - droits refuses a la personne.
 * Un refus personnel l'emporte toujours, sauf pour un profil administrateur
 * (qui a tout, pour qu'une organisation ne puisse pas se bloquer).
 */
final class ResolveurDroits
{
    /**
     * @param string[] $droitsDuProfil
     * @param array<string,string> $reglesPersonnelles code => ACCORDE | REFUSE
     * @return string[]
     */
    public static function resoudre(bool $profilAdmin, array $droitsDuProfil, array $reglesPersonnelles): array
    {
        if ($profilAdmin) {
            return CatalogueDroits::codes();
        }
        $droits = array_fill_keys($droitsDuProfil, true);
        foreach ($reglesPersonnelles as $code => $effet) {
            if ($effet === 'ACCORDE') {
                $droits[$code] = true;
            } elseif ($effet === 'REFUSE') {
                unset($droits[$code]);
            }
        }
        return array_values(array_intersect(CatalogueDroits::codes(), array_keys($droits)));
    }
}

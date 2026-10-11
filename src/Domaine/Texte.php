<?php
namespace AvecXY\Domaine;

/** Regles d'ecriture communes a toute l'application. */
final class Texte
{
    private const SANS_ACCENT = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y', 'ẞ' => 'SS',
    ];

    /**
     * Forme unique d'un nom de personne ou d'organisation :
     * MAJUSCULES, sans accents, espaces simples. "  élodie  kasongo " => "ELODIE KASONGO".
     */
    public static function nom(string $valeur): string
    {
        $valeur = mb_strtoupper(trim($valeur), 'UTF-8');
        $valeur = str_replace('ß', 'SS', $valeur);
        $valeur = strtr($valeur, self::SANS_ACCENT);
        if (class_exists(\Normalizer::class)) {
            // retire les accents restants (lettres hors de la table ci-dessus)
            $decompose = \Normalizer::normalize($valeur, \Normalizer::FORM_D);
            if (is_string($decompose)) {
                $valeur = preg_replace('/\p{Mn}+/u', '', $decompose) ?? $valeur;
            }
        }
        return preg_replace('/\s+/u', ' ', $valeur) ?? $valeur;
    }

    /** Identifiant de connexion : e-mail en minuscules, ou telephone sans espaces. */
    public static function identifiant(string $valeur): string
    {
        $valeur = trim($valeur);
        if (str_contains($valeur, '@')) {
            return mb_strtolower($valeur, 'UTF-8');
        }
        return preg_replace('/[\s().-]+/', '', $valeur) ?? $valeur;
    }

    public static function estIdentifiantValide(string $identifiant): bool
    {
        if (self::estIdentifiantLocal($identifiant)) {
            return true;
        }
        if (str_contains($identifiant, '@')) {
            return filter_var($identifiant, FILTER_VALIDATE_EMAIL) !== false;
        }
        return (bool) preg_match('/^\+?[0-9]{8,15}$/', $identifiant);
    }

    /** Identifiant propre a l'application : prenom@nomcourt (ex. marie@tuungane). Ce n'est pas une adresse e-mail. */
    public static function estIdentifiantLocal(string $identifiant): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{0,39}@[a-z0-9]{2,12}$/', $identifiant);
    }

    /** Nom court d'une organisation : minuscules et chiffres, 12 caracteres au plus. "Imf Kivu" => "imfkivu". */
    public static function nomCourt(string $valeur): string
    {
        return substr((string) preg_replace('/[^a-z0-9]/', '', strtolower(self::nom($valeur))), 0, 12);
    }

    /**
     * Nom court propose a partir du nom de l'organisation : sans les mots generiques, jamais coupe au milieu d'un mot
     * (les initiales si c'est trop long). "LIGHT VISION BUSINESS" => "lightvision".
     */
    public static function nomCourtPropose(string $nomOrganisation): string
    {
        $mots = array_values(array_filter(explode(' ', (string) preg_replace('/[^A-Z0-9 ]/', ' ', self::nom($nomOrganisation)))));
        $utiles = array_values(array_filter($mots, static fn ($m) => !in_array($m, ['MAISON', 'AVEC', 'DE', 'DU', 'DES', 'D', 'LA', 'LE', 'LES', 'L', 'ET', 'BUSINESS', 'GROUP', 'GROUPE', 'SARL', 'SA', 'COMPANY', 'SERVICES', 'THE', 'OF', 'AND'], true)));
        $utiles = $utiles ?: $mots;
        $colle = strtolower(implode('', $utiles));
        if (strlen($colle) <= 12) {
            return $colle;
        }
        $initiales = strtolower(implode('', array_map(static fn ($m) => $m[0], $utiles)));
        return strlen($initiales) >= 2 ? substr($initiales, 0, 12) : substr($colle, 0, 12);
    }

    /** Premiere partie d'un identifiant, tiree du prenom : "Marie-Claire Salumu" => "marieclaire". */
    public static function prenomPourIdentifiant(string $nom): string
    {
        $prenom = explode(' ', self::nom($nom))[0] ?? '';
        return substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($prenom)), 0, 20);
    }

    /** Mot de passe provisoire lisible (sans caracteres qui se confondent). */
    public static function motDePasseProvisoire(int $longueur = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < $longueur; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $s;
    }

    /** Code de profil a partir de son nom : "Chef d'agence" => "CHEF_D_AGENCE". */
    public static function code(string $valeur): string
    {
        $code = preg_replace('/[^A-Z0-9]+/', '_', self::nom($valeur)) ?? '';
        return substr(trim($code, '_'), 0, 40);
    }
}

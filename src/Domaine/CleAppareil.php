<?php
namespace AvecXY\Domaine;

/**
 * Connexion par empreinte ou par visage : c'est l'appareil qui reconnait la personne, puis il signe
 * un defi avec une cle privee qui ne le quitte jamais (norme WebAuthn). Le serveur ne garde que la cle publique.
 * Cette classe lit les reponses de l'appareil ; elle ne touche ni a la base ni a la session.
 */
final class CleAppareil
{
    public static function versTexte(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }

    public static function versOctets(string $texte): string
    {
        $octets = base64_decode(strtr($texte, '-_', '+/'), true);
        if ($octets === false) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        return $octets;
    }

    /**
     * Controle la partie signee par le navigateur : bon type d'operation, bon defi, bonne adresse du site.
     */
    public static function verifierClient(string $clientDataJson, string $type, string $defi, string $origine): void
    {
        $d = json_decode($clientDataJson, true);
        if (!is_array($d) || ($d['type'] ?? '') !== $type || !hash_equals($defi, (string) ($d['challenge'] ?? '')) || ($d['origin'] ?? '') !== $origine) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
    }

    /**
     * Lit les donnees de l'appareil.
     * @return array{compteur:int,identifiant:?string,cle:?string} cle = cle publique au format PEM (a l'enregistrement seulement)
     */
    public static function lireDonnees(string $donnees, string $domaine): array
    {
        if (strlen($donnees) < 37 || !hash_equals(hash('sha256', $domaine, true), substr($donnees, 0, 32))) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $drapeaux = ord($donnees[32]);
        // 0x01 : la personne etait presente ; 0x04 : l'appareil l'a reconnue (empreinte, visage ou code de l'appareil)
        if (($drapeaux & 0x01) === 0 || ($drapeaux & 0x04) === 0) {
            throw new ErreurMetier('cle.non_verifiee');
        }
        $resultat = ['compteur' => unpack('N', substr($donnees, 33, 4))[1], 'identifiant' => null, 'cle' => null];
        if (($drapeaux & 0x40) !== 0) {
            if (strlen($donnees) < 55) {
                throw new ErreurMetier('cle.reponse_invalide');
            }
            $longueur = unpack('n', substr($donnees, 53, 2))[1];
            $resultat['identifiant'] = self::versTexte(substr($donnees, 55, $longueur));
            $position = 55 + $longueur;
            $resultat['cle'] = self::clePem(self::decoder($donnees, $position));
        }
        return $resultat;
    }

    /** Extrait les donnees de l'appareil de la reponse d'enregistrement. */
    public static function donneesDeLAttestation(string $attestation): string
    {
        $position = 0;
        $objet = self::decoder($attestation, $position);
        if (!is_array($objet) || !is_string($objet['authData'] ?? null)) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        return $objet['authData'];
    }

    /** La signature est-elle bien celle de la cle enregistree ? */
    public static function signatureValide(string $donnees, string $clientDataJson, string $signature, string $clePem): bool
    {
        return openssl_verify($donnees . hash('sha256', $clientDataJson, true), $signature, $clePem, OPENSSL_ALGO_SHA256) === 1;
    }

    /** Cle publique de l'appareil (format COSE) vers PEM, pour ES256 et RS256. */
    private static function clePem(mixed $cose): string
    {
        if (!is_array($cose)) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $type = $cose[1] ?? null;
        if ($type === 2 && ($cose[3] ?? null) === -7 && ($cose[-1] ?? null) === 1 && strlen((string) ($cose[-2] ?? '')) === 32 && strlen((string) ($cose[-3] ?? '')) === 32) {
            // courbe P-256 : en-tete fixe, puis le point non compresse
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $cose[-2] . $cose[-3];
        } elseif ($type === 3 && ($cose[3] ?? null) === -257 && is_string($cose[-1] ?? null) && is_string($cose[-2] ?? null)) {
            $cleRsa = self::der(0x30, self::entierDer($cose[-1]) . self::entierDer($cose[-2]));
            $der = self::der(0x30, hex2bin('300d06092a864886f70d0101010500') . self::der(0x03, "\x00" . $cleRsa));
        } else {
            throw new ErreurMetier('cle.type_non_gere');
        }
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der(int $etiquette, string $contenu): string
    {
        $l = strlen($contenu);
        $longueur = $l < 128 ? chr($l) : ($l < 256 ? "\x81" . chr($l) : "\x82" . pack('n', $l));
        return chr($etiquette) . $longueur . $contenu;
    }

    private static function entierDer(string $octets): string
    {
        $octets = ltrim($octets, "\x00");
        if ($octets === '' || (ord($octets[0]) & 0x80) !== 0) {
            $octets = "\x00" . $octets;
        }
        return self::der(0x02, $octets);
    }

    /**
     * Lecteur CBOR minimal (entiers, octets, textes, tableaux, tables) : ce qu'utilisent les appareils.
     */
    private static function decoder(string $d, int &$p): mixed
    {
        if ($p >= strlen($d)) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $octet = ord($d[$p++]);
        $majeur = $octet >> 5;
        $info = $octet & 0x1f;
        $valeur = match (true) {
            $info < 24 => $info,
            $info === 24 => self::lire($d, $p, 1, 'C'),
            $info === 25 => self::lire($d, $p, 2, 'n'),
            $info === 26 => self::lire($d, $p, 4, 'N'),
            $info === 27 => self::lire($d, $p, 8, 'J'),
            default => throw new ErreurMetier('cle.reponse_invalide'),
        };
        switch ($majeur) {
            case 0:
                return $valeur;
            case 1:
                return -1 - $valeur;
            case 2:
            case 3:
                if ($valeur > strlen($d) - $p) {
                    throw new ErreurMetier('cle.reponse_invalide');
                }
                $texte = substr($d, $p, $valeur);
                $p += $valeur;
                return $texte;
            case 4:
                $liste = [];
                for ($i = 0; $i < $valeur && $i < 64; $i++) {
                    $liste[] = self::decoder($d, $p);
                }
                return $liste;
            case 5:
                $table = [];
                for ($i = 0; $i < $valeur && $i < 64; $i++) {
                    $cle = self::decoder($d, $p);
                    $table[is_int($cle) || is_string($cle) ? $cle : 'x'] = self::decoder($d, $p);
                }
                return $table;
            default:
                throw new ErreurMetier('cle.reponse_invalide');
        }
    }

    private static function lire(string $d, int &$p, int $n, string $format): int
    {
        if ($p + $n > strlen($d)) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $v = unpack($format, substr($d, $p, $n))[1];
        $p += $n;
        return (int) $v;
    }
}

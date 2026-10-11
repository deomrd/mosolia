<?php
namespace AvecXY\Domaine;

/**
 * Regles d'un type de carnet, definies par chaque organisation.
 *
 * Mode PARTS : le carnet compte un nombre fixe de parts, decoupe en tranches.
 *   Dans chaque tranche, l'organisation prend ses parts de benefice (au debut
 *   ou a la fin de la tranche) ; le reste est l'epargne du client.
 *   31 parts, tranche 31, 1 part  => part 1 a l'organisation.
 *   90 parts, tranche 30, 1 part  => parts 1, 31 et 61 a l'organisation.
 * Mode LIBRE : depots et retraits de montants libres, le carnet reste ouvert ;
 *   l'organisation se remunere par des frais de tenue de compte (compte courant).
 *   Un solde minimum peut etre exige.
 * Mode BLOQUE (a l'ecran) : un compte libre dont l'argent est bloque pendant une duree
 *   choisie par le client parmi celles proposees ; l'organisation verse un interet.
 *   En base, mode = LIBRE et blocage = RETRAITS ou TOUT.
 * Compte annuel : un carnet a parts (372 parts, tranche 31, 1 part de benefice par tranche)
 *   avec une reserve : un retrait partiel doit laisser au moins N parts, tout retirer
 *   n'est possible qu'une fois le carnet complet.
 */
final class TypeCarnet
{
    public const MODES = ['PARTS', 'LIBRE', 'BLOQUE'];
    public const BLOCAGES = ['RETRAITS', 'TOUT'];
    public const TAUX_BASES = ['AN', 'MOIS', 'DUREE'];
    public const VERSEMENTS = ['FIN', 'MENSUEL'];
    public const ANTICIPES = ['INTERDIT', 'PENALITE', 'PERTE_INTERETS'];
    public const PRELEVEMENTS = ['DEBUT_TRANCHE', 'FIN_TRANCHE'];
    public const RETRAITS = ['TOTAL', 'PAR_TRANCHE', 'LIBRE'];
    public const FERMETURES = ['PREMIER_RETRAIT', 'DERNIERE_TRANCHE_RETIREE', 'ECHEANCE', 'MANUELLE'];
    public const FRAIS_TYPES = ['AUCUN', 'FIXE', 'POURCENTAGE_SOLDE', 'POURCENTAGE_RETRAIT'];
    public const FRAIS_FREQUENCES = ['MENSUEL', 'A_CHAQUE_RETRAIT', 'A_LA_CLOTURE'];

    /**
     * Controle une saisie et renvoie la ligne prete a enregistrer.
     * @param array<string,string> $d champs du formulaire
     * @return array<string,mixed>
     */
    public static function valider(array $d, int $decimalesDevise): array
    {
        $nom = Texte::nom($d['nom'] ?? '');
        if ($nom === '') {
            throw new ErreurMetier('carnets.nom_obligatoire');
        }
        $mode = in_array($d['mode'] ?? '', self::MODES, true) ? $d['mode'] : 'PARTS';
        // vide ou 0 = sans duree (le carnet s'arrete quand ses parts sont faites, ou a la main)
        $duree = trim((string) ($d['duree_jours'] ?? '')) === '' ? 0 : self::entier((string) $d['duree_jours'], 0, 3660, 'carnets.duree_invalide');
        if ($mode === 'BLOQUE') {
            $duree = 0;   // la duree vient de l'option choisie par le client
        }
        $prix = Montant::lire(($d['prix'] ?? '') === '' ? '0' : $d['prix'], $decimalesDevise, 'carnets.prix_invalide');

        $ligne = [
            'nom' => $nom, 'mode' => $mode, 'duree_jours' => $duree, 'prix' => $prix,
            'nombre_parts' => null, 'taille_tranche' => null, 'parts_benefice_par_tranche' => 0,
            'prelevement_benefice' => 'DEBUT_TRANCHE', 'mise_minimum' => '0.00', 'mise_maximum' => null,
            'retrait' => 'LIBRE', 'fermeture' => 'ECHEANCE', 'depot_minimum' => null,
            'blocage' => null, 'options_blocage' => null, 'taux_base' => 'AN', 'interets_versement' => 'FIN',
            'retrait_anticipe' => 'INTERDIT', 'penalite_anticipe' => '0.00', 'solde_minimum' => null, 'parts_reserve' => null, 'renouvellement' => 'AUCUN',
            'par_defaut' => ($d['par_defaut'] ?? '') === '1' ? 1 : 0,
            'actif' => ($d['actif'] ?? '1') === '0' ? 0 : 1,
        ];

        if ($mode === 'PARTS') {
            $parts = self::entier($d['nombre_parts'] ?? '', 1, 3660, 'carnets.parts_invalides');
            $tranche = ($d['taille_tranche'] ?? '') === '' ? $parts : self::entier($d['taille_tranche'], 1, $parts, 'carnets.tranche_invalide');
            $benefice = self::entier(($d['parts_benefice_par_tranche'] ?? '') === '' ? '0' : $d['parts_benefice_par_tranche'], 0, $tranche, 'carnets.benefice_invalide');
            if ($benefice >= $tranche) {
                throw new ErreurMetier('carnets.benefice_trop_grand');
            }
            $min = Montant::lire(($d['mise_minimum'] ?? '') === '' ? '0' : $d['mise_minimum'], $decimalesDevise, 'carnets.mise_invalide');
            $max = ($d['mise_maximum'] ?? '') === '' ? null : Montant::lire($d['mise_maximum'], $decimalesDevise, 'carnets.mise_invalide');
            if ($max !== null && Montant::comparer($max, $min) < 0) {
                throw new ErreurMetier('carnets.mise_max_inferieure');
            }
            $retrait = in_array($d['retrait'] ?? '', self::RETRAITS, true) ? $d['retrait'] : 'TOTAL';
            $fermeture = in_array($d['fermeture'] ?? '', self::FERMETURES, true) ? $d['fermeture'] : 'PREMIER_RETRAIT';
            if ($fermeture === 'DERNIERE_TRANCHE_RETIREE' && $retrait !== 'PAR_TRANCHE') {
                throw new ErreurMetier('carnets.fermeture_incoherente');
            }
            if ($fermeture === 'ECHEANCE' && $duree === 0) {
                throw new ErreurMetier('carnets.echeance_sans_duree');
            }
            if ($retrait === 'TOTAL' && $fermeture !== 'PREMIER_RETRAIT') {
                // un retrait total vide le carnet : il se ferme a ce moment-la
                $fermeture = 'PREMIER_RETRAIT';
            }
            $ligne = array_merge($ligne, [
                'nombre_parts' => $parts, 'taille_tranche' => $tranche, 'parts_benefice_par_tranche' => $benefice,
                'prelevement_benefice' => in_array($d['prelevement_benefice'] ?? '', self::PRELEVEMENTS, true) ? $d['prelevement_benefice'] : 'DEBUT_TRANCHE',
                'mise_minimum' => $min, 'mise_maximum' => $max, 'retrait' => $retrait, 'fermeture' => $fermeture,
            ]);
            // reserve : seulement avec des retraits libres (compte annuel)
            if ($retrait === 'LIBRE' && trim((string) ($d['parts_reserve'] ?? '')) !== '') {
                $reserve = self::entier((string) $d['parts_reserve'], 0, $parts, 'carnets.reserve_invalide');
                $ligne['parts_reserve'] = $reserve > 0 ? $reserve : null;
            }
        } else {
            $ligne['mode'] = 'LIBRE';
            $ligne['depot_minimum'] = ($d['depot_minimum'] ?? '') === '' ? null : Montant::lire($d['depot_minimum'], $decimalesDevise, 'carnets.depot_invalide');
            $ligne['solde_minimum'] = ($d['solde_minimum'] ?? '') === '' ? null : Montant::lire($d['solde_minimum'], $decimalesDevise, 'carnets.solde_min_invalide');
            if ($ligne['solde_minimum'] !== null && Montant::estNul($ligne['solde_minimum'])) {
                $ligne['solde_minimum'] = null;
            }
            $ligne['fermeture'] = ($d['fermeture'] ?? '') === 'MANUELLE' ? 'MANUELLE' : 'ECHEANCE';
            if ($ligne['fermeture'] === 'ECHEANCE' && $duree === 0 && $mode === 'LIBRE') {
                $ligne['fermeture'] = 'MANUELLE';   // un compte courant sans duree se ferme a la main
            }
            if ($mode === 'BLOQUE') {
                $ligne = array_merge($ligne, self::validerBlocage($d));
            }
        }

        return array_merge($ligne, self::validerFrais($d, $decimalesDevise));
    }

    /** Regles d'un compte bloque : ce qui est bloque, durees et taux proposes, interets, sortie anticipee. */
    private static function validerBlocage(array $d): array
    {
        $options = [];
        $mois = (array) ($d['options_mois'] ?? []);
        $taux = (array) ($d['options_taux'] ?? []);
        foreach ($mois as $i => $m) {
            $m = trim((string) $m);
            $t = trim((string) ($taux[$i] ?? ''));
            if ($m === '' && $t === '') {
                continue;
            }
            $n = self::entier($m, 1, 120, 'carnets.option_mois_invalide');
            $pct = Montant::lire($t === '' ? '0' : $t, 2, 'carnets.option_taux_invalide');
            if (Montant::comparer($pct, '100.00') > 0) {
                throw new ErreurMetier('carnets.option_taux_invalide');
            }
            $options[$n] = ['mois' => $n, 'taux' => $pct];
        }
        if ($options === []) {
            throw new ErreurMetier('carnets.options_obligatoires');
        }
        ksort($options);
        $anticipe = in_array($d['retrait_anticipe'] ?? '', self::ANTICIPES, true) ? $d['retrait_anticipe'] : 'INTERDIT';
        $penalite = '0.00';
        if ($anticipe === 'PENALITE') {
            $penalite = Montant::lire(($d['penalite_anticipe'] ?? '') === '' ? '0' : (string) $d['penalite_anticipe'], 2, 'carnets.penalite_invalide');
            if (Montant::estNul($penalite) || Montant::comparer($penalite, '100.00') > 0) {
                throw new ErreurMetier('carnets.penalite_invalide');
            }
        }
        return [
            'blocage' => in_array($d['blocage'] ?? '', self::BLOCAGES, true) ? $d['blocage'] : 'RETRAITS',
            'options_blocage' => json_encode(array_values($options)),
            'taux_base' => in_array($d['taux_base'] ?? '', self::TAUX_BASES, true) ? $d['taux_base'] : 'AN',
            'interets_versement' => in_array($d['interets_versement'] ?? '', self::VERSEMENTS, true) ? $d['interets_versement'] : 'FIN',
            'retrait_anticipe' => $anticipe, 'penalite_anticipe' => $penalite,
            'renouvellement' => ($d['renouvellement'] ?? '') === 'AUTOMATIQUE' ? 'AUTOMATIQUE' : 'AUCUN',
        ];
    }

    /** @return array<int,array{mois:int,taux:string}> durees proposees par un type de compte bloque */
    public static function options(array $type): array
    {
        $o = json_decode((string) ($type['options_blocage'] ?? ''), true);
        return is_array($o) ? $o : [];
    }

    /** Mode affiche a l'ecran : PARTS, LIBRE ou BLOQUE. */
    public static function modeAffiche(array $ligne): string
    {
        return ($ligne['blocage'] ?? null) !== null && $ligne['blocage'] !== '' ? 'BLOQUE' : (string) $ligne['mode'];
    }

    /** @return array{frais_tenue_type:string,frais_tenue_valeur:string,frais_tenue_frequence:?string} */
    private static function validerFrais(array $d, int $decimalesDevise): array
    {
        $type = in_array($d['frais_tenue_type'] ?? '', self::FRAIS_TYPES, true) ? $d['frais_tenue_type'] : 'AUCUN';
        if ($type === 'AUCUN') {
            return ['frais_tenue_type' => 'AUCUN', 'frais_tenue_valeur' => '0.00', 'frais_tenue_frequence' => null];
        }
        // un pourcentage accepte toujours deux decimales (2,5 %), quelle que soit la devise
        $valeur = Montant::lire($d['frais_tenue_valeur'] ?? '', $type === 'FIXE' ? $decimalesDevise : 2, 'carnets.frais_invalides');
        if (Montant::estNul($valeur)) {
            throw new ErreurMetier('carnets.frais_invalides');
        }
        if ($type !== 'FIXE' && Montant::comparer($valeur, '100.00') > 0) {
            throw new ErreurMetier('carnets.frais_pourcentage');
        }
        $frequence = in_array($d['frais_tenue_frequence'] ?? '', self::FRAIS_FREQUENCES, true) ? $d['frais_tenue_frequence'] : 'MENSUEL';
        if ($type === 'POURCENTAGE_RETRAIT') {
            $frequence = 'A_CHAQUE_RETRAIT';
        }
        return ['frais_tenue_type' => $type, 'frais_tenue_valeur' => $valeur, 'frais_tenue_frequence' => $frequence];
    }

    /**
     * Numeros des parts qui reviennent a l'organisation.
     * @return int[]
     */
    public static function partsBenefice(int $nombreParts, int $tailleTranche, int $parTranche, string $prelevement): array
    {
        $numeros = [];
        if ($tailleTranche < 1 || $parTranche < 1) {
            return $numeros;
        }
        for ($debut = 1; $debut <= $nombreParts; $debut += $tailleTranche) {
            $fin = min($debut + $tailleTranche - 1, $nombreParts);
            for ($i = 0; $i < $parTranche; $i++) {
                $n = $prelevement === 'FIN_TRANCHE' ? $fin - $i : $debut + $i;
                if ($n >= $debut && $n <= $fin) {
                    $numeros[] = $n;
                }
            }
        }
        sort($numeros);
        return $numeros;
    }

    /** La part numero N est-elle une part de benefice ? (utilise a chaque cotisation) */
    public static function estPartBenefice(int $numero, int $nombreParts, int $tailleTranche, int $parTranche, string $prelevement): bool
    {
        return in_array($numero, self::partsBenefice($nombreParts, $tailleTranche, $parTranche, $prelevement), true);
    }

    public static function nombreTranches(int $nombreParts, int $tailleTranche): int
    {
        return $tailleTranche < 1 ? 0 : (int) ceil($nombreParts / $tailleTranche);
    }

    private static function entier(string $v, int $min, int $max, string $cle): int
    {
        $v = trim($v);
        if (!ctype_digit($v) || (int) $v < $min || (int) $v > $max) {
            throw new ErreurMetier($cle, ['min' => $min, 'max' => $max]);
        }
        return (int) $v;
    }
}

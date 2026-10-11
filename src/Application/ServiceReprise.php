<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\Texte;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotClients;
use AvecXY\Infrastructure\DepotCredits;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotRecolteurs;
use AvecXY\Infrastructure\DepotTypesCarnet;
use AvecXY\Infrastructure\Tableur;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Reprise de l'existant : une organisation qui travaillait sur papier remplit un classeur Excel
 * (recolteurs, clients et carnets en cours, credits en cours et leurs echeances) puis l'importe.
 *
 * Ce qui a ete verse, retire ou rembourse avant l'application est enregistre comme point de depart :
 * il compte dans le solde du client mais ne passe ni par la caisse du jour, ni par le controle, et le
 * benefice deja pris sur papier n'est pas partage une seconde fois.
 * L'import se fait en deux temps : analyse (rien n'est enregistre) puis confirmation.
 */
final class ServiceReprise
{
    /** Noms des feuilles, en francais puis en anglais. Les colonnes sont lues par position. */
    public const FEUILLES = [
        'aide' => ['Mode d\'emploi', 'Instructions'],
        'recolteurs' => ['Récolteurs', 'Collectors'],
        'clients' => ['Clients et carnets', 'Clients and booklets'],
        'credits' => ['Crédits en cours', 'Open loans'],
        'echeances' => ['Échéances', 'Instalments'],
        'listes' => ['Listes', 'Lists'],
    ];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotRecolteurs $recolteurs,
        private readonly DepotTypesCarnet $types,
        private readonly DepotCredits $credits,
        private readonly DepotClients $clients,
        private readonly DepotCarnets $carnets,
        private readonly DepotCaisse $caisse,
        private readonly DepotOrganisations $organisations,
        private readonly ServiceRecolteurs $serviceRecolteurs,
        private readonly RecalculCarnet $recalcul,
        private readonly ServiceCredits $serviceCredits,
        private readonly Audit $audit,
    ) {
    }

    /** L'administrateur choisit de partir de vide (il pourra toujours importer plus tard). */
    public function partirDeVide(Contexte $c): void
    {
        $this->organisations->modifier($c->organisationId(), ['demarrage' => 'VIDE']);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'reprise.partir_de_vide', 'organisation', $c->organisationId());
    }

    public function liste(int $org): array
    {
        return $this->bd->tous('SELECT r.*, u.nom AS auteur_nom FROM avecxy_reprises r LEFT JOIN avecxy_utilisateurs u ON u.id = r.fait_par WHERE r.organisation_id = ? ORDER BY r.id DESC', [$org]);
    }

    // ------------------------------------------------------------------
    // Modele a telecharger
    // ------------------------------------------------------------------

    /** Classeur pret a remplir, avec les recolteurs, les types de carnet et les regles de credit de l'organisation dans les listes. */
    public function modele(Contexte $c, string $langue): string
    {
        $org = $c->organisationId();
        $l = $langue === 'en' ? 1 : 0;
        $nom = static fn (string $cle): string => self::FEUILLES[$cle][$l];
        $types = array_values(array_filter($this->types->liste($org), static fn ($t) => (int) $t['actif'] === 1));
        $produits = $c->module('credit') ? $this->credits->produits($org, true) : [];
        $recolteurs = array_values(array_filter($this->recolteurs->liste($org), static fn ($r) => $r['statut'] === 'ACTIF'));

        $aide = [[t('reprise.xl.titre', ['organisation' => $c->organisation['nom']])], ['']];
        foreach (range(1, 12) as $i) {
            $aide[] = [t('reprise.xl.aide' . $i)];
        }
        $aide[] = [''];
        $aide[] = [t('reprise.xl.types')];
        foreach ($types as $ty) {
            $aide[] = ['  • ' . $ty['nom'] . ' : ' . ($ty['mode'] === 'PARTS'
                ? t('reprise.xl.type_parts', ['parts' => $ty['nombre_parts'], 'min' => Montant::afficher($ty['mise_minimum'], $c->decimales())])
                : t('reprise.xl.type_libre'))];
        }

        $listes = [[t('reprise.xl.col.type'), t('reprise.xl.col.produit'), t('reprise.xl.oui_non')]];
        for ($i = 0; $i < max(2, count($types), count($produits)); $i++) {
            $listes[] = [$types[$i]['nom'] ?? '', $produits[$i]['nom'] ?? '', [t('reprise.xl.oui'), t('reprise.xl.non')][$i] ?? ''];
        }
        $plage = fn (string $col, int $n): string => "'" . str_replace("'", "''", $nom('listes')) . "'!\$" . $col . '$2:$' . $col . '$' . (max(1, $n) + 1);
        $ouiNon = $plage('C', 2);
        $titre = static fn (array $obligatoires, int $total): array => [0 => array_map(static fn ($i) => in_array($i, $obligatoires, true) ? Tableur::TITRE_OBLIGATOIRE : Tableur::TITRE_FACULTATIF, range(0, $total - 1))];
        $cols = static fn (string $prefixe, array $cles): array => array_map(static fn ($k) => t('reprise.xl.col.' . $prefixe . $k), $cles);

        $feuilleRecolteurs = [$cols('', ['recolteur_nom', 'telephone'])];
        foreach ($recolteurs as $r) {
            $feuilleRecolteurs[] = [$r['nom'], (string) ($r['telephone'] ?? '')];
        }

        return Tableur::ecrire([
            ['nom' => $nom('aide'), 'lignes' => $aide, 'largeurs' => [120], 'styles_lignes' => [0 => Tableur::GRAND_TITRE] + array_fill(1, count($aide), Tableur::NOTE)],
            ['nom' => $nom('recolteurs'), 'fige' => true, 'lignes' => $feuilleRecolteurs, 'largeurs' => [34, 20],
                'styles_colonnes' => [1 => Tableur::TEXTE], 'styles_lignes' => $titre([0], 2)],
            ['nom' => $nom('clients'), 'fige' => true,
                'lignes' => [$cols('', ['ref_client', 'client_nom', 'telephone', 'recolteur', 'type', 'mise', 'mises_versees', 'solde', 'date_ouverture', 'deja_beneficie', 'tranches_retirees', 'adresse', 'activite'])],
                'largeurs' => [11, 32, 18, 26, 24, 13, 13, 15, 15, 14, 13, 24, 22],
                'styles_colonnes' => [2 => Tableur::TEXTE, 8 => Tableur::DATE], 'styles_lignes' => $titre([0, 1, 3], 13),
                'validations' => [
                    ['colonne' => 0, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                    ['colonne' => 3, 'type' => 'liste', 'source' => "'" . str_replace("'", "''", $nom('recolteurs')) . "'!\$A\$2:\$A\$500", 'message' => t('reprise.xl.err_liste')],
                    ['colonne' => 4, 'type' => 'liste', 'source' => $plage('A', count($types)), 'message' => t('reprise.xl.err_liste')],
                    ['colonne' => 5, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 6, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                    ['colonne' => 7, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 8, 'type' => 'date', 'message' => t('reprise.xl.err_date')],
                    ['colonne' => 9, 'type' => 'liste', 'source' => $ouiNon, 'message' => t('reprise.xl.err_liste')],
                    ['colonne' => 10, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                ]],
            ['nom' => $nom('credits'), 'fige' => true,
                'lignes' => [$cols('', ['ref_credit', 'ref_client', 'produit', 'montant_accorde', 'taux', 'date_octroi', 'date_echeance', 'deja_rembourse', 'reste_du'])],
                'largeurs' => [11, 11, 26, 16, 10, 15, 15, 16, 16],
                'styles_colonnes' => [5 => Tableur::DATE, 6 => Tableur::DATE], 'styles_lignes' => $titre([0, 1, 3, 5, 6], 9),
                'validations' => [
                    ['colonne' => 0, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                    ['colonne' => 1, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                    ['colonne' => 2, 'type' => 'liste', 'source' => $plage('B', count($produits)), 'message' => t('reprise.xl.err_liste')],
                    ['colonne' => 3, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 4, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 5, 'type' => 'date', 'message' => t('reprise.xl.err_date')],
                    ['colonne' => 6, 'type' => 'date', 'message' => t('reprise.xl.err_date')],
                    ['colonne' => 7, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 8, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                ]],
            ['nom' => $nom('echeances'), 'fige' => true,
                'lignes' => [$cols('', ['ref_credit', 'echeance_date', 'echeance_montant', 'echeance_payee'])],
                'largeurs' => [11, 15, 16, 12], 'styles_colonnes' => [1 => Tableur::DATE], 'styles_lignes' => $titre([0, 1, 2], 4),
                'validations' => [
                    ['colonne' => 0, 'type' => 'entier', 'message' => t('reprise.xl.err_entier')],
                    ['colonne' => 1, 'type' => 'date', 'message' => t('reprise.xl.err_date')],
                    ['colonne' => 2, 'type' => 'decimal', 'message' => t('reprise.xl.err_nombre')],
                    ['colonne' => 3, 'type' => 'liste', 'source' => $ouiNon, 'message' => t('reprise.xl.err_liste')],
                ]],
            ['nom' => $nom('listes'), 'cachee' => true, 'lignes' => $listes, 'largeurs' => [30, 30, 12]],
        ]);
    }

    // ------------------------------------------------------------------
    // Analyse du fichier rempli (rien n'est enregistre)
    // ------------------------------------------------------------------

    /**
     * @return array{recolteurs:array,clients:array,credits:array,erreurs:array,avertissements:array,totaux:array,date:string,caisse:int}
     */
    public function analyser(Contexte $c, string $chemin, string $dateDemarrage, string $caisseSaisie): array
    {
        $org = $c->organisationId();
        $dec = $c->decimales();
        $date = Dates::passee($dateDemarrage);
        $caisse = trim($caisseSaisie) === '' ? 0 : Montant::enCentimes(Montant::lire($caisseSaisie, $dec));
        $aujourdhui = date('Y-m-d');
        $feuilles = $this->feuilles(Tableur::lire($chemin));
        $erreurs = [];
        $avertissements = [];
        $erreur = static function (string $feuille, int $ligne, string $cle, array $v = []) use (&$erreurs): void {
            $erreurs[] = ['feuille' => self::FEUILLES[$feuille][0], 'ligne' => $ligne, 'message' => t('reprise.err.' . $cle, $v)];
        };
        $avertir = static function (string $feuille, int $ligne, string $cle, array $v = []) use (&$avertissements): void {
            $avertissements[] = ['feuille' => self::FEUILLES[$feuille][0], 'ligne' => $ligne, 'message' => t('reprise.avert.' . $cle, $v)];
        };
        $argent = static fn (int $centimes): string => Montant::afficher(Montant::depuisCentimes($centimes), $dec);

        // --- recolteurs : ceux de l'application, puis ceux du fichier
        $recolteurs = [];
        foreach ($this->recolteurs->liste($org) as $r) {
            $recolteurs[Texte::nom($r['nom'])] = ['id' => (int) $r['id'], 'nom' => $r['nom'], 'telephone' => $r['telephone'], 'ligne' => 0];
        }
        foreach ($feuilles['recolteurs'] as $n => $l) {
            $nom = Texte::nom($l[0] ?? '');
            if ($nom === '') {
                $erreur('recolteurs', $n, 'nom_manquant');
                continue;
            }
            $recolteurs[$nom] ??= ['id' => 0, 'nom' => $nom, 'telephone' => self::telephone($l[1] ?? ''), 'ligne' => $n];
        }

        // --- types de carnet et regles de credit de l'organisation
        $types = [];
        foreach ($this->types->liste($org) as $ty) {
            if ((int) $ty['actif'] === 1) {
                $types[Texte::nom($ty['nom'])] = $ty;
            }
        }
        $produits = [];
        foreach ($this->credits->produits($org, true) as $p) {
            $produits[Texte::nom($p['nom'])] = $p;
        }

        // --- clients et carnets
        $clients = [];
        $vus = [];
        $epargneTotale = 0;
        $nbCarnets = 0;
        foreach ($feuilles['clients'] as $n => $l) {
            $ref = self::reference($l[0] ?? '');
            if ($ref === '') {
                $erreur('clients', $n, 'ref_manquante');
                continue;
            }
            if (!isset($clients[$ref])) {
                $nom = Texte::nom($l[1] ?? '');
                $nomRecolteur = Texte::nom($l[3] ?? '');
                if ($nom === '') {
                    $erreur('clients', $n, 'nom_manquant');
                    continue;
                }
                if ($nomRecolteur === '' || !isset($recolteurs[$nomRecolteur])) {
                    $erreur('clients', $n, 'recolteur_inconnu', ['nom' => $l[3] ?? '']);
                    continue;
                }
                $r = $recolteurs[$nomRecolteur];
                if ($r['id'] > 0 && $this->clients->homonymes($org, $nom, 'RECOLTEUR', $r['id'])) {
                    $erreur('clients', $n, 'client_existe', ['nom' => $nom]);
                    continue;
                }
                if (isset($vus[$nom . '|' . $nomRecolteur])) {
                    $avertir('clients', $n, 'meme_nom', ['nom' => $nom, 'ligne' => $vus[$nom . '|' . $nomRecolteur]]);
                }
                $vus[$nom . '|' . $nomRecolteur] = $n;
                $clients[$ref] = [
                    'ref' => $ref, 'ligne' => $n, 'nom' => $nom, 'telephone' => self::telephone($l[2] ?? ''), 'recolteur' => $nomRecolteur,
                    'adresse' => Texte::nom($l[11] ?? ''), 'activite' => Texte::nom($l[12] ?? ''), 'carnets' => [],
                ];
            }
            $nomType = Texte::nom($l[4] ?? '');
            if ($nomType === '') {
                if (($l[5] ?? '') !== '' || ($l[6] ?? '') !== '' || ($l[7] ?? '') !== '') {
                    $erreur('clients', $n, 'type_manquant');
                } elseif ($clients[$ref]['ligne'] !== $n) {
                    $erreur('clients', $n, 'ref_en_double', ['ref' => $ref]);
                }
                continue;   // client sans carnet
            }
            $type = $types[$nomType] ?? null;
            if ($type === null) {
                $erreur('clients', $n, 'type_inconnu', ['nom' => $l[4]]);
                continue;
            }
            $ouverture = ($l[8] ?? '') === '' ? $date : Tableur::date($l[8]);
            if ($ouverture === null) {
                $erreur('clients', $n, 'date_invalide', ['valeur' => $l[8]]);
                continue;
            }
            if ($ouverture > $date) {
                $erreur('clients', $n, 'ouvert_apres', ['date' => jour($ouverture)]);
                continue;
            }
            $k = [
                'ligne' => $n, 'type_id' => (int) $type['id'], 'type_nom' => $type['nom'], 'mode' => $type['mode'], 'date_ouverture' => $ouverture,
                'mise' => null, 'reprise_parts' => 0, 'reprise_montant' => '0.00', 'reprise_tranches' => 0, 'reprise_retire' => '0.00',
                'benefice_deja_pris' => self::oui($l[9] ?? '') ? 1 : 0,
            ];
            try {
                $solde = ($l[7] ?? '') === '' ? null : self::montant($l[7], $dec);
                if ($type['mode'] === 'PARTS') {
                    $mise = ($l[5] ?? '') === '' ? null : self::montant($l[5], $dec);
                    if ($mise === null || $mise <= 0) {
                        $erreur('clients', $n, 'mise_manquante');
                        continue;
                    }
                    if ($mise < Montant::enCentimes($type['mise_minimum']) || ($type['mise_maximum'] !== null && $mise > Montant::enCentimes($type['mise_maximum']))) {
                        $erreur('clients', $n, 'mise_hors_limites', ['mise' => $argent($mise), 'min' => Montant::afficher($type['mise_minimum'], $dec)]);
                        continue;
                    }
                    if (($l[6] ?? '') === '') {
                        if ($solde !== null && $solde > 0) {
                            $erreur('clients', $n, 'mises_versees_manquantes');
                            continue;
                        }
                        $parts = 0;
                    } else {
                        $parts = self::entier($l[6]);
                    }
                    if ($parts > (int) $type['nombre_parts']) {
                        $erreur('clients', $n, 'trop_de_mises', ['n' => $parts, 'max' => (int) $type['nombre_parts']]);
                        continue;
                    }
                    $k['mise'] = Montant::depuisCentimes($mise);
                    $k['reprise_parts'] = $parts;
                    $tranches = ($l[10] ?? '') === '' ? 0 : self::entier($l[10]);
                    $pseudo = $type + ['mise' => $k['mise'], 'reprise_parts' => $parts, 'benefice_deja_pris' => $k['benefice_deja_pris']];
                    if ($tranches > 0) {
                        if ($type['retrait'] !== 'PAR_TRANCHE' || $tranches > CalculCarnet::tranchesCompletes($pseudo, $parts)) {
                            $erreur('clients', $n, 'tranches_invalides', ['n' => $tranches]);
                            continue;
                        }
                        $retire = 0;
                        for ($t = 1; $t <= $tranches; $t++) {
                            $retire += CalculCarnet::epargneDeTranche($pseudo, $t);
                        }
                        $k['reprise_tranches'] = $tranches;
                        $k['reprise_retire'] = Montant::depuisCentimes($retire);
                    }
                    $epargne = CalculCarnet::repartir($pseudo, [])['epargne_brute'] - Montant::enCentimes($k['reprise_retire']);
                    if ($epargne < 0) {
                        $erreur('clients', $n, 'solde_negatif');
                        continue;
                    }
                    if ($solde !== null && $solde !== $epargne) {
                        $avertir('clients', $n, 'solde_different', ['fichier' => $argent($solde), 'calcule' => $argent($epargne)]);
                    }
                } else {
                    $epargne = $solde ?? 0;
                    $k['reprise_montant'] = Montant::depuisCentimes($epargne);
                }
            } catch (\InvalidArgumentException $e) {
                $erreur('clients', $n, 'nombre_invalide', ['valeur' => $e->getMessage()]);
                continue;
            }
            $k['epargne'] = $epargne;
            $epargneTotale += $epargne;
            $nbCarnets++;
            $clients[$ref]['carnets'][] = $k;
        }

        // --- credits en cours
        $credits = [];
        $encours = 0;
        if ($feuilles['credits'] && !$c->module('credit')) {
            $erreur('credits', 1, 'module_credit');
        } else {
            foreach ($feuilles['credits'] as $n => $l) {
                $ref = self::reference($l[0] ?? '');
                $refClient = self::reference($l[1] ?? '');
                if ($ref === '' || isset($credits[$ref])) {
                    $erreur('credits', $n, $ref === '' ? 'ref_manquante' : 'ref_credit_en_double', ['ref' => $ref]);
                    continue;
                }
                if (!isset($clients[$refClient])) {
                    $erreur('credits', $n, 'client_introuvable', ['ref' => $refClient]);
                    continue;
                }
                $nomProduit = Texte::nom($l[2] ?? '');
                $produit = $nomProduit === '' ? (count($produits) === 1 ? reset($produits) : null) : ($produits[$nomProduit] ?? null);
                if ($produit === null) {
                    $erreur('credits', $n, $produits ? 'produit_inconnu' : 'aucun_produit', ['nom' => $l[2] ?? '']);
                    continue;
                }
                $octroi = Tableur::date($l[5] ?? '');
                $echeance = Tableur::date($l[6] ?? '');
                if ($octroi === null || $echeance === null || $octroi > $aujourdhui || $echeance <= $octroi) {
                    $erreur('credits', $n, 'dates_credit');
                    continue;
                }
                try {
                    $montant = self::montant($l[3] ?? '', $dec);
                    $deja = ($l[7] ?? '') === '' ? 0 : self::montant($l[7], $dec);
                    $taux = ($l[4] ?? '') === '' ? (string) $produit['taux_interet'] : Montant::depuisCentimes(self::montant($l[4], 2));
                    $reste = ($l[8] ?? '') === '' ? null : self::montant($l[8], $dec);
                } catch (\InvalidArgumentException $e) {
                    $erreur('credits', $n, 'nombre_invalide', ['valeur' => $e->getMessage()]);
                    continue;
                }
                if ($montant <= 0 || (float) $taux > 999.99) {
                    $erreur('credits', $n, 'montant_credit');
                    continue;
                }
                // le reste du inscrit au cahier fait foi : l'interet en decoule (il peut inclure des reconductions deja appliquees)
                $interet = $reste === null ? Montant::arrondirDevise(Montant::pourcentage($montant, $taux), $dec) : $reste + $deja - $montant;
                if ($interet < 0 || $deja >= $montant + $interet) {
                    $erreur('credits', $n, $interet < 0 ? 'reste_incoherent' : 'credit_solde');
                    continue;
                }
                if ($echeance < $aujourdhui) {
                    $avertir('credits', $n, 'echeance_depassee', ['date' => jour($echeance)]);
                }
                $credits[$ref] = [
                    'ref' => $ref, 'ligne' => $n, 'client' => $refClient, 'produit_id' => (int) $produit['id'], 'produit_nom' => $produit['nom'],
                    'montant' => $montant, 'taux' => $taux, 'interet' => $interet, 'date_credit' => $octroi, 'date_echeance' => $echeance,
                    'deja' => $deja, 'restant' => $montant + $interet - $deja, 'echeances' => [],
                    'taux_penalite' => $produit['taux_penalite'], 'reconduction' => (int) $produit['reconduction'],
                ];
                $encours += $montant + $interet - $deja;
            }
            foreach ($feuilles['echeances'] as $n => $l) {
                $ref = self::reference($l[0] ?? '');
                if (!isset($credits[$ref])) {
                    $erreur('echeances', $n, 'credit_introuvable', ['ref' => $ref]);
                    continue;
                }
                $jour = Tableur::date($l[1] ?? '');
                try {
                    $montant = self::montant($l[2] ?? '', $dec);
                } catch (\InvalidArgumentException) {
                    $montant = 0;
                }
                if ($jour === null || $montant <= 0) {
                    $erreur('echeances', $n, 'echeance_invalide');
                    continue;
                }
                $credits[$ref]['echeances'][] = ['date' => $jour, 'montant' => $montant];
            }
            foreach ($credits as $ref => &$cr) {
                if ($cr['echeances']) {
                    usort($cr['echeances'], static fn ($a, $b) => strcmp($a['date'], $b['date']));
                    $somme = array_sum(array_column($cr['echeances'], 'montant'));
                    if ($somme === $cr['restant'] && $cr['deja'] > 0) {
                        // seules les echeances a venir sont listees : ce qui est deja rembourse forme une premiere echeance, payee
                        array_unshift($cr['echeances'], ['date' => $cr['date_credit'], 'montant' => $cr['deja']]);
                    } elseif ($somme !== $cr['montant'] + $cr['interet']) {
                        $avertir('credits', $cr['ligne'], 'echeances_total', ['somme' => $argent($somme), 'reste' => $argent($cr['restant']), 'total' => $argent($cr['montant'] + $cr['interet'])]);
                    }
                }
            }
            unset($cr);
        }

        // --- seuls les recolteurs utiles sont crees ; limites du forfait
        $utilises = array_flip(array_column($clients, 'recolteur'));
        $aCreer = array_filter($recolteurs, static fn ($r, $nom) => $r['id'] === 0 && ($r['ligne'] > 0), ARRAY_FILTER_USE_BOTH);
        if (!$clients && !$aCreer) {
            $erreur('clients', 1, 'fichier_vide');
        }
        $max = $c->forfait['max_clients'] ?? null;
        if ($max !== null && $this->clients->compter($org) + count($clients) > (int) $max) {
            $erreur('clients', 1, 'limite_clients', ['n' => (int) $max]);
        }
        $max = $c->forfait['max_agents'] ?? null;
        if ($max !== null && $this->recolteurs->compter($org) + count($aCreer) > (int) $max) {
            $erreur('recolteurs', 1, 'limite_recolteurs', ['n' => (int) $max]);
        }
        unset($utilises);

        return [
            'recolteurs' => $recolteurs, 'clients' => $clients, 'credits' => $credits, 'erreurs' => $erreurs, 'avertissements' => $avertissements,
            'date' => $date, 'caisse' => $caisse,
            'totaux' => [
                'recolteurs' => count($aCreer), 'clients' => count($clients), 'carnets' => $nbCarnets, 'credits' => count($credits),
                'epargne' => $epargneTotale, 'encours' => $encours, 'caisse' => $caisse,
            ],
        ];
    }

    /** Retrouve les quatre feuilles de donnees par leur nom (francais ou anglais), sinon par leur position. */
    private function feuilles(array $classeur): array
    {
        $parNom = [];
        foreach ($classeur as $f) {
            $parNom[Texte::nom($f['nom'])] = $f['lignes'];
        }
        $sortie = [];
        foreach (['recolteurs' => 1, 'clients' => 2, 'credits' => 3, 'echeances' => 4] as $cle => $position) {
            $lignes = $parNom[Texte::nom(self::FEUILLES[$cle][0])] ?? $parNom[Texte::nom(self::FEUILLES[$cle][1])] ?? null;
            if ($lignes === null) {
                if (!isset($classeur[$position]) || count($classeur) < 5) {
                    throw new ErreurMetier('reprise.feuille_manquante', ['nom' => self::FEUILLES[$cle][0]]);
                }
                $lignes = $classeur[$position]['lignes'];
            }
            unset($lignes[1]);   // ligne des titres
            $sortie[$cle] = $lignes;
        }
        return $sortie;
    }

    /** Montant d'une cellule, en centimes. @throws \InvalidArgumentException */
    private static function montant(string $v, int $decimales): int
    {
        $n = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($v));
        if ($n === '' || !is_numeric($n) || (float) $n < 0 || (float) $n > 9e12) {
            throw new \InvalidArgumentException($v);
        }
        return Montant::arrondirDevise((int) round((float) $n * 100), $decimales);
    }

    /** @throws \InvalidArgumentException */
    private static function entier(string $v): int
    {
        $n = str_replace([' ', ','], ['', '.'], trim($v));
        if (!is_numeric($n) || (float) $n < 0 || abs((float) $n - round((float) $n)) > 0.0001 || (float) $n > 100000) {
            throw new \InvalidArgumentException($v);
        }
        return (int) round((float) $n);
    }

    /** Reference saisie par l'organisation (N° client, N° credit) : "12", "12.0" et " 12 " designent la meme. */
    private static function reference(string $v): string
    {
        $v = trim($v);
        return is_numeric($v) && abs((float) $v - round((float) $v)) < 0.0001 ? (string) (int) round((float) $v) : mb_strtoupper($v);
    }

    private static function telephone(string $v): ?string
    {
        $v = trim($v);
        if ($v !== '' && is_numeric($v) && str_contains($v, '.')) {
            $v = (string) (int) round((float) $v);   // Excel a transforme le numero en nombre
        }
        return $v === '' ? null : mb_substr(Texte::identifiant($v), 0, 30);
    }

    private static function oui(string $v): bool
    {
        return in_array(mb_strtoupper(trim($v)), ['OUI', 'O', 'YES', 'Y', '1', 'TRUE', 'VRAI', 'X'], true);
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /** Enregistre une analyse sans erreur. @return int identifiant de la reprise */
    public function importer(Contexte $c, array $a): int
    {
        if ($a['erreurs']) {
            throw new ErreurMetier('reprise.erreurs_a_corriger');
        }
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $a): int {
            $maintenant = $this->bd->maintenant();
            // la date de demarrage sert de date d'effet (partage d'un benefice pas encore pris, solde d'ouverture)
            $effet = $a['date'] . ' ' . substr($maintenant, 11);
            $id = $this->bd->inserer('avecxy_reprises', [
                'organisation_id' => $org, 'date_demarrage' => $a['date'], 'statut' => 'VALIDEE', 'fait_par' => $c->utilisateurId(), 'cree_le' => $maintenant,
            ]);

            $agents = [];
            $utiles = array_flip(array_column($a['clients'], 'recolteur'));
            foreach ($a['recolteurs'] as $nom => $r) {
                if ($r['id'] === 0 && ($r['ligne'] > 0)) {
                    $cree = $this->serviceRecolteurs->enregistrer($c, 0, ['nom' => $r['nom'], 'telephone' => (string) $r['telephone'], 'adresse' => '', 'statut' => 'ACTIF', 'utilisateur_id' => 0]);
                    $this->recolteurs->modifier($org, $cree['id'], ['reprise_id' => $id]);
                    $r['id'] = $cree['id'];
                }
                $agents[$nom] = $r['id'];
            }
            unset($utiles);

            $idsClients = [];
            $nbCarnets = 0;
            foreach ($a['clients'] as $ref => $cl) {
                $agentId = $agents[$cl['recolteur']];
                $clientId = $this->clients->creer([
                    'organisation_id' => $org, 'agent_id' => $agentId, 'code' => $this->clients->prochainCode($org, $agentId), 'nom' => $cl['nom'],
                    'telephone' => $cl['telephone'], 'adresse' => $cl['adresse'] !== '' ? $cl['adresse'] : ($c->organisation['ville_defaut'] ?: null),
                    'activite' => $cl['activite'] !== '' ? $cl['activite'] : null, 'statut' => 'ACTIF', 'reprise_id' => $id,
                    'cree_par' => $c->utilisateurId(), 'cree_le' => $maintenant,
                ]);
                $idsClients[$ref] = ['id' => $clientId, 'agent' => $agentId];
                foreach ($cl['carnets'] as $k) {
                    $type = $this->types->parId($org, $k['type_id']);
                    $carnetId = $this->carnets->creer([
                        'organisation_id' => $org, 'client_id' => $clientId, 'agent_id' => $agentId, 'carnet_type_id' => $k['type_id'],
                        'numero' => $this->carnets->prochainNumero($org), 'prix' => '0.00', 'mode' => $type['mode'], 'mise' => $k['mise'],   // carnet deja paye sur papier
                        'nombre_parts' => $type['nombre_parts'], 'taille_tranche' => $type['taille_tranche'],
                        'parts_benefice_par_tranche' => $type['parts_benefice_par_tranche'], 'prelevement_benefice' => $type['prelevement_benefice'],
                        'retrait' => $type['retrait'], 'fermeture' => $type['fermeture'], 'frais_tenue_type' => $type['frais_tenue_type'],
                        'frais_tenue_valeur' => $type['frais_tenue_valeur'], 'frais_tenue_frequence' => $type['frais_tenue_frequence'],
                        'date_echeance' => (int) $type['duree_jours'] > 0 ? Dates::ajouterJours($k['date_ouverture'], (int) $type['duree_jours']) : null, 'statut' => 'ACTIF', 'date_ouverture' => $k['date_ouverture'],
                        'validation' => 'VALIDE', 'reprise_id' => $id, 'reprise_parts' => $k['reprise_parts'], 'reprise_montant' => $k['reprise_montant'],
                        'reprise_retire' => $k['reprise_retire'], 'reprise_tranches' => $k['reprise_tranches'], 'benefice_deja_pris' => $k['benefice_deja_pris'],
                        'cree_par' => $c->utilisateurId(), 'cree_le' => $effet,
                    ]);
                    $repris = $this->recalcul->recalculer($org, $carnetId);
                    // epargne au jour de la reprise : ecriture d'ouverture en comptabilite
                    $this->carnets->modifier($carnetId, ['reprise_epargne' => $repris['epargne']]);
                    $nbCarnets++;
                }
            }

            foreach ($a['credits'] as $cr) {
                $client = $idsClients[$cr['client']];
                $total = $cr['montant'] + $cr['interet'];
                $creditId = $this->credits->creerCredit([
                    'organisation_id' => $org, 'client_id' => $client['id'], 'agent_id' => $client['agent'], 'credit_produit_id' => $cr['produit_id'],
                    'eligibilite_id' => null, 'numero' => $this->credits->prochainNumero($org), 'date_credit' => $cr['date_credit'], 'date_echeance' => $cr['date_echeance'],
                    'duree_jours' => max(1, Dates::joursEntre($cr['date_credit'], $cr['date_echeance'])), 'reconduction' => $cr['reconduction'],
                    'montant' => Montant::depuisCentimes($cr['montant']), 'taux_interet' => $cr['taux'], 'montant_interet' => Montant::depuisCentimes($cr['interet']),
                    'taux_penalite' => $cr['taux_penalite'], 'total_penalites' => '0.00', 'total_du' => Montant::depuisCentimes($total),
                    'total_rembourse' => Montant::depuisCentimes($cr['deja']), 'restant' => Montant::depuisCentimes($total - $cr['deja']), 'statut' => 'EN_COURS',
                    'derogation' => 0, 'commentaire' => t('reprise.commentaire_credit'), 'reprise_id' => $id, 'reprise_rembourse' => Montant::depuisCentimes($cr['deja']),
                    'cree_par' => $c->utilisateurId(), 'cree_le' => $maintenant,
                ]);
                foreach ($cr['echeances'] as $i => $e) {
                    $this->credits->creerEcheance([
                        'organisation_id' => $org, 'credit_id' => $creditId, 'numero' => $i + 1, 'date_prevue' => $e['date'],
                        'montant' => Montant::depuisCentimes($e['montant']), 'cree_le' => $maintenant,
                    ]);
                }
                $this->serviceCredits->recalculer($org, $creditId);
            }

            if ($a['caisse'] > 0) {
                $this->caisse->creerMouvement([
                    'organisation_id' => $org, 'date_mouvement' => $a['date'], 'type' => 'APPORT', 'montant' => Montant::depuisCentimes($a['caisse']),
                    'source' => t('reprise.solde_ouverture'), 'statut' => 'VALIDE', 'reprise_id' => $id, 'cree_par' => $c->utilisateurId(), 'cree_le' => $maintenant,
                ]);
            }
            $t = $a['totaux'];
            $this->bd->modifier('avecxy_reprises', [
                'recolteurs' => $t['recolteurs'], 'clients' => $t['clients'], 'carnets' => $nbCarnets, 'credits' => $t['credits'],
                'epargne' => Montant::depuisCentimes($t['epargne']), 'encours' => Montant::depuisCentimes($t['encours']), 'caisse' => Montant::depuisCentimes($t['caisse']),
            ], ['id' => $id]);
            $this->organisations->modifier($org, ['demarrage' => 'REPRISE']);
            $this->audit->noter($org, $c->utilisateurId(), 'reprise.importer', 'reprise', $id, null, $t);
            return $id;
        });
    }

    /**
     * Annule entierement une reprise pour corriger le fichier et recommencer.
     * Possible tant qu'aucune operation n'a ete faite sur ce qui a ete repris.
     */
    public function annuler(Contexte $c, int $id): void
    {
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $id): void {
            $r = $this->bd->un("SELECT * FROM avecxy_reprises WHERE organisation_id = ? AND id = ? AND statut = 'VALIDEE'", [$org, $id]) ?? throw new ErreurMetier('reprise.introuvable');
            $n = static fn (BaseDeDonnees $bd, string $sql) => (int) $bd->valeur($sql, [$id]);
            $operations = $n($this->bd, 'SELECT COUNT(*) FROM avecxy_cotisations o JOIN avecxy_carnets k ON k.id = o.carnet_id WHERE k.reprise_id = ?')
                + $n($this->bd, 'SELECT COUNT(*) FROM avecxy_retraits o JOIN avecxy_carnets k ON k.id = o.carnet_id WHERE k.reprise_id = ?')
                + $n($this->bd, 'SELECT COUNT(*) FROM avecxy_credit_remboursements o JOIN avecxy_credits cr ON cr.id = o.credit_id WHERE cr.reprise_id = ?')
                + $n($this->bd, 'SELECT COUNT(*) FROM avecxy_carnets k JOIN avecxy_clients cl ON cl.id = k.client_id WHERE cl.reprise_id = ? AND k.reprise_id IS NULL')
                + $n($this->bd, 'SELECT COUNT(*) FROM avecxy_credits cr JOIN avecxy_clients cl ON cl.id = cr.client_id WHERE cl.reprise_id = ? AND cr.reprise_id IS NULL')
                + $n($this->bd, 'SELECT COUNT(*) FROM avecxy_credit_eligibilites e JOIN avecxy_clients cl ON cl.id = e.client_id WHERE cl.reprise_id = ?');
            if ($operations > 0) {
                throw new ErreurMetier('reprise.deja_utilisee');
            }
            $carnets = 'SELECT id FROM avecxy_carnets WHERE reprise_id = ?';
            $credits = 'SELECT id FROM avecxy_credits WHERE reprise_id = ?';
            $clients = 'SELECT id FROM avecxy_clients WHERE reprise_id = ?';
            foreach ([
                "DELETE FROM avecxy_benefices WHERE carnet_id IN ($carnets)",
                "DELETE FROM avecxy_benefices WHERE credit_id IN ($credits)",
                "DELETE FROM avecxy_frais_tenue WHERE carnet_id IN ($carnets)",
                "DELETE FROM avecxy_credit_penalites WHERE credit_id IN ($credits)",
                "DELETE FROM avecxy_credit_echeances WHERE credit_id IN ($credits)",
                'DELETE FROM avecxy_credits WHERE reprise_id = ?',
                'DELETE FROM avecxy_carnets WHERE reprise_id = ?',
                "DELETE FROM avecxy_notifications WHERE destinataire = 'CLIENT' AND destinataire_id IN ($clients)",
                "DELETE FROM avecxy_client_acces WHERE client_id IN ($clients)",
                'DELETE FROM avecxy_clients WHERE reprise_id = ?',
                'DELETE FROM avecxy_mouvements_fonds WHERE reprise_id = ?',
                // les recolteurs crees par la reprise ne partent que s'ils sont restes sans client ni compte
                'DELETE FROM avecxy_agents WHERE reprise_id = ? AND id NOT IN (SELECT agent_id FROM avecxy_clients) AND id NOT IN (SELECT agent_id FROM avecxy_utilisateurs WHERE agent_id IS NOT NULL)',
            ] as $sql) {
                $this->bd->executer($sql, [$id]);
            }
            $this->bd->executer('UPDATE avecxy_agents SET reprise_id = NULL WHERE reprise_id = ?', [$id]);
            $this->bd->modifier('avecxy_reprises', ['statut' => 'ANNULEE', 'annulee_le' => $this->bd->maintenant()], ['id' => $id]);
            $this->audit->noter($org, $c->utilisateurId(), 'reprise.annuler', 'reprise', $id, $r);
        });
    }
}

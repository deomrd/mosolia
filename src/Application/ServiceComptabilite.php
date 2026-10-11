<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotComptabilite;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Comptabilite generale : plan de comptes de l'organisation, roles des comptes dans les ecritures
 * automatiques, ecritures manuelles, journal, grand livre, balance, bilan et compte de resultat.
 */
final class ServiceComptabilite
{
    public const NATURES = ['ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT'];

    /** Role d'un compte dans les ecritures automatiques, et la nature qu'il doit avoir. */
    public const ROLES = [
        'caisse' => 'ACTIF', 'credits' => 'ACTIF',
        'depots' => 'PASSIF', 'capital' => 'PASSIF', 'report' => 'PASSIF', 'emprunts' => 'PASSIF', 'repartitions' => 'PASSIF',
        'produit_vente' => 'PRODUIT', 'produit_part' => 'PRODUIT', 'produit_frais' => 'PRODUIT', 'produit_dossier' => 'PRODUIT',
        'produit_interet' => 'PRODUIT', 'produit_penalite' => 'PRODUIT', 'produit_divers' => 'PRODUIT',
        'charge_interet_epargne' => 'CHARGE', 'charge_interet_emprunt' => 'CHARGE', 'charge_recolteurs' => 'CHARGE', 'charge_manquants' => 'CHARGE',
    ];

    /** Plan de depart (numerotation proche du SYSCOHADA), modifiable ensuite par l'organisation. [numero, role, nature, texte] */
    public const PLAN = [
        ['101', 'capital', 'PASSIF', 'capital'], ['110', 'report', 'PASSIF', 'report'], ['119', 'repartitions', 'PASSIF', 'repartitions'],
        ['162', 'emprunts', 'PASSIF', 'emprunts'], ['251', 'depots', 'PASSIF', 'depots'], ['271', 'credits', 'ACTIF', 'credits'],
        ['512', null, 'ACTIF', 'banque'], ['571', 'caisse', 'ACTIF', 'caisse'],
        ['605', null, 'CHARGE', 'fournitures'], ['622', null, 'CHARGE', 'loyers'], ['624', null, 'CHARGE', 'transport'],
        ['631', 'charge_recolteurs', 'CHARGE', 'charge_recolteurs'], ['641', null, 'CHARGE', 'salaires'],
        ['658', 'charge_manquants', 'CHARGE', 'charge_manquants'], ['671', 'charge_interet_epargne', 'CHARGE', 'charge_interet_epargne'],
        ['672', 'charge_interet_emprunt', 'CHARGE', 'charge_interet_emprunt'],
        ['701', 'produit_vente', 'PRODUIT', 'produit_vente'], ['702', 'produit_part', 'PRODUIT', 'produit_part'],
        ['703', 'produit_frais', 'PRODUIT', 'produit_frais'], ['704', 'produit_dossier', 'PRODUIT', 'produit_dossier'],
        ['705', 'produit_interet', 'PRODUIT', 'produit_interet'], ['706', 'produit_penalite', 'PRODUIT', 'produit_penalite'],
        ['758', 'produit_divers', 'PRODUIT', 'produit_divers'],
    ];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotComptabilite $compta,
        private readonly Audit $audit,
    ) {
    }

    /** Plan de comptes ; cree le plan de depart a la premiere ouverture. */
    public function plan(int $org, bool $tous = true): array
    {
        $comptes = $this->compta->comptes($org, true);
        if ($comptes === []) {
            $this->bd->transaction(function () use ($org): void {
                foreach (self::PLAN as [$numero, $role, $nature, $texte]) {
                    $this->compta->creerCompte([
                        'organisation_id' => $org, 'numero' => $numero, 'libelle' => mb_strtoupper(t('compta.plan.' . $texte)),
                        'nature' => $nature, 'cle' => $role, 'actif' => 1, 'cree_le' => $this->bd->maintenant(),
                    ]);
                }
            });
            $comptes = $this->compta->comptes($org, true);
        }
        return $tous ? $comptes : array_values(array_filter($comptes, static fn ($c) => (int) $c['actif'] === 1));
    }

    public function enregistrerCompte(Contexte $c, int $id, array $d): int
    {
        $org = $c->organisationId();
        $this->plan($org);
        $numero = strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', (string) ($d['numero'] ?? '')) ?? '');
        $libelle = Texte::nom((string) ($d['libelle'] ?? ''));
        $nature = in_array($d['nature'] ?? '', self::NATURES, true) ? $d['nature'] : '';
        if ($numero === '' || strlen($numero) > 20) {
            throw new ErreurMetier('compta.numero_invalide');
        }
        if ($libelle === '' || $nature === '') {
            throw new ErreurMetier('compta.compte_incomplet');
        }
        if ($this->compta->numeroPris($org, $numero, $id)) {
            throw new ErreurMetier('compta.numero_pris');
        }
        $ligne = ['numero' => $numero, 'libelle' => mb_substr($libelle, 0, 160), 'nature' => $nature, 'actif' => ($d['actif'] ?? '1') === '0' ? 0 : 1];
        if ($id === 0) {
            $id = $this->compta->creerCompte($ligne + ['organisation_id' => $org, 'cree_le' => $this->bd->maintenant()]);
            $this->audit->noter($org, $c->utilisateurId(), 'compta.compte_creer', 'compte', $id, null, $ligne);
            return $id;
        }
        $avant = $this->compta->compte($org, $id) ?? throw new ErreurMetier('compta.compte_introuvable');
        if ($avant['cle'] !== null && ($ligne['nature'] !== self::ROLES[$avant['cle']] || $ligne['actif'] === 0)) {
            // un compte qui porte un role garde sa nature et reste ouvert
            throw new ErreurMetier('compta.compte_role');
        }
        $this->compta->modifierCompte($id, $ligne);
        $this->audit->noter($org, $c->utilisateurId(), 'compta.compte_modifier', 'compte', $id, $avant, $ligne);
        return $id;
    }

    /** @param array<string,int> $roles role => compte */
    public function affecter(Contexte $c, array $roles): void
    {
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $roles): void {
            foreach ($roles as $role => $compteId) {
                if (!isset(self::ROLES[$role])) {
                    continue;
                }
                $compte = $this->compta->compte($org, (int) $compteId) ?? throw new ErreurMetier('compta.compte_introuvable');
                if ($compte['nature'] !== self::ROLES[$role] || (int) $compte['actif'] !== 1) {
                    throw new ErreurMetier('compta.role_nature', ['compte' => $compte['numero'], 'nature' => t('compta.nature.' . strtolower(self::ROLES[$role]))]);
                }
                if ($compte['cle'] !== $role) {
                    $this->compta->affecter($org, $role, (int) $compteId);
                }
            }
            // chaque role doit rester porte par un compte, sinon des ecritures disparaitraient
            $portes = array_filter(array_column($this->compta->comptes($org, true), 'cle'));
            foreach (array_keys(self::ROLES) as $role) {
                if (!in_array($role, $portes, true)) {
                    throw new ErreurMetier('compta.role_manquant', ['role' => t('compta.role.' . $role)]);
                }
            }
            $this->audit->noter($org, $c->utilisateurId(), 'compta.affecter', 'organisation', $org, null, $roles);
        });
    }

    public function supprimerCompte(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $compte = $this->compta->compte($org, $id) ?? throw new ErreurMetier('compta.compte_introuvable');
        if ($compte['cle'] !== null || (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_ecriture_lignes l JOIN avecxy_ecritures e ON e.id = l.ecriture_id WHERE l.compte_id = ? AND e.supprime_le IS NULL', [$id]) > 0) {
            throw new ErreurMetier('compta.compte_utilise');
        }
        $this->compta->modifierCompte($id, ['supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId()]);
        $this->audit->noter($org, $c->utilisateurId(), 'compta.compte_supprimer', 'compte', $id, $compte);
        return (string) $compte['numero'];
    }

    /**
     * Ecriture saisie a la main : au moins deux lignes, chacune au debit ou au credit, total des debits = total des credits.
     * @param array<int,array{compte_id:mixed,debit:string,credit:string}> $lignes
     */
    public function saisir(Contexte $c, string $date, string $piece, string $libelle, int $agenceChoisie, array $lignes): int
    {
        $org = $c->organisationId();
        $date = Dates::passee($date);
        $libelle = trim($libelle);
        if ($libelle === '') {
            throw new ErreurMetier('compta.libelle_obligatoire');
        }
        $agence = ServiceCaisse::agenceDe($c, $agenceChoisie);
        if ($agence > 0 && $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agences WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $agence]) == 0) {
            throw new ErreurMetier('agences.introuvable');
        }
        $propres = [];
        $debit = 0;
        $credit = 0;
        foreach ($lignes as $l) {
            $compteId = (int) ($l['compte_id'] ?? 0);
            $d = trim((string) ($l['debit'] ?? '')) === '' ? 0 : Montant::enCentimes(Montant::lire((string) $l['debit'], $c->decimales(), 'compta.montant_invalide'));
            $k = trim((string) ($l['credit'] ?? '')) === '' ? 0 : Montant::enCentimes(Montant::lire((string) $l['credit'], $c->decimales(), 'compta.montant_invalide'));
            if ($compteId === 0 && $d === 0 && $k === 0) {
                continue;   // ligne vide du formulaire
            }
            $compte = $this->compta->compte($org, $compteId);
            if ($compte === null || (int) $compte['actif'] !== 1) {
                throw new ErreurMetier('compta.compte_introuvable');
            }
            if (($d > 0) === ($k > 0)) {
                throw new ErreurMetier('compta.ligne_debit_ou_credit');
            }
            $debit += $d;
            $credit += $k;
            $propres[] = ['compte_id' => $compteId, 'debit' => Montant::depuisCentimes($d), 'credit' => Montant::depuisCentimes($k)];
        }
        if (count($propres) < 2) {
            throw new ErreurMetier('compta.deux_lignes');
        }
        if ($debit !== $credit) {
            throw new ErreurMetier('compta.desequilibre', ['debit' => argent($debit / 100), 'credit' => argent($credit / 100)]);
        }
        return $this->bd->transaction(function () use ($c, $org, $date, $piece, $libelle, $agence, $propres): int {
            $id = $this->compta->creerEcriture([
                'organisation_id' => $org, 'date_ecriture' => $date, 'piece' => trim($piece) !== '' ? mb_substr(trim($piece), 0, 40) : null,
                'libelle' => mb_substr($libelle, 0, 255), 'agence_id' => $agence > 0 ? $agence : null,
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ], $propres);
            $this->audit->noter($org, $c->utilisateurId(), 'compta.ecriture', 'ecriture', $id, null, ['date' => $date, 'libelle' => $libelle, 'lignes' => $propres]);
            return $id;
        });
    }

    public function supprimerEcriture(Contexte $c, int $id, string $motif): void
    {
        $org = $c->organisationId();
        if ($this->compta->marquerEcriture($org, $id, $this->bd->maintenant(), $c->utilisateurId(), $motif !== '' ? mb_substr($motif, 0, 255) : null) === 0) {
            throw new ErreurMetier('compta.ecriture_introuvable');
        }
        $this->audit->noter($org, $c->utilisateurId(), 'compta.ecriture_supprimer', 'ecriture', $id, null, null, $motif ?: null);
    }

    /** Solde d'un compte dans son sens normal (actif et charge : debit - credit ; passif et produit : credit - debit). */
    public static function sens(string $nature, int $debit, int $credit): int
    {
        return in_array($nature, ['ACTIF', 'CHARGE'], true) ? $debit - $credit : $credit - $debit;
    }

    /**
     * Balance, bilan et compte de resultat d'une periode.
     * @return array{comptes:array,totaux:array,resultat:int,bilan:array,equilibre:bool}
     *   montants en centimes ; resultat = produits - charges de la periode
     */
    public function etats(int $org, string $du, string $au, ?int $agence): array
    {
        $comptes = $this->plan($org);
        $periode = $this->compta->totaux($org, $du, $au, $agence);
        $debutExercice = substr($au, 0, 4) . '-01-01';
        $exercice = $this->compta->totaux($org, $debutExercice, $au, $agence);
        $lignes = [];
        $t = ['avant_debit' => 0, 'avant_credit' => 0, 'debit' => 0, 'credit' => 0, 'fin_debit' => 0, 'fin_credit' => 0];
        $resultat = 0;
        $bilan = ['ACTIF' => [], 'PASSIF' => [], 'actif' => 0, 'passif' => 0, 'resultat_anterieur' => 0, 'resultat_exercice' => 0];
        $charges = [];
        $produits = [];
        foreach ($comptes as $compte) {
            $x = $periode[$compte['id']] ?? null;
            $c = static fn (string $k): int => $x === null ? 0 : Montant::enCentimes($x[$k]);
            $l = [
                'avant_debit' => $c('avant_debit'), 'avant_credit' => $c('avant_credit'), 'debit' => $c('debit'), 'credit' => $c('credit'),
            ];
            $finD = $l['avant_debit'] + $l['debit'];
            $finC = $l['avant_credit'] + $l['credit'];
            $l['fin_debit'] = max(0, $finD - $finC);
            $l['fin_credit'] = max(0, $finC - $finD);
            $l['solde'] = self::sens($compte['nature'], $finD, $finC);
            $l['mouvement'] = self::sens($compte['nature'], $l['debit'], $l['credit']);
            foreach ($t as $k => $_) {
                $t[$k] += $l[$k];
            }
            $ligne = $compte + $l;
            if ($finD !== 0 || $finC !== 0 || (int) $compte['actif'] === 1) {
                $lignes[] = $ligne;
            }
            if ($compte['nature'] === 'CHARGE') {
                $resultat -= $l['mouvement'];
                $charges[] = $ligne;
                // resultat cumule jusqu'a la fin de la periode : exercice en cours et anterieur
                $e = $exercice[$compte['id']] ?? null;
                $dansExercice = $e === null ? 0 : self::sens('CHARGE', Montant::enCentimes($e['debit']), Montant::enCentimes($e['credit']));
                $bilan['resultat_exercice'] -= $dansExercice;
                $bilan['resultat_anterieur'] -= $l['solde'] - $dansExercice;
            } elseif ($compte['nature'] === 'PRODUIT') {
                $resultat += $l['mouvement'];
                $produits[] = $ligne;
                $e = $exercice[$compte['id']] ?? null;
                $dansExercice = $e === null ? 0 : self::sens('PRODUIT', Montant::enCentimes($e['debit']), Montant::enCentimes($e['credit']));
                $bilan['resultat_exercice'] += $dansExercice;
                $bilan['resultat_anterieur'] += $l['solde'] - $dansExercice;
            } elseif ($l['solde'] !== 0) {
                $bilan[$compte['nature']][] = $ligne;
                $bilan[strtolower($compte['nature'])] += $l['solde'];
            }
        }
        $bilan['passif_total'] = $bilan['passif'] + $bilan['resultat_anterieur'] + $bilan['resultat_exercice'];
        return [
            'comptes' => $lignes, 'totaux' => $t, 'resultat' => $resultat, 'charges' => $charges, 'produits' => $produits,
            'bilan' => $bilan, 'equilibre' => $t['debit'] === $t['credit'] && $t['avant_debit'] === $t['avant_credit'] && $bilan['actif'] === $bilan['passif_total'],
        ];
    }

    /** Classeur Excel : balance, compte de resultat, bilan et journal de la periode. */
    public function classeur(int $org, string $du, string $au, ?int $agence, string $titre): string
    {
        $etats = $this->etats($org, $du, $au, $agence);
        $a = static fn (int $c): float => $c / 100;
        $balance = [[$titre], [t('compta.periode', ['du' => jour($du), 'au' => jour($au)])], [],
            [t('compta.numero'), t('compta.libelle'), t('compta.report_debit'), t('compta.report_credit'), t('compta.debit'), t('compta.credit'), t('compta.solde_debit'), t('compta.solde_credit')]];
        foreach ($etats['comptes'] as $l) {
            $balance[] = [$l['numero'], $l['libelle'], $a($l['avant_debit']), $a($l['avant_credit']), $a($l['debit']), $a($l['credit']), $a($l['fin_debit']), $a($l['fin_credit'])];
        }
        $tt = $etats['totaux'];
        $balance[] = ['', t('commun.total'), $a($tt['avant_debit']), $a($tt['avant_credit']), $a($tt['debit']), $a($tt['credit']), $a($tt['fin_debit']), $a($tt['fin_credit'])];

        $resultat = [[t('compta.compte_resultat')], [t('compta.periode', ['du' => jour($du), 'au' => jour($au)])], [], [t('compta.charges'), '', ''],];
        foreach ($etats['charges'] as $l) {
            $resultat[] = [$l['numero'], $l['libelle'], $a($l['mouvement'])];
        }
        $resultat[] = [];
        $resultat[] = [t('compta.produits'), '', ''];
        foreach ($etats['produits'] as $l) {
            $resultat[] = [$l['numero'], $l['libelle'], $a($l['mouvement'])];
        }
        $resultat[] = [];
        $resultat[] = ['', t($etats['resultat'] >= 0 ? 'compta.benefice' : 'compta.perte'), $a($etats['resultat'])];

        $b = $etats['bilan'];
        $bilan = [[t('compta.bilan')], [t('compta.au_date', ['date' => jour($au)])], [], [t('compta.nature.actif'), '', '']];
        foreach ($b['ACTIF'] as $l) {
            $bilan[] = [$l['numero'], $l['libelle'], $a($l['solde'])];
        }
        $bilan[] = ['', t('commun.total'), $a($b['actif'])];
        $bilan[] = [];
        $bilan[] = [t('compta.nature.passif'), '', ''];
        foreach ($b['PASSIF'] as $l) {
            $bilan[] = [$l['numero'], $l['libelle'], $a($l['solde'])];
        }
        $bilan[] = ['', t('compta.resultat_anterieur'), $a($b['resultat_anterieur'])];
        $bilan[] = ['', t('compta.resultat_exercice'), $a($b['resultat_exercice'])];
        $bilan[] = ['', t('commun.total'), $a($b['passif_total'])];

        $journal = [[t('compta.journal')], [t('compta.periode', ['du' => jour($du), 'au' => jour($au)])], [],
            [t('commun.date'), t('compta.operation'), t('compta.reference'), t('compta.numero'), t('compta.libelle'), t('compta.debit'), t('compta.credit')]];
        foreach ($this->compta->lignes($org, $du, $au, $agence, 0, 60000) as $l) {
            $journal[] = [jour((string) $l['jour']), t('compta.type.' . strtolower((string) $l['type'])), (string) $l['ref'], $l['numero'], $l['compte_libelle'],
                $a(Montant::enCentimes($l['debit'])), $a(Montant::enCentimes($l['credit']))];
        }
        return \AvecXY\Infrastructure\Tableur::ecrire([
            ['nom' => t('compta.balance'), 'lignes' => $balance, 'largeurs' => [10, 40, 16, 16, 16, 16, 16, 16]],
            ['nom' => t('compta.compte_resultat'), 'lignes' => $resultat, 'largeurs' => [10, 44, 18]],
            ['nom' => t('compta.bilan'), 'lignes' => $bilan, 'largeurs' => [10, 44, 18]],
            ['nom' => t('compta.journal'), 'lignes' => $journal, 'largeurs' => [12, 26, 18, 10, 40, 16, 16]],
        ]);
    }
}

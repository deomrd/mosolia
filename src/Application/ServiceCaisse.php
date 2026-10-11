<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotParametres;
use AvecXY\Infrastructure\DepotRecolteurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Caisse : regles de partage et de repartition, fonds propres, remises, paiements. */
final class ServiceCaisse
{
    public const SOURCES = ['VENTE_CARNET', 'PART_BENEFICE', 'FRAIS_TENUE', 'INTERET_CREDIT', 'PENALITE_CREDIT', 'FRAIS_DOSSIER'];
    /** Tables dont les lignes passent par la corbeille depuis les ecrans de caisse. */
    public const TABLES = [
        'mouvements' => 'avecxy_mouvements_fonds', 'remises' => 'avecxy_remises_fonds',
        'paiements-recolteurs' => 'avecxy_paiements_agents', 'repartitions' => 'avecxy_repartitions',
        'reglements-manquants' => 'avecxy_manquant_reglements', 'emprunts' => 'avecxy_emprunts', 'remboursements-emprunt' => 'avecxy_emprunt_remboursements',
        'transferts' => 'avecxy_transferts_fonds',
    ];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCaisse $caisse,
        private readonly DepotRecolteurs $recolteurs,
        private readonly RecalculCarnet $recalcul,
        private readonly ServicePartage $partage,
        private readonly Audit $audit,
        private readonly ?DepotParametres $parametres = null,
    ) {
    }

    /**
     * Charge d'interets d'emprunts que la part de l'organisation supporte ce mois, selon ses regles :
     * rien si elle a choisi de ne pas les retirer ; sinon les interets payes dans le mois, plus,
     * si le report est active, ce que les mois precedents n'ont pas pu couvrir.
     */
    public function chargesDuMois(int $org, string $periode): int
    {
        if ($this->parametres !== null && $this->parametres->lire($org, 'emprunts.charge') === 'AUCUNE') {
            return 0;
        }
        $charges = $this->caisse->chargesEmprunts($org, $periode);
        if ($this->parametres === null || $this->parametres->lire($org, 'emprunts.report') !== '1') {
            return $charges;
        }
        $report = 0;
        $premier = $this->caisse->premierMoisDeCharge($org);
        if ($premier !== null) {
            // au plus 36 mois en arriere
            $m = max(new \DateTimeImmutable($premier . '-01'), (new \DateTimeImmutable($periode . '-01'))->modify('-36 months'));
            for ($fin = new \DateTimeImmutable($periode . '-01'); $m < $fin; $m = $m->modify('+1 month')) {
                $mois = $m->format('Y-m');
                $du = $report + $this->caisse->chargesEmprunts($org, $mois);
                $report = max(0, $du - Montant::enCentimes($this->caisse->baseRepartition($org, $mois)));
            }
        }
        return $charges + $report;
    }

    public function reglerCharges(Contexte $c, string $charge, bool $report): void
    {
        $charge = $charge === 'AUCUNE' ? 'AUCUNE' : 'ORGANISATION';
        $this->parametres->ecrire($c->organisationId(), 'emprunts.charge', $charge, $c->utilisateurId());
        $this->parametres->ecrire($c->organisationId(), 'emprunts.report', $report ? '1' : '0', $c->utilisateurId());
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'emprunts.regle', 'organisation', $c->organisationId(), null, ['charge' => $charge, 'report' => $report]);
    }

    /** Regle de l'organisation : les manquants sont-ils deduits du paiement du recolteur ? */
    public function manquantsDeduits(int $org): bool
    {
        return $this->parametres === null || $this->parametres->lire($org, 'manquants.regle') !== 'SUIVRE';
    }

    public function reglerManquants(Contexte $c, string $regle): void
    {
        $regle = $regle === 'SUIVRE' ? 'SUIVRE' : 'DEDUIRE';
        $this->parametres->ecrire($c->organisationId(), 'manquants.regle', $regle, $c->utilisateurId());
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'manquants.regle', 'organisation', $c->organisationId(), null, ['regle' => $regle]);
    }

    /** @param ?int $agence null = toute l'organisation, 0 = siege, N = agence N */
    public function argentEnMain(int $org, ?int $agence = null): int
    {
        return Montant::enCentimes($this->caisse->solde($org, null, $agence));
    }

    /** Agence dont la caisse est concernee : celle de l'utilisateur s'il est rattache, sinon celle choisie (0 = siege). */
    public static function agenceDe(Contexte $c, int $choisie = 0): int
    {
        return $c->agenceRestreinte() ?? max(0, $choisie);
    }

    /** Agence d'un recolteur (0 = siege). */
    private function agenceDuRecolteur(int $agentId): int
    {
        return (int) ($this->bd->valeur('SELECT agence_id FROM avecxy_agents WHERE id = ?', [$agentId]) ?? 0);
    }

    /**
     * Transfert de fonds d'une caisse a une autre (siege = 0). L'argent sort d'une caisse et entre dans l'autre :
     * l'argent en main de l'organisation ne change pas.
     */
    public function transferer(Contexte $c, int $de, int $vers, string $montant, string $date, string $motif): void
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        $de = max(0, $de);
        $vers = max(0, $vers);
        if ($c->agenceRestreinte() !== null && $de !== $c->agenceRestreinte()) {
            throw new ErreurMetier('agences.transfert_sa_caisse');
        }
        if ($de === $vers) {
            throw new ErreurMetier('agences.transfert_meme_caisse');
        }
        foreach ([$de, $vers] as $agence) {
            if ($agence > 0 && $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agences WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $agence]) == 0) {
                throw new ErreurMetier('agences.introuvable');
            }
        }
        $m = Montant::enCentimes(Montant::lire($montant, $c->decimales(), 'montant.invalide'));
        if ($m <= 0) {
            throw new ErreurMetier('montant.invalide');
        }
        $this->bd->transaction(function () use ($c, $org, $de, $vers, $m, $date, $motif): void {
            if ($m > $this->argentEnMain($org, $de)) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($this->caisse->solde($org, null, $de))]);
            }
            $id = $this->caisse->creerTransfert([
                'organisation_id' => $org, 'date_transfert' => $date, 'de_agence_id' => $de > 0 ? $de : null, 'vers_agence_id' => $vers > 0 ? $vers : null,
                'montant' => Montant::depuisCentimes($m), 'motif' => trim($motif) !== '' ? mb_substr(trim($motif), 0, 255) : null,
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'caisse.transfert', 'transfert', $id, null, ['de' => $de, 'vers' => $vers, 'montant' => $m / 100]);
        });
    }

    /**
     * Fixe le partage recolteur / organisation d'une source de benefice a partir d'une date.
     * Les benefices deja enregistres a partir de cette date sont recalcules.
     */
    public function definirPartage(Contexte $c, string $source, string $pctAgent, string $date): void
    {
        if (!in_array($source, self::SOURCES, true)) {
            throw new ErreurMetier('partage.source_invalide');
        }
        $date = Dates::ou($date, '');
        if ($date === '') {
            throw new ErreurMetier('date.invalide');
        }
        $pct = Montant::lire($pctAgent, 2, 'partage.pourcentage_invalide');
        if (Montant::comparer($pct, '100.00') > 0) {
            throw new ErreurMetier('partage.pourcentage_invalide');
        }
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $source, $pct, $date): void {
            $reste = Montant::depuisCentimes(10000 - Montant::enCentimes($pct));
            $this->caisse->creerReglePartage($org, $source, $pct, $reste, $date, $c->utilisateurId());
            $this->partage->oublierCache();
            foreach ($this->caisse->carnetsConcernes($org, $source, $date) as $carnetId) {
                $this->recalcul->recalculer($org, $carnetId);
            }
            foreach ($this->caisse->beneficesDeCredit($org, $source, $date) as $b) {
                $p = $this->partage->repartir($org, $source, (string) $b['date_operation'], Montant::enCentimes($b['montant_total']));
                $this->caisse->modifierBenefice((int) $b['id'], [
                    'regle_partage_id' => $p['regle_id'], 'part_agent' => Montant::depuisCentimes($p['agent']), 'part_organisation' => Montant::depuisCentimes($p['organisation']),
                ]);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'partage.definir', 'regle_partage', null, null, ['source' => $source, 'recolteur' => $pct, 'depuis' => $date]);
        });
    }

    /**
     * Fixe comment la part de l'organisation est repartie chaque mois.
     * @param array<int,array{libelle:string,pourcentage:string}> $lignes
     */
    public function definirRepartition(Contexte $c, string $nom, string $date, array $lignes): void
    {
        $date = Dates::ou($date, '');
        if ($date === '') {
            throw new ErreurMetier('date.invalide');
        }
        $propres = [];
        $total = 0;
        foreach ($lignes as $l) {
            $libelle = trim((string) ($l['libelle'] ?? ''));
            $valeur = trim((string) ($l['pourcentage'] ?? ''));
            if ($libelle === '' && $valeur === '') {
                continue;
            }
            if ($libelle === '') {
                throw new ErreurMetier('repartition.libelle_obligatoire');
            }
            $pct = Montant::lire($valeur, 2, 'partage.pourcentage_invalide');
            $total += Montant::enCentimes($pct);
            $propres[] = ['libelle' => mb_substr($libelle, 0, 120), 'pourcentage' => $pct];
        }
        if (!$propres || $total !== 10000) {
            throw new ErreurMetier('repartition.total_cent', ['total' => Montant::afficher(Montant::depuisCentimes($total), 2)]);
        }
        $org = $c->organisationId();
        if ($this->caisse->regleRepartitionExiste($org, $date)) {
            throw new ErreurMetier('repartition.date_prise');
        }
        $id = $this->caisse->creerRegleRepartition($org, trim($nom) !== '' ? mb_substr(trim($nom), 0, 120) : t('repartition.nom_defaut', ['date' => jour($date)]), $date, $propres, $c->utilisateurId());
        $this->audit->noter($org, $c->utilisateurId(), 'repartition.definir', 'regle_repartition', $id, null, ['depuis' => $date, 'lignes' => $propres]);
    }

    /** Apport ou retrait de fonds propres dans la caisse. */
    public function mouvement(Contexte $c, string $type, string $montant, string $date, string $source, string $commentaire, int $agenceChoisie = 0): void
    {
        $agence = self::agenceDe($c, $agenceChoisie);
        $date = Dates::passee($date);
        $type = $type === 'RETRAIT' ? 'RETRAIT' : 'APPORT';
        $m = Montant::lire($montant, $c->decimales());
        if (Montant::estNul($m)) {
            throw new ErreurMetier('montant.invalide');
        }
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $type, $m, $date, $source, $commentaire, $agence): void {
            if ($type === 'RETRAIT' && Montant::enCentimes($m) > $this->argentEnMain($org, $agence)) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($this->caisse->solde($org, null, $agence))]);
            }
            $id = $this->caisse->creerMouvement([
                'organisation_id' => $org, 'date_mouvement' => $date, 'type' => $type, 'montant' => $m, 'agence_id' => $agence > 0 ? $agence : null,
                'source' => trim($source) !== '' ? mb_substr(trim($source), 0, 160) : null, 'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null,
                'statut' => 'VALIDE', 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'caisse.mouvement', 'mouvement', $id, null, ['type' => $type, 'montant' => $m, 'date' => $date]);
        });
    }

    /** Remise de fonds d'un recolteur : ce qu'il devait remettre et ce qu'il a remis. */
    public function remise(Contexte $c, int $agentId, string $date, string $attendu, string $verse, string $commentaire): void
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        if ($this->recolteurs->parId($org, $agentId) === null) {
            throw new ErreurMetier('recolteurs.introuvable');
        }
        $a = Montant::enCentimes(Montant::lire($attendu, $c->decimales()));
        $v = Montant::enCentimes(Montant::lire($verse === '' ? '0' : $verse, $c->decimales()));
        if ($a <= 0) {
            throw new ErreurMetier('montant.invalide');
        }
        $id = $this->caisse->creerRemise([
            'organisation_id' => $org, 'agent_id' => $agentId, 'date_remise' => $date, 'periode' => substr($date, 0, 7), 'origine' => 'MANUELLE',
            'montant_attendu' => Montant::depuisCentimes($a), 'montant_verse' => Montant::depuisCentimes($v),
            'manquant' => Montant::depuisCentimes(max(0, $a - $v)), 'excedent' => Montant::depuisCentimes(max(0, $v - $a)),
            'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($org, $c->utilisateurId(), 'caisse.remise', 'remise', $id, null, ['recolteur' => $agentId, 'attendu' => $a / 100, 'verse' => $v / 100]);
    }

    /** Le recolteur rembourse tout ou partie de ses manquants : l'argent rentre en caisse. */
    public function reglerManquant(Contexte $c, int $agentId, string $montant, string $date, string $commentaire): void
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        $m = Montant::enCentimes(Montant::lire($montant, $c->decimales()));
        $this->bd->transaction(function () use ($c, $org, $agentId, $m, $date, $commentaire): void {
            $du = (int) ($this->caisse->manquantsDus($org, $agentId)[0]['du'] ?? 0);
            if ($m <= 0) {
                throw new ErreurMetier('montant.invalide');
            }
            if ($m > $du) {
                throw new ErreurMetier('manquants.au_dessus_du_du', ['du' => argent($du / 100)]);
            }
            $id = $this->caisse->creerReglement([
                'organisation_id' => $org, 'agent_id' => $agentId, 'date_paiement' => $date, 'montant' => Montant::depuisCentimes($m),
                'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'manquants.regler', 'reglement_manquant', $id, null, ['recolteur' => $agentId, 'montant' => $m / 100]);
        });
    }

    /** L'organisation emprunte de l'argent, avec ou sans interet. L'interet est calcule une fois sur le montant emprunte. */
    public function emprunter(Contexte $c, string $preteur, string $montant, string $taux, string $date, string $echeance, string $commentaire): void
    {
        $date = Dates::passee($date);
        $preteur = trim($preteur);
        if ($preteur === '') {
            throw new ErreurMetier('emprunts.preteur_obligatoire');
        }
        $m = Montant::enCentimes(Montant::lire($montant, $c->decimales()));
        if ($m <= 0) {
            throw new ErreurMetier('montant.invalide');
        }
        $t = Montant::lire($taux === '' ? '0' : $taux, 2, 'credits.taux_invalide');
        if (Montant::comparer($t, '999.99') > 0) {
            throw new ErreurMetier('credits.taux_invalide');
        }
        $echeance = Dates::ou($echeance, '');
        if ($echeance !== '' && $echeance < $date) {
            throw new ErreurMetier('date.invalide');
        }
        $interet = Montant::arrondirDevise(Montant::pourcentage($m, $t), $c->decimales());
        $org = $c->organisationId();
        $id = $this->caisse->creerEmprunt([
            'organisation_id' => $org, 'date_emprunt' => $date, 'preteur' => mb_substr($preteur, 0, 160), 'montant' => Montant::depuisCentimes($m),
            'taux_interet' => $t, 'montant_interet' => Montant::depuisCentimes($interet), 'total_du' => Montant::depuisCentimes($m + $interet),
            'date_echeance' => $echeance !== '' ? $echeance : null, 'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null,
            'agence_id' => $c->agenceRestreinte(), 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($org, $c->utilisateurId(), 'emprunt.creer', 'emprunt', $id, null, ['preteur' => $preteur, 'montant' => $m / 100, 'taux' => $t]);
    }

    /** Rembourse un emprunt avec l'argent en main. */
    public function rembourserEmprunt(Contexte $c, int $empruntId, string $montant, string $date, string $commentaire): void
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        $m = Montant::enCentimes(Montant::lire($montant, $c->decimales()));
        $this->bd->transaction(function () use ($c, $org, $empruntId, $m, $date, $commentaire): void {
            $e = $this->caisse->emprunt($org, $empruntId) ?? throw new ErreurMetier('caisse.ligne_introuvable');
            $reste = Montant::enCentimes($e['total_du']) - Montant::enCentimes($e['rembourse']);
            if ($m <= 0) {
                throw new ErreurMetier('montant.invalide');
            }
            if ($m > $reste) {
                throw new ErreurMetier('credits.au_dessus_du_reste', ['reste' => argent($reste / 100)]);
            }
            $agence = (int) ($e['agence_id'] ?? 0);
            if ($m > $this->argentEnMain($org, $agence)) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($this->caisse->solde($org, null, $agence))]);
            }
            $id = $this->caisse->creerRemboursementEmprunt([
                'organisation_id' => $org, 'emprunt_id' => $empruntId, 'date_paiement' => $date, 'montant' => Montant::depuisCentimes($m),
                'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'emprunt.rembourser', 'emprunt', $id, null, ['emprunt' => $empruntId, 'montant' => $m / 100]);
        });
    }

    /** Paie a un recolteur sa part du mois, manquants deduits. Le montant est calcule ici, jamais recu du formulaire. */
    public function payerRecolteur(Contexte $c, int $agentId, string $periode, string $date): int
    {
        $date = Dates::passee($date);
        $periode = self::periode($periode);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $agentId, $periode, $date): int {
            $ligne = null;
            foreach ($this->caisse->syntheseRecolteurs($org, $periode) as $l) {
                if ((int) $l['id'] === $agentId) {
                    $ligne = $l;
                }
            }
            if ($ligne === null) {
                throw new ErreurMetier('recolteurs.introuvable');
            }
            if ($ligne['paiement_id'] !== null) {
                throw new ErreurMetier('paiements.deja_paye');
            }
            $part = Montant::enCentimes($ligne['part_agent']);
            // ce que le recolteur doit encore, tous mois confondus : un manquant deja regle ou deja deduit ne l'est pas deux fois
            $manquants = $this->manquantsDeduits($org) ? min($part, (int) ($this->caisse->manquantsDus($org, $agentId)[0]['du'] ?? 0)) : 0;
            $net = $part - $manquants;
            if ($net <= 0) {
                throw new ErreurMetier('paiements.rien_a_payer');
            }
            $agence = $this->agenceDuRecolteur($agentId);
            if ($net > $this->argentEnMain($org, $agence)) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($this->caisse->solde($org, null, $agence))]);
            }
            $id = $this->caisse->creerPaiementRecolteur([
                'organisation_id' => $org, 'agent_id' => $agentId, 'periode' => $periode, 'date_paiement' => $date,
                'part_agent' => Montant::depuisCentimes($part), 'manquants_deduits' => Montant::depuisCentimes($manquants),
                'montant_paye' => Montant::depuisCentimes($net), 'paye_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'paiement.recolteur', 'paiement_recolteur', $id, null, ['recolteur' => $ligne['code'], 'periode' => $periode, 'montant' => $net / 100]);
            return $net;
        });
    }

    /**
     * Ce que donnerait la repartition du mois selon la regle en vigueur.
     * @return array{base:int,regle:?array,lignes:array<int,array{libelle:string,pourcentage:mixed,montant:int}>}
     */
    public function projetRepartition(int $org, string $periode, int $decimales = 2): array
    {
        $periode = self::periode($periode);
        // la part de l'organisation supporte ses charges : les interets payes sur ses emprunts.
        // La part des recolteurs n'est jamais touchee.
        $part = Montant::enCentimes($this->caisse->baseRepartition($org, $periode));
        $charges = $this->chargesDuMois($org, $periode);
        $base = max(0, $part - $charges);
        $regle = $this->caisse->regleRepartition($org, date('Y-m-t', strtotime($periode . '-01')));
        $lignes = [];
        if ($regle !== null) {
            $reste = $base;
            $dernier = count($regle['lignes']) - 1;
            foreach ($regle['lignes'] as $i => $l) {
                // la derniere ligne prend le reste : la somme tombe toujours juste
                $montant = $i === $dernier ? $reste : min($reste, Montant::arrondirDevise(Montant::pourcentage($base, (string) $l['pourcentage']), $decimales));
                $reste -= $montant;
                $lignes[] = ['libelle' => $l['libelle'], 'pourcentage' => $l['pourcentage'], 'montant' => $montant];
            }
        }
        return ['base' => $base, 'part' => $part, 'charges' => $charges, 'regle' => $regle, 'lignes' => $lignes];
    }

    public function payerRepartition(Contexte $c, string $periode, string $date): int
    {
        $date = Dates::passee($date);
        $periode = self::periode($periode);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $periode, $date): int {
            if ($this->caisse->repartition($org, $periode) !== null) {
                throw new ErreurMetier('repartition.deja_payee');
            }
            $projet = $this->projetRepartition($org, $periode, $c->decimales());
            if ($projet['regle'] === null) {
                throw new ErreurMetier('repartition.aucune_regle');
            }
            if ($projet['base'] <= 0) {
                throw new ErreurMetier('repartition.rien');
            }
            if ($projet['base'] > $this->argentEnMain($org)) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($this->caisse->solde($org))]);
            }
            $id = $this->caisse->creerRepartition([
                'organisation_id' => $org, 'periode' => $periode, 'regle_id' => $projet['regle']['id'], 'date_paiement' => $date,
                'montant_base' => Montant::depuisCentimes($projet['base']), 'paye_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ], array_map(static fn ($l) => ['libelle' => $l['libelle'], 'pourcentage' => $l['pourcentage'], 'montant' => Montant::depuisCentimes($l['montant'])], $projet['lignes']));
            $this->audit->noter($org, $c->utilisateurId(), 'repartition.payer', 'repartition', $id, null, ['periode' => $periode, 'base' => $projet['base'] / 100]);
            return $projet['base'];
        });
    }

    public function supprimer(Contexte $c, string $type, int $id, string $motif): void
    {
        $table = self::TABLES[$type] ?? throw new ErreurMetier('erreur.404.titre');
        if ($this->caisse->marquer($table, $c->organisationId(), $id, $this->bd->maintenant(), $c->utilisateurId(), $motif !== '' ? mb_substr($motif, 0, 255) : null) === 0) {
            throw new ErreurMetier('caisse.ligne_introuvable');
        }
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'caisse.supprimer', $type, $id, null, null, $motif ?: null);
    }

    public function restaurer(Contexte $c, string $type, int $id): string
    {
        $table = self::TABLES[$type] ?? throw new ErreurMetier('erreur.404.titre');
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $table, $type, $id): string {
            $ligne = $this->bd->un("SELECT * FROM $table WHERE organisation_id = ? AND id = ? AND supprime_le IS NOT NULL", [$org, $id]) ?? throw new ErreurMetier('caisse.ligne_introuvable');
            // un seul paiement par recolteur et par mois, une seule repartition par mois
            if ($type === 'paiements-recolteurs' && $this->bd->valeur('SELECT COUNT(*) FROM avecxy_paiements_agents WHERE organisation_id = ? AND agent_id = ? AND periode = ? AND supprime_le IS NULL', [$org, $ligne['agent_id'], $ligne['periode']])
                || $type === 'repartitions' && $this->caisse->repartition($org, (string) $ligne['periode']) !== null) {
                throw new ErreurMetier('paiements.deja_paye');
            }
            $this->caisse->marquer($table, $org, $id, null, null, null);
            $this->audit->noter($org, $c->utilisateurId(), 'caisse.restaurer', $type, $id);
            return t('corbeille.section.' . $type) . ' #' . $id;
        });
    }

    /** Controle un mois saisi (AAAA-MM). */
    public static function periode(string $valeur): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valeur) ? $valeur : date('Y-m');
    }
}

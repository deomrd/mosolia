<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ControleCredit;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\Echeancier;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotCredits;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Noyau\BaseDeDonnees;

/** Credits : regles fixees par l'organisation, demandes, octroi, remboursements, penalites. */
final class ServiceCredits
{
    private const PAS = ['JOUR' => 1, 'SEMAINE' => 7, 'MOIS' => 30];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCredits $credits,
        private readonly DepotCaisse $caisse,
        private readonly DepotOperations $operations,
        private readonly ServicePartage $partage,
        private readonly Audit $audit,
        private readonly ?ServiceRetraits $retraits = null,
        private readonly ?DepotCarnets $carnets = null,
    ) {
    }

    // ------------------------------------------------------------------
    // Regles (produits de credit)
    // ------------------------------------------------------------------

    public function enregistrerProduit(Contexte $c, int $id, array $d): void
    {
        $dec = $c->decimales();
        $nom = Texte::nom((string) ($d['nom'] ?? ''));
        if ($nom === '') {
            throw new ErreurMetier('credits.nom_obligatoire');
        }
        $taux = static function (string $v): string {
            $t = Montant::lire($v === '' ? '0' : $v, 2, 'credits.taux_invalide');
            if (Montant::comparer($t, '999.99') > 0) {
                throw new ErreurMetier('credits.taux_invalide');
            }
            return $t;
        };
        $entier = static function (mixed $v, int $min, int $max, string $cle): int {
            $v = trim((string) $v);
            if ($v === '') {
                $v = (string) $min;
            }
            if (!ctype_digit($v) || (int) $v < $min || (int) $v > $max) {
                throw new ErreurMetier($cle);
            }
            return (int) $v;
        };
        $montantOuNull = static fn (string $v): ?string => $v === '' ? null : Montant::lire($v, $dec);
        $ligne = [
            'nom' => $nom,
            'taux_interet' => $taux((string) ($d['taux_interet'] ?? '')),
            'duree_jours' => $entier($d['duree_jours'] ?? '', 1, 3660, 'credits.duree_invalide'),
            'taux_penalite' => $taux((string) ($d['taux_penalite'] ?? '')),
            'base_penalite' => ($d['base_penalite'] ?? '') === 'CAPITAL' ? 'CAPITAL' : 'RESTANT',
            'frequence_penalite' => in_array($d['frequence_penalite'] ?? '', ['JOUR', 'SEMAINE', 'MOIS'], true) ? $d['frequence_penalite'] : 'MANUELLE',
            'jours_tolerance' => $entier($d['jours_tolerance'] ?? '', 0, 365, 'credits.nombre_invalide'),
            'montant_minimum' => $montantOuNull((string) ($d['montant_minimum'] ?? '')),
            'montant_maximum' => $montantOuNull((string) ($d['montant_maximum'] ?? '')),
            'multiple_epargne_max' => ($d['multiple_epargne_max'] ?? '') === '' ? null : $taux((string) $d['multiple_epargne_max']),
            'plafond_encours_pct_caisse' => ($d['plafond_encours_pct_caisse'] ?? '') === '' ? null : $taux((string) $d['plafond_encours_pct_caisse']),
            'credits_simultanes_max' => $entier($d['credits_simultanes_max'] ?? '', 1, 50, 'credits.nombre_invalide'),
            'carnets_fermes_minimum' => $entier($d['carnets_fermes_minimum'] ?? '', 0, 1000, 'credits.nombre_invalide'),
            'anciennete_jours_minimum' => $entier($d['anciennete_jours_minimum'] ?? '', 0, 36500, 'credits.nombre_invalide'),
            'mode_remboursement' => ($d['mode_remboursement'] ?? '') === 'CALENDRIER' ? 'CALENDRIER' : 'LIBRE',
            'echeances_nombre' => max(1, $entier($d['echeances_nombre'] ?? '', 0, 366, 'credits.nombre_invalide')),
            'echeances_intervalle' => max(1, $entier($d['echeances_intervalle'] ?? '', 0, 366, 'credits.nombre_invalide')),
            'mode_calcul' => in_array($d['mode_calcul'] ?? '', Echeancier::MODES, true) ? $d['mode_calcul'] : 'FIXE',
            'echeances_unite' => in_array($d['echeances_unite'] ?? '', Echeancier::UNITES, true) ? $d['echeances_unite'] : 'JOUR',
            'frais_dossier_type' => in_array($d['frais_dossier_type'] ?? '', ['FIXE', 'POURCENTAGE'], true) ? $d['frais_dossier_type'] : 'AUCUN',
            'frais_dossier_valeur' => '0.00',
            'frais_dossier_mode' => ($d['frais_dossier_mode'] ?? '') === 'AJOUTE' ? 'AJOUTE' : 'RETENU',
            'garantie_pct' => ($d['garantie_pct'] ?? '') === '' ? null : $taux((string) $d['garantie_pct']),
        ];
        if ($ligne['mode_calcul'] !== 'FIXE' && $ligne['mode_remboursement'] !== 'CALENDRIER') {
            // un taux degressif se calcule echeance par echeance : il lui faut un calendrier
            throw new ErreurMetier('credits.degressif_calendrier');
        }
        if ($ligne['frais_dossier_type'] !== 'AUCUN') {
            $ligne['frais_dossier_valeur'] = $ligne['frais_dossier_type'] === 'FIXE'
                ? Montant::lire((string) ($d['frais_dossier_valeur'] ?? ''), $dec, 'credits.frais_dossier_invalides')
                : $taux((string) ($d['frais_dossier_valeur'] ?? ''));
            if (Montant::estNul($ligne['frais_dossier_valeur']) || ($ligne['frais_dossier_type'] === 'POURCENTAGE' && Montant::comparer($ligne['frais_dossier_valeur'], '100.00') > 0)) {
                throw new ErreurMetier('credits.frais_dossier_invalides');
            }
        }
        if ($ligne['garantie_pct'] !== null && (Montant::estNul($ligne['garantie_pct']) || Montant::comparer($ligne['garantie_pct'], '100.00') > 0)) {
            $ligne['garantie_pct'] = Montant::estNul($ligne['garantie_pct']) ? null : throw new ErreurMetier('credits.garantie_invalide');
        }
        foreach (['reconduction', 'remboursement_epargne', 'carnet_actif_requis', 'sans_retard_anterieur', 'accord_agent_requis', 'validation_manuelle_requise', 'derogation_autorisee', 'actif'] as $case) {
            $ligne[$case] = ($d[$case] ?? '') === '1' ? 1 : 0;
        }
        if ($ligne['montant_minimum'] !== null && $ligne['montant_maximum'] !== null && Montant::comparer($ligne['montant_minimum'], $ligne['montant_maximum']) > 0) {
            throw new ErreurMetier('credits.limites_inversees');
        }
        $org = $c->organisationId();
        if ($id > 0 && $this->credits->produit($org, $id) === null) {
            throw new ErreurMetier('credits.produit_introuvable');
        }
        $id = $this->credits->enregistrerProduit($org, $id, $ligne);
        $this->audit->noter($org, $c->utilisateurId(), 'credit.regles', 'credit_produit', $id, null, $ligne);
    }

    // ------------------------------------------------------------------
    // Controle
    // ------------------------------------------------------------------

    /**
     * Controle une demande : regle par regle, sans rien enregistrer.
     * @return array{lignes:array,conforme:bool,faits:array}
     */
    public function controler(int $org, array $client, array $produit, int $montant): array
    {
        $f = $this->credits->faits($org, (int) $client['id']);
        $faits = [
            'epargne' => Montant::enCentimes($f['epargne']), 'carnets_fermes' => (int) $f['carnets_fermes'], 'carnet_actif' => (int) $f['carnets_ouverts'] > 0,
            'anciennete_jours' => max(0, Dates::joursEntre(substr((string) $f['cree_le'], 0, 10), date('Y-m-d'))),
            'retard_anterieur' => (int) $f['retards'] > 0, 'credits_en_cours' => (int) $f['credits_en_cours'],
            // l'argent prete sort de la caisse de l'agence du client (le siege quand il n'y a pas d'agence)
            'encours' => Montant::enCentimes($f['encours']),
            'caisse' => Montant::enCentimes($this->caisse->solde($org, null, (int) ($this->bd->valeur('SELECT agence_id FROM avecxy_agents WHERE id = ?', [$client['agent_id']]) ?? 0))),
            'garanties' => Montant::enCentimes($f['garanties'] ?? 0),
        ];
        $lignes = ControleCredit::controler($produit, $faits, $montant);
        return ['lignes' => $lignes, 'conforme' => ControleCredit::conforme($lignes), 'faits' => $faits];
    }

    private function client(Contexte $c, string $code): array
    {
        $client = $this->credits->clientParCode($c->organisationId(), strtoupper(trim($code)), $c->recolteurRestreint());
        if ($client === null) {
            throw new ErreurMetier('credits.client_introuvable');
        }
        if ($client['statut'] !== 'ACTIF') {
            throw new ErreurMetier('clients.inactif');
        }
        return $client;
    }

    private function produit(int $org, int $id): array
    {
        $p = $this->credits->produit($org, $id);
        if ($p === null || (int) $p['actif'] !== 1) {
            throw new ErreurMetier('credits.produit_introuvable');
        }
        return $p;
    }

    private function montant(Contexte $c, string $saisie): int
    {
        $m = Montant::enCentimes(Montant::lire($saisie, $c->decimales()));
        if ($m <= 0) {
            throw new ErreurMetier('montant.invalide');
        }
        return $m;
    }

    /** Une derogation exige le droit, l'accord des regles et un motif ecrit. */
    private function exigerDerogation(Contexte $c, array $produit, string $motif): void
    {
        if ((int) $produit['derogation_autorisee'] !== 1) {
            throw new ErreurMetier('credits.derogation_interdite');
        }
        if (!$c->peut('credits.deroger')) {
            throw new ErreurMetier('credits.derogation_sans_droit');
        }
        if (mb_strlen(trim($motif)) < 5) {
            throw new ErreurMetier('credits.motif_derogation_requis');
        }
    }

    // ------------------------------------------------------------------
    // Demandes de credit et octroi
    // ------------------------------------------------------------------

    /** Le minimum et le maximum du credit ne se depassent jamais, meme par derogation. */
    private function limitesStrictes(array $produit, int $montant): void
    {
        if ($produit['montant_minimum'] !== null && $montant < Montant::enCentimes($produit['montant_minimum'])) {
            throw new ErreurMetier('credits.sous_minimum', ['min' => argent($produit['montant_minimum'])]);
        }
        if ($produit['montant_maximum'] !== null && $montant > Montant::enCentimes($produit['montant_maximum'])) {
            throw new ErreurMetier('credits.au_dessus_maximum', ['max' => argent($produit['montant_maximum'])]);
        }
    }

    /** Tant qu'une dette est en cours, pas de nouveau credit, sauf si les regles en autorisent plusieurs a la fois. */
    private function detteEnCours(array $produit, array $faits): void
    {
        if ($faits['credits_en_cours'] >= (int) $produit['credits_simultanes_max']) {
            throw new ErreurMetier('credits.dette_en_cours');
        }
    }

    /** Le recolteur (ou toute personne autorisee) envoie une demande pour un client. */
    public function proposer(Contexte $c, string $codeClient, int $produitId, string $montant, string $commentaire): int
    {
        $org = $c->organisationId();
        $client = $this->client($c, $codeClient);
        $produit = $this->produit($org, $produitId);
        $m = $this->montant($c, $montant);
        $this->limitesStrictes($produit, $m);
        if ($this->credits->demandeEnAttente($org, (int) $client['id'])) {
            throw new ErreurMetier('eligibilite.deja_en_cours');
        }
        $controle = $this->controler($org, $client, $produit, $m);
        $this->detteEnCours($produit, $controle['faits']);
        $id = $this->credits->creerDemande([
            'organisation_id' => $org, 'client_id' => $client['id'], 'agent_id' => $client['agent_id'], 'credit_produit_id' => $produitId,
            'statut' => 'PROPOSE', 'montant_demande' => Montant::depuisCentimes($m), 'controle' => json_encode($controle['lignes']),
            'propose_par' => $c->utilisateurId(), 'propose_le' => $this->bd->maintenant(),
            'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($org, $c->utilisateurId(), 'eligibilite.proposer', 'eligibilite', $id, null, ['client' => $client['code'], 'montant' => $m / 100, 'conforme' => $controle['conforme']]);
        return $id;
    }

    /**
     * Ce que le client voit de son eligibilite pour une regle de credit : les regles qui le concernent
     * (jamais l'etat de la caisse de l'organisation), et le montant qu'il peut demander.
     * @return array{eligible:bool,lignes:array,minimum:int,maximum:int,raison:?string}
     */
    public function eligibiliteDuClient(int $org, array $client, array $produit): array
    {
        $min = $produit['montant_minimum'] !== null ? Montant::enCentimes($produit['montant_minimum']) : 1;
        $r = $this->controler($org, $client, $produit, max(1, $min));
        $max = $produit['montant_maximum'] !== null ? Montant::enCentimes($produit['montant_maximum']) : PHP_INT_MAX;
        if ($produit['multiple_epargne_max'] !== null) {
            $max = min($max, Montant::pourcentage($r['faits']['epargne'], (float) $produit['multiple_epargne_max'] * 100));
        }
        $lignes = array_values(array_filter($r['lignes'], static fn ($l) => !in_array($l['regle'], ['plafond_encours', 'montant_minimum', 'montant_maximum'], true)));
        $raison = null;
        if ($this->credits->demandeEnAttente($org, (int) $client['id'])) {
            $raison = 'portail.demande_deja_en_cours';
        } elseif (array_filter($lignes, static fn ($l) => !$l['ok']) || $max < $min) {
            $raison = 'portail.non_eligible';
        }
        return ['eligible' => $raison === null, 'lignes' => $lignes, 'minimum' => $min, 'maximum' => $max === PHP_INT_MAX ? 0 : $max, 'raison' => $raison];
    }

    /** Demande de credit faite par le client depuis son espace : elle arrive dans les demandes de l'organisation. */
    public function demanderParClient(int $org, int $clientId, int $produitId, string $montantSaisi, int $decimales): array
    {
        $client = $this->bd->un("SELECT * FROM avecxy_clients WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL AND statut = 'ACTIF'", [$org, $clientId]) ?? throw new ErreurMetier('credits.client_introuvable');
        $produit = $this->produit($org, $produitId);
        if ((int) $produit['actif'] !== 1) {
            throw new ErreurMetier('credits.produit_introuvable');
        }
        $m = Montant::enCentimes(Montant::lire($montantSaisi, $decimales));
        if ($m <= 0) {
            throw new ErreurMetier('montant.invalide');
        }
        $this->limitesStrictes($produit, $m);
        if ($this->credits->demandeEnAttente($org, $clientId)) {
            throw new ErreurMetier('portail.demande_deja_en_cours');
        }
        $controle = $this->controler($org, $client, $produit, $m);
        $this->detteEnCours($produit, $controle['faits']);
        // depuis son espace, le client ne peut demander que s'il respecte les regles qui dependent de lui
        if (array_filter($controle['lignes'], static fn ($l) => !$l['ok'] && $l['regle'] !== 'plafond_encours')) {
            throw new ErreurMetier('portail.non_eligible');
        }
        $id = $this->credits->creerDemande([
            'organisation_id' => $org, 'client_id' => $clientId, 'agent_id' => $client['agent_id'], 'credit_produit_id' => $produitId,
            'statut' => 'PROPOSE', 'montant_demande' => Montant::depuisCentimes($m), 'controle' => json_encode($controle['lignes']),
            'propose_par' => null, 'propose_le' => $this->bd->maintenant(), 'origine' => 'CLIENT',
            'commentaire' => t('portail.demande_commentaire'), 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($org, null, 'eligibilite.demande_client', 'eligibilite', $id, null, ['client' => $client['code'], 'montant' => $m / 100]);
        return ['id' => $id, 'client' => $client, 'produit' => $produit, 'montant' => $m];
    }

    /** Refuser une demande (meme eligible) : elle part a la corbeille. */
    public function refuser(Contexte $c, int $id, string $motif): void
    {
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $id, $motif): void {
            $d = $this->credits->demande($org, $id);
            if ($d === null || $d['statut'] !== 'PROPOSE') {
                throw new ErreurMetier('eligibilite.introuvable');
            }
            $this->credits->modifierDemande($id, [
                'statut' => 'REJETE', 'decide_par' => $c->utilisateurId(), 'decide_le' => $this->bd->maintenant(),
                'supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => trim($motif) !== '' ? mb_substr(trim($motif), 0, 255) : t('eligibilite.refusee'),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'eligibilite.refuser', 'eligibilite', $id, null, null, trim($motif) ?: null);
        });
    }

    public function restaurerDemande(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $d = $this->bd->un('SELECT * FROM avecxy_credit_eligibilites WHERE organisation_id = ? AND id = ? AND supprime_le IS NOT NULL', [$org, $id]) ?? throw new ErreurMetier('eligibilite.introuvable');
        $this->credits->modifierDemande($id, ['statut' => 'PROPOSE', 'decide_par' => null, 'decide_le' => null, 'supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
        $this->audit->noter($org, $c->utilisateurId(), 'eligibilite.restaurer', 'eligibilite', $id);
        return t('corbeille.section.demandes-credit') . ' #' . $id;
    }

    /**
     * Accorde un credit : c'est une dette des cet instant, pour le montant accorde
     * (qui peut etre inferieur au montant demande : accord partiel).
     * @param int $demandeId demande a laquelle on repond (0 = credit accorde directement par une personne autorisee)
     * @return array{id:int,numero:int}
     */
    public function accorder(Contexte $c, string $codeClient, int $produitId, string $montant, string $date, string $motifDerogation, string $commentaire, string $taux = '', string $duree = '', int $demandeId = 0): array
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $codeClient, $produitId, $montant, $date, $motifDerogation, $commentaire, $taux, $duree, $demandeId): array {
            $demande = null;
            if ($demandeId > 0) {
                $demande = $this->credits->demande($org, $demandeId);
                if ($demande === null || $demande['statut'] !== 'PROPOSE') {
                    throw new ErreurMetier('eligibilite.introuvable');
                }
                $client = $this->bd->un('SELECT * FROM avecxy_clients WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $demande['client_id']]) ?? throw new ErreurMetier('credits.client_introuvable');
                if ($client['statut'] !== 'ACTIF') {
                    throw new ErreurMetier('clients.inactif');
                }
                $produitId = (int) $demande['credit_produit_id'];
            } else {
                $client = $this->client($c, $codeClient);
            }
            $produit = $this->produit($org, $produitId);
            if ($demande === null && (int) $produit['accord_agent_requis'] === 1) {
                throw new ErreurMetier('credits.demande_requise');
            }
            // le taux et la duree sont fixes a l'octroi ; les regles n'en donnent que la valeur proposee
            [$tauxInteret, $dureeJours] = self::conditions($produit, $taux, $duree);
            $m = $this->montant($c, $montant);
            $this->limitesStrictes($produit, $m);
            $controle = $this->controler($org, $client, $produit, $m);
            $this->detteEnCours($produit, $controle['faits']);
            $derogation = false;
            if (!$controle['conforme']) {
                // les autres regles se depassent seulement par derogation motivee
                if (trim($motifDerogation) === '') {
                    throw new ErreurMetier('credits.regles_non_respectees');
                }
                $this->exigerDerogation($c, $produit, $motifDerogation);
                $derogation = true;
            }

            $plan = self::plan($produit, $m, $tauxInteret, $date, $c->decimales());
            $interet = $plan['interet'];
            // ce qui sort de la caisse : le pret, moins des frais de dossier retenus
            if ($plan['verse'] > $controle['faits']['caisse']) {
                throw new ErreurMetier('caisse.insuffisante', ['solde' => argent($controle['faits']['caisse'] / 100)]);
            }
            $numero = $this->credits->prochainNumero($org);
            $id = $this->credits->creerCredit([
                'organisation_id' => $org, 'client_id' => $client['id'], 'agent_id' => $client['agent_id'], 'credit_produit_id' => $produitId,
                'eligibilite_id' => $demande['id'] ?? null, 'numero' => $numero, 'date_credit' => $date,
                'date_echeance' => $plan['echeances'] ? end($plan['echeances'])['date_prevue'] : Dates::ajouterJours($date, $dureeJours), 'duree_jours' => $dureeJours, 'reconduction' => (int) $produit['reconduction'],
                'montant' => Montant::depuisCentimes($m), 'taux_interet' => $tauxInteret, 'montant_interet' => Montant::depuisCentimes($interet),
                'taux_penalite' => $produit['taux_penalite'], 'total_penalites' => '0.00', 'total_du' => Montant::depuisCentimes($plan['total']),
                'total_rembourse' => '0.00', 'restant' => Montant::depuisCentimes($plan['total']), 'statut' => 'EN_COURS',
                'mode_calcul' => $plan['mode'], 'frais_dossier' => Montant::depuisCentimes($plan['frais']), 'frais_dossier_mode' => $plan['frais_mode'],
                'garantie' => Montant::depuisCentimes($plan['garantie']),
                'derogation' => $derogation ? 1 : 0, 'motif_derogation' => $derogation ? trim($motifDerogation) : null,
                'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            if ($demande !== null) {
                $this->credits->modifierDemande((int) $demande['id'], [
                    'statut' => 'VALIDE', 'plafond_accorde' => Montant::depuisCentimes($m), 'credit_id' => $id, 'controle' => json_encode($controle['lignes']),
                    'derogation' => $derogation ? 1 : 0, 'motif_derogation' => $derogation ? trim($motifDerogation) : null,
                    'decide_par' => $c->utilisateurId(), 'decide_le' => $this->bd->maintenant(),
                ]);
            }
            foreach ($plan['echeances'] as $e) {
                $this->credits->creerEcheance([
                    'organisation_id' => $org, 'credit_id' => $id, 'numero' => $e['numero'], 'date_prevue' => $e['date_prevue'],
                    'montant' => Montant::depuisCentimes($e['montant']), 'capital' => Montant::depuisCentimes($e['capital']),
                    'interet' => Montant::depuisCentimes($e['interet']), 'cree_le' => $this->bd->maintenant(),
                ]);
            }
            $this->recalculer($org, $id);
            $this->audit->noter($org, $c->utilisateurId(), 'credit.accorder', 'credit', $id, null,
                ['client' => $client['code'], 'montant' => $m / 100, 'taux' => $tauxInteret, 'duree' => $dureeJours, 'interet' => $interet / 100, 'derogation' => $derogation, 'controle' => $controle['lignes']],
                $derogation ? trim($motifDerogation) : null);
            return ['id' => $id, 'numero' => $numero];
        });
    }

    /**
     * Tout ce qu'un credit coutera, calcule a l'octroi : interets selon le mode de calcul, frais de dossier,
     * garantie et calendrier. Sert aussi a l'exemple chiffre des regles de credit.
     * @return array{mode:string,interet:int,frais:int,frais_mode:string,garantie:int,total:int,verse:int,echeances:array}
     *   total = ce que le client doit rembourser ; verse = ce qu'il recoit en main
     */
    public static function plan(array $produit, int $montant, string $taux, string $date, int $decimales): array
    {
        $mode = (string) ($produit['mode_calcul'] ?? 'FIXE');
        $calendrier = ($produit['mode_remboursement'] ?? 'LIBRE') === 'CALENDRIER';
        if (!$calendrier) {
            $mode = 'FIXE';
        }
        $interetFixe = Montant::arrondirDevise(Montant::pourcentage($montant, $taux), $decimales);
        $echeances = [];
        if ($calendrier) {
            $dates = Echeancier::dates($date, (int) $produit['echeances_nombre'], (int) $produit['echeances_intervalle'], (string) ($produit['echeances_unite'] ?? 'JOUR'));
            $echeances = Echeancier::amortissement($montant, $taux, $dates, $mode, $interetFixe, $decimales);
        }
        $interet = $mode === 'FIXE' ? $interetFixe : Echeancier::totalInterets($echeances);
        $fraisType = (string) ($produit['frais_dossier_type'] ?? 'AUCUN');
        $frais = match ($fraisType) {
            'FIXE' => Montant::enCentimes($produit['frais_dossier_valeur']),
            'POURCENTAGE' => Montant::arrondirDevise(Montant::pourcentage($montant, (string) $produit['frais_dossier_valeur']), $decimales),
            default => 0,
        };
        $fraisMode = (string) ($produit['frais_dossier_mode'] ?? 'RETENU');
        if ($frais > 0 && $fraisMode === 'AJOUTE' && $echeances) {
            // frais ajoutes au pret : payes avec la premiere echeance
            $echeances[0]['montant'] += $frais;
        }
        $garantie = ($produit['garantie_pct'] ?? null) !== null && (string) $produit['garantie_pct'] !== ''
            ? Montant::arrondirDevise(Montant::pourcentage($montant, (string) $produit['garantie_pct']), $decimales) : 0;
        return [
            'mode' => $mode, 'interet' => $interet, 'frais' => $frais, 'frais_mode' => $fraisMode, 'garantie' => $garantie,
            'total' => $montant + $interet + ($fraisMode === 'AJOUTE' ? $frais : 0),
            'verse' => $montant - ($fraisMode === 'RETENU' ? $frais : 0),
            'echeances' => $echeances,
        ];
    }

    /**
     * Exemple chiffre d'un produit de credit (montant minimum, ou 100 000 a defaut), pour verifier les regles.
     * @param array $produit valeurs du formulaire ou de la base
     */
    public function simulation(Contexte $c, array $produit, string $montantSaisi): ?array
    {
        try {
            $montant = trim($montantSaisi) !== '' ? Montant::enCentimes(Montant::lire($montantSaisi, $c->decimales()))
                : (($produit['montant_minimum'] ?? '') !== '' ? Montant::enCentimes($produit['montant_minimum']) : 10000000);
            if ($montant <= 0) {
                return null;
            }
            return ['montant' => $montant, 'taux' => (string) $produit['taux_interet']] + self::plan($produit, $montant, (string) $produit['taux_interet'], date('Y-m-d'), $c->decimales());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Taux d'interet et duree retenus pour un credit : ceux saisis a l'octroi, sinon ceux des regles.
     * @return array{0:string,1:int}
     */
    public static function conditions(array $produit, string $taux, string $duree): array
    {
        $t = trim($taux) === '' ? (string) $produit['taux_interet'] : Montant::lire($taux, 2, 'credits.taux_invalide');
        if (Montant::comparer($t, '999.99') > 0) {
            throw new ErreurMetier('credits.taux_invalide');
        }
        $d = trim($duree) === '' ? (string) $produit['duree_jours'] : trim($duree);
        if (($produit['mode_remboursement'] ?? 'LIBRE') === 'CALENDRIER') {
            // avec un calendrier, la duree decoule des regles : jusqu'a la derniere echeance
            $aujourdhui = date('Y-m-d');
            $dates = Echeancier::dates($aujourdhui, (int) $produit['echeances_nombre'], (int) $produit['echeances_intervalle'], (string) ($produit['echeances_unite'] ?? 'JOUR'));
            $d = (string) max(1, Dates::joursEntre($aujourdhui, (string) end($dates)));
        }
        if (!ctype_digit($d) || (int) $d < 1 || (int) $d > 3660) {
            throw new ErreurMetier('credits.duree_invalide');
        }
        return [$t, (int) $d];
    }

    // ------------------------------------------------------------------
    // Recalcul d'un credit
    // ------------------------------------------------------------------

    /** Remet un credit d'aplomb : penalites, remboursements, reste a payer, statut, benefices. */
    public function recalculer(int $org, int $creditId): array
    {
        $cr = $this->credits->credit($org, $creditId, null, true) ?? throw new ErreurMetier('credits.introuvable');
        $capital = Montant::enCentimes($cr['montant']);
        $frais = Montant::enCentimes($cr['frais_dossier'] ?? 0);
        $fraisRetenus = ($cr['frais_dossier_mode'] ?? 'RETENU') === 'RETENU';
        // des frais ajoutes au pret se remboursent avec les interets, apres le capital
        $interet = Montant::enCentimes($cr['montant_interet']) + ($fraisRetenus ? 0 : $frais);
        $penalites = 0;
        foreach ($this->credits->penalitesDuCredit($creditId) as $p) {
            $penalites += Montant::enCentimes($p['montant']);
        }
        $du = $capital + $interet + $penalites;

        $this->credits->effacerBenefices($creditId);
        if ($frais > 0 && $fraisRetenus && ($cr['reprise_id'] ?? null) === null) {
            // frais retenus sur le pret : gagnes des l'octroi
            $p = $this->partage->repartir($org, 'FRAIS_DOSSIER', (string) $cr['date_credit'], $frais);
            $this->operations->creerBenefice([
                'organisation_id' => $org, 'date_operation' => $cr['date_credit'], 'periode' => substr((string) $cr['date_credit'], 0, 7),
                'source' => 'FRAIS_DOSSIER', 'source_id' => $creditId, 'agent_id' => $cr['agent_id'], 'client_id' => $cr['client_id'],
                'regle_partage_id' => $p['regle_id'], 'montant_total' => Montant::depuisCentimes($frais),
                'part_agent' => Montant::depuisCentimes($p['agent']), 'part_organisation' => Montant::depuisCentimes($p['organisation']),
                'credit_id' => $creditId, 'cree_le' => $this->bd->maintenant(),
            ]);
        }
        // credit repris du papier : ce qui a ete rembourse avant l'application compte, sans benefice a repartager
        $rembourse = Montant::enCentimes($cr['reprise_rembourse'] ?? 0);
        // avec un tableau d'amortissement, chaque paiement suit le calendrier (interet de l'echeance, puis son capital) ;
        // sinon le capital est rembourse en premier
        $calendrier = $this->credits->echeances($creditId);
        $selonCalendrier = Echeancier::avecAmortissement($calendrier);
        foreach ($this->credits->remboursementsDuCredit($creditId, true) as $r) {
            $m = Montant::enCentimes($r['montant']);
            $b = $selonCalendrier ? Echeancier::partagerPaiement($calendrier, $rembourse, $m) : ControleCredit::beneficeDuRemboursement($capital, $interet, $rembourse, $m);
            $rembourse += $m;
            foreach (['INTERET_CREDIT' => $b['interet'], 'PENALITE_CREDIT' => $b['penalite']] as $source => $centimes) {
                if ($centimes <= 0) {
                    continue;
                }
                $p = $this->partage->repartir($org, $source, (string) $r['date_paiement'], $centimes);
                $this->operations->creerBenefice([
                    'organisation_id' => $org, 'date_operation' => $r['date_paiement'], 'periode' => substr((string) $r['date_paiement'], 0, 7),
                    'source' => $source, 'source_id' => $r['id'], 'agent_id' => $cr['agent_id'], 'client_id' => $cr['client_id'],
                    'regle_partage_id' => $p['regle_id'], 'montant_total' => Montant::depuisCentimes($centimes),
                    'part_agent' => Montant::depuisCentimes($p['agent']), 'part_organisation' => Montant::depuisCentimes($p['organisation']),
                    'credit_id' => $creditId, 'cree_le' => $this->bd->maintenant(),
                ]);
            }
        }
        if ($rembourse > $du) {
            throw new ErreurMetier('credits.trop_rembourse');
        }
        $limite = Dates::ajouterJours((string) $cr['date_echeance'], (int) $cr['jours_tolerance']);
        $echeances = $this->credits->echeances($creditId);
        $retardCalendrier = $echeances && Echeancier::situation($echeances, $rembourse, date('Y-m-d'), (int) $cr['jours_tolerance'])['retard'] > 0;
        $maj = [
            'total_penalites' => Montant::depuisCentimes($penalites), 'total_du' => Montant::depuisCentimes($du),
            'total_rembourse' => Montant::depuisCentimes($rembourse), 'restant' => Montant::depuisCentimes($du - $rembourse),
            'statut' => $rembourse >= $du ? 'REMBOURSE' : (date('Y-m-d') > $limite || $retardCalendrier ? 'EN_RETARD' : 'EN_COURS'),
        ];
        $this->credits->modifierCredit($creditId, $maj);
        return array_merge($cr, $maj);
    }

    // ------------------------------------------------------------------
    // Remboursements
    // ------------------------------------------------------------------

    /** @return array{statut:string,montant:int} */
    public function rembourser(Contexte $c, int $creditId, string $montant, string $date, string $commentaire): array
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $creditId, $montant, $date, $commentaire): array {
            $cr = $this->credits->credit($org, $creditId, $c->recolteurRestreint(), true) ?? throw new ErreurMetier('credits.introuvable');
            if ($cr['statut'] === 'REMBOURSE') {
                throw new ErreurMetier('credits.deja_rembourse');
            }
            if ($date < $cr['date_credit']) {
                throw new ErreurMetier('credits.avant_octroi');
            }
            $m = $this->montant($c, $montant);
            $possible = Montant::enCentimes($cr['restant']) - Montant::enCentimes($this->credits->enAttenteDuCredit($creditId));
            if ($m > $possible) {
                throw new ErreurMetier('credits.au_dessus_du_reste', ['reste' => argent($possible / 100)]);
            }
            $valide = $c->peut('remboursements.valider');
            $id = $this->credits->creerRemboursement([
                'organisation_id' => $org, 'credit_id' => $creditId, 'date_paiement' => $date, 'montant' => Montant::depuisCentimes($m),
                'commentaire' => trim($commentaire) !== '' ? trim($commentaire) : null, 'statut' => $valide ? 'VALIDE' : 'EN_ATTENTE',
                'cree_par' => $c->utilisateurId(), 'traite_par' => $valide ? $c->utilisateurId() : null, 'traite_le' => $valide ? $this->bd->maintenant() : null,
                'cree_le' => $this->bd->maintenant(),
            ]);
            if ($valide) {
                $this->recalculer($org, $creditId);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'remboursement.saisir', 'remboursement', $id, null, ['credit' => $cr['numero'], 'montant' => $m / 100, 'statut' => $valide ? 'VALIDE' : 'EN_ATTENTE']);
            return ['statut' => $valide ? 'VALIDE' : 'EN_ATTENTE', 'montant' => $m];
        });
    }

    /**
     * Le client rembourse avec l'epargne d'un de ses carnets, si les regles du credit le permettent.
     * Le retrait suit les regles du carnet (tout ou tranche, frais, fermeture) ; ce qui depasse
     * le reste a payer est remis au client.
     * @return array{rembourse:int,remis:int,ferme:bool} centimes
     */
    public function rembourserParEpargne(Contexte $c, int $creditId, int $carnetId, string $date, string $montant): array
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $creditId, $carnetId, $date, $montant): array {
            $cr = $this->credits->credit($org, $creditId, $c->recolteurRestreint(), true) ?? throw new ErreurMetier('credits.introuvable');
            if ($cr['statut'] === 'REMBOURSE') {
                throw new ErreurMetier('credits.deja_rembourse');
            }
            $produit = $this->credits->ligne(DepotCredits::TABLES['produits-credit'], $org, (int) $cr['credit_produit_id']);
            if ((int) ($produit['remboursement_epargne'] ?? 0) !== 1) {
                throw new ErreurMetier('credits.epargne_interdite');
            }
            $carnet = $this->carnets->parId($org, $carnetId);
            if ($carnet === null || (int) $carnet['client_id'] !== (int) $cr['client_id']) {
                throw new ErreurMetier('carnets.carnet_introuvable');
            }
            $reste = Montant::enCentimes($cr['restant']) - Montant::enCentimes($this->credits->enAttenteDuCredit($creditId));
            if ($reste <= 0) {
                throw new ErreurMetier('credits.deja_rembourse');
            }
            $retrait = $this->retraits->faire($c, $carnetId, $date, $montant, t('credits.retrait_pour_credit', ['numero' => $cr['numero']]));
            $rembourse = min($retrait['net'], $reste);
            $id = $this->credits->creerRemboursement([
                'organisation_id' => $org, 'credit_id' => $creditId, 'date_paiement' => $date, 'montant' => Montant::depuisCentimes($rembourse),
                'commentaire' => t('credits.paye_par_epargne', ['numero' => $carnet['numero']]), 'statut' => 'VALIDE',
                'cree_par' => $c->utilisateurId(), 'traite_par' => $c->utilisateurId(), 'traite_le' => $this->bd->maintenant(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->recalculer($org, $creditId);
            $this->audit->noter($org, $c->utilisateurId(), 'remboursement.par_epargne', 'remboursement', $id, null,
                ['credit' => $cr['numero'], 'carnet' => $carnet['numero'], 'rembourse' => $rembourse / 100, 'remis_au_client' => ($retrait['net'] - $rembourse) / 100]);
            return ['rembourse' => $rembourse, 'remis' => $retrait['net'] - $rembourse, 'ferme' => (bool) $retrait['ferme']];
        });
    }

    public function traiterRemboursement(Contexte $c, int $id, bool $valider): void
    {
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $id, $valider): void {
            $r = $this->credits->remboursement($org, $id);
            if ($r === null || $r['statut'] !== 'EN_ATTENTE') {
                throw new ErreurMetier('remboursements.introuvable');
            }
            $this->credits->modifierRemboursement($id, ['statut' => $valider ? 'VALIDE' : 'REJETE', 'traite_par' => $c->utilisateurId(), 'traite_le' => $this->bd->maintenant()]);
            if ($valider) {
                $this->recalculer($org, (int) $r['credit_id']);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'remboursement.' . ($valider ? 'valider' : 'rejeter'), 'remboursement', $id);
        });
    }

    // ------------------------------------------------------------------
    // Penalites
    // ------------------------------------------------------------------

    private function basePenalite(array $cr): int
    {
        if ($cr['base_penalite'] === 'CAPITAL') {
            return Montant::enCentimes($cr['montant']);
        }
        // reste du capital et de l'interet : une penalite ne produit pas de penalite
        return max(0, Montant::enCentimes($cr['montant']) + Montant::enCentimes($cr['montant_interet']) - Montant::enCentimes($cr['total_rembourse']));
    }

    public function penaliser(Contexte $c, int $creditId, string $date, string $montant): int
    {
        $date = Dates::passee($date);
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $creditId, $date, $montant): int {
            $cr = $this->credits->credit($org, $creditId, null, true) ?? throw new ErreurMetier('credits.introuvable');
            if ($cr['statut'] === 'REMBOURSE') {
                throw new ErreurMetier('credits.deja_rembourse');
            }
            $base = $this->basePenalite($cr);
            $m = $montant !== '' ? $this->montant($c, $montant) : Montant::arrondirDevise(Montant::pourcentage($base, (string) $cr['taux_penalite']), $c->decimales());
            if ($m <= 0) {
                throw new ErreurMetier('credits.penalite_nulle');
            }
            $id = $this->credits->creerPenalite([
                'organisation_id' => $org, 'credit_id' => $creditId, 'date_penalite' => $date, 'taux' => $cr['taux_penalite'],
                'base' => Montant::depuisCentimes($base), 'montant' => Montant::depuisCentimes($m), 'automatique' => 0,
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->recalculer($org, $creditId);
            $this->audit->noter($org, $c->utilisateurId(), 'credit.penalite', 'penalite', $id, null, ['credit' => $cr['numero'], 'montant' => $m / 100]);
            return $m;
        });
    }

    /**
     * Tache du jour, pour chaque credit echu et non solde :
     * - reconduction : le reste a payer recoit une penalite au taux d'interet du credit, et l'echeance
     *   est repoussee de la duree du credit (110 000 dus, 60 000 payes : 50 000 deviennent 55 000 pour 30 jours de plus) ;
     * - sinon, penalite periodique des regles (par jour, semaine ou mois de retard).
     * Rien n'est jamais compte deux fois.
     */
    public function suivreRetards(int $org, int $decimales): int
    {
        $ajoutees = 0;
        foreach ($this->credits->creditsASuivre($org) as $cr) {
            $this->bd->transaction(function () use ($org, $cr, $decimales, &$ajoutees): void {
                if ((int) $cr['reconduction'] === 1) {
                    $ajoutees += $this->reconduire($org, $cr, $decimales);
                    return;
                }
                $pas = self::PAS[$cr['frequence_penalite']] ?? 0;
                $debut = Dates::ajouterJours((string) $cr['date_echeance'], (int) $cr['jours_tolerance']);
                $retard = Dates::joursEntre($debut, date('Y-m-d'));
                if ($pas > 0 && $retard > 0 && (float) $cr['taux_penalite'] > 0) {
                    $dues = intdiv($retard, $pas);
                    $faites = $this->credits->compterPenalitesAuto((int) $cr['id']);
                    $base = $this->basePenalite($cr);
                    $montant = Montant::arrondirDevise(Montant::pourcentage($base, (string) $cr['taux_penalite']), $decimales);
                    // au plus 90 rattrapages par passage : une organisation restee longtemps sans se connecter ne bloque pas la page
                    for ($n = $faites + 1; $n <= min($dues, $faites + 90) && $montant > 0; $n++) {
                        $this->credits->creerPenalite([
                            'organisation_id' => $org, 'credit_id' => $cr['id'], 'date_penalite' => Dates::ajouterJours($debut, $n * $pas), 'taux' => $cr['taux_penalite'],
                            'base' => Montant::depuisCentimes($base), 'montant' => Montant::depuisCentimes($montant), 'automatique' => 1, 'cree_le' => $this->bd->maintenant(),
                        ]);
                        $ajoutees++;
                    }
                }
                $this->recalculer($org, (int) $cr['id']);
            });
        }
        return $ajoutees;
    }

    /** Reconduit un credit echu autant de fois que d'echeances depassees. @return int nombre de reconductions */
    private function reconduire(int $org, array $cr, int $decimales): int
    {
        $duree = (int) ($cr['duree_jours'] ?: Dates::joursEntre((string) $cr['date_credit'], (string) $cr['date_echeance']));
        $duree = max(1, $duree);
        $faites = 0;
        // au plus 24 reconductions par passage (rattrapage d'une longue absence)
        while ($faites < 24) {
            $cr = $this->recalculer($org, (int) $cr['id']);
            $reste = Montant::enCentimes($cr['restant']);
            $limite = Dates::ajouterJours((string) $cr['date_echeance'], (int) $cr['jours_tolerance']);
            if ($reste <= 0 || date('Y-m-d') <= $limite) {
                break;
            }
            $penalite = Montant::arrondirDevise(Montant::pourcentage($reste, (string) $cr['taux_interet']), $decimales);
            if ($penalite > 0) {
                $this->credits->creerPenalite([
                    'organisation_id' => $org, 'credit_id' => $cr['id'], 'date_penalite' => $cr['date_echeance'], 'taux' => $cr['taux_interet'],
                    'base' => Montant::depuisCentimes($reste), 'montant' => Montant::depuisCentimes($penalite), 'automatique' => 1, 'cree_le' => $this->bd->maintenant(),
                ]);
            }
            $nouvelle = Dates::ajouterJours((string) $cr['date_echeance'], $duree);
            $this->credits->modifierCredit((int) $cr['id'], ['date_echeance' => $nouvelle]);
            $echeances = $this->credits->echeances((int) $cr['id']);
            if ($echeances) {
                foreach (Echeancier::situation($echeances, Montant::enCentimes($cr['total_rembourse']), date('Y-m-d'))['lignes'] as $e) {
                    if ($e['etat'] !== 'PAYEE') {
                        $this->credits->deplacerEcheance((int) $e['id'], $nouvelle);
                    }
                }
                if ($penalite > 0) {
                    $this->credits->creerEcheance([
                        'organisation_id' => $org, 'credit_id' => $cr['id'], 'numero' => count($echeances) + 1, 'date_prevue' => $nouvelle,
                        'montant' => Montant::depuisCentimes($penalite), 'cree_le' => $this->bd->maintenant(),
                    ]);
                }
            }
            $faites++;
        }
        $this->recalculer($org, (int) $cr['id']);
        return $faites;
    }

    // ------------------------------------------------------------------
    // Corbeille
    // ------------------------------------------------------------------

    public function supprimer(Contexte $c, string $type, int $id, string $motif): void
    {
        $table = DepotCredits::TABLES[$type] ?? throw new ErreurMetier('erreur.404.titre');
        $org = $c->organisationId();
        $this->bd->transaction(function () use ($c, $org, $table, $type, $id, $motif): void {
            $ligne = $this->credits->ligne($table, $org, $id);
            if ($ligne === null || $this->credits->marquer($table, $org, $id, $this->bd->maintenant(), $c->utilisateurId(), $motif !== '' ? mb_substr($motif, 0, 255) : null) === 0) {
                throw new ErreurMetier('caisse.ligne_introuvable');
            }
            if ($type === 'credits') {
                $this->credits->effacerBenefices($id);
            } elseif ($type !== 'produits-credit') {
                $this->recalculer($org, (int) $ligne['credit_id']);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'credit.supprimer', $type, $id, null, null, $motif ?: null);
        });
    }

    public function restaurer(Contexte $c, string $type, int $id): string
    {
        $table = DepotCredits::TABLES[$type] ?? throw new ErreurMetier('erreur.404.titre');
        $org = $c->organisationId();
        return $this->bd->transaction(function () use ($c, $org, $table, $type, $id): string {
            $ligne = $this->credits->ligne($table, $org, $id);
            if ($ligne === null || $this->credits->marquer($table, $org, $id, null, null, null) === 0) {
                throw new ErreurMetier('caisse.ligne_introuvable');
            }
            if ($type !== 'produits-credit') {
                $creditId = $type === 'credits' ? $id : (int) $ligne['credit_id'];
                if ($this->credits->credit($org, $creditId) === null) {
                    throw new ErreurMetier('credits.restaurer_credit_avant');
                }
                $this->recalculer($org, $creditId);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'credit.restaurer', $type, $id);
            return t('corbeille.section.' . $type) . ' #' . $id;
        });
    }
}

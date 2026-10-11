<?php
namespace AvecXY\Application;

use AvecXY\Domaine\DoublonAConfirmer;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Noyau\BaseDeDonnees;
use AvecXY\Noyau\Noyau;

/**
 * Application mobile des recolteurs (hors ligne puis synchronisation).
 *
 *  - connexion : identifiant + mot de passe, une fois, en ligne -> un jeton par appareil ;
 *  - portefeuille : tout ce que le recolteur doit avoir dans son telephone pour travailler sans reseau ;
 *  - synchroniser : les saisies faites hors ligne. Chacune porte un identifiant unique cree sur le
 *    telephone : renvoyee dix fois, elle n'est appliquee qu'une fois, et le serveur redonne la meme reponse.
 *
 * Le serveur decide toujours : chaque saisie repasse par les memes services et les memes regles que le web.
 */
final class ServiceMobile
{
    private const DUREE_JETON_JOURS = 60;
    private const ESSAIS_MAX = 5;
    private const FENETRE = 900;
    public const TYPES = ['client.creer', 'carnet.vendre', 'cotisation.saisir', 'cotisation.corriger', 'cotisation.supprimer',
        'remboursement.saisir', 'remboursement.supprimer', 'credit.demander'];

    public function __construct(private readonly BaseDeDonnees $bd, private readonly Noyau $noyau)
    {
    }

    // ------------------------------------------------------------------
    // Connexion et appareils
    // ------------------------------------------------------------------

    /** Un code client (ex. 7TCN-12) ouvre l'espace client ; tout autre identifiant est celui du personnel. */
    public static function estCodeClient(string $identifiant): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-\d+$/', strtoupper(trim($identifiant)));
    }

    /**
     * Ce que l'application fait pour ce compte : RECOLTEUR (tournee hors ligne), GESTION (le site dans l'application)
     * ou LES_DEUX (il bascule de l'un a l'autre).
     */
    public static function profil(Contexte $c): string
    {
        $recolteur = !empty($c->utilisateur['agent_id']) && $c->peut('cotisations.saisir');
        $gestion = $c->estAdmin() || $c->peut('clients.voir_tous') || $c->peut('caisse.voir') || $c->peut('carnets.valider')
            || $c->peut('cotisations.valider') || $c->peut('compta.voir') || $c->peut('rapports.voir');
        return $recolteur ? ($gestion ? 'LES_DEUX' : 'RECOLTEUR') : 'GESTION';
    }

    /** Adresse officielle du site, que l'application adopte (reglee par la plateforme ; vide = inchangee). */
    public function adresse(): string
    {
        return (string) $this->noyau->organisations()->parametrePlateforme('mobile.adresse', '');
    }

    /**
     * Connexion d'un client a son espace depuis l'application : code client + PIN.
     * @return array{organisation_id:int,client_id:int,passage:string}
     */
    public function connecterClient(string $code, string $pin, string $ip): array
    {
        $clientId = $this->noyau->servicePortail()->connecter(strtoupper(trim($code)), $pin, $ip);
        $org = (int) $this->bd->valeur('SELECT organisation_id FROM avecxy_clients WHERE id = ?', [$clientId]);
        return ['organisation_id' => $org, 'client_id' => $clientId, 'passage' => $this->creerPassage('CLIENT', $org, null, $clientId, '/espace-client')];
    }

    /**
     * Passage a usage unique (2 minutes) : l'application ouvre /passage/<code> et le site ouvre la session correspondante.
     * Le code ne sert qu'une fois ; le mot de passe ne transite jamais par l'adresse.
     */
    public function creerPassage(string $type, int $org, ?int $utilisateurId, ?int $compteId, string $cible): string
    {
        $cible = self::cibleSure($cible, $type === 'CLIENT' ? '/espace-client' : '/');
        $code = bin2hex(random_bytes(24));
        $this->bd->inserer('avecxy_passages', [
            'code_hash' => hash('sha256', $code), 'type' => $type, 'organisation_id' => $org, 'utilisateur_id' => $utilisateurId, 'compte_id' => $compteId,
            'cible' => $cible, 'expire_le' => date('Y-m-d H:i:s', time() + 120), 'cree_le' => $this->bd->maintenant(),
        ]);
        return $code;
    }

    /** Utilise un passage (une seule fois) ; null s'il est inconnu, deja utilise ou perime. */
    public function utiliserPassage(string $code): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $code)) {
            return null;
        }
        $p = $this->bd->un('SELECT * FROM avecxy_passages WHERE code_hash = ? AND utilise_le IS NULL AND expire_le > ?', [hash('sha256', $code), $this->bd->maintenant()]);
        if ($p === null) {
            return null;
        }
        // le premier qui le marque l'emporte : un code ne sert jamais deux fois
        if ($this->bd->executer('UPDATE avecxy_passages SET utilise_le = ? WHERE id = ? AND utilise_le IS NULL', [$this->bd->maintenant(), $p['id']]) !== 1) {
            return null;
        }
        $this->bd->executer('DELETE FROM avecxy_passages WHERE expire_le < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        return $p;
    }

    /** Une adresse interne au site seulement (jamais un autre domaine). */
    public static function cibleSure(string $cible, string $defaut): string
    {
        $cible = trim($cible);
        return preg_match('#^/(?!/)[A-Za-z0-9/_\-?=&.%]*$#', $cible) ? mb_substr($cible, 0, 190) : $defaut;
    }

    /** @return array{jeton:string,utilisateur_id:int,organisation_id:int} */
    public function connecter(string $identifiant, string $motDePasse, string $appareilId, string $appareilNom, string $ip): array
    {
        $appareilId = mb_substr(trim($appareilId), 0, 128);
        if ($appareilId === '') {
            throw new ErreurMetier('mobile.appareil_requis');
        }
        // memes protections que la connexion web (limite d'essais, comptes inactifs) ;
        // un seul champ dans l'application : le PIN du compte (4 a 6 chiffres) ou le mot de passe
        $uid = null;
        if (preg_match('/^\d{4,6}$/', $motDePasse)) {
            try {
                $uid = (int) $this->noyau->serviceSecurite()->connecterParPin($identifiant, $motDePasse, $ip)['id'];
            } catch (ErreurMetier) {
                $uid = null;   // pas le PIN : peut-etre un mot de passe fait de chiffres
            }
        }
        $uid ??= $this->noyau->authentification()->connecter($identifiant, $motDePasse, $ip);
        $u = $this->bd->un('SELECT * FROM avecxy_utilisateurs WHERE id = ?', [$uid]);
        $jeton = bin2hex(random_bytes(32));
        $this->bd->executer('UPDATE avecxy_api_jetons SET revoque_le = ? WHERE utilisateur_id = ? AND appareil_id = ? AND revoque_le IS NULL', [$this->bd->maintenant(), $uid, $appareilId]);
        $this->bd->inserer('avecxy_api_jetons', [
            'organisation_id' => $u['organisation_id'], 'utilisateur_id' => $uid, 'appareil_id' => $appareilId,
            'appareil_nom' => mb_substr(trim($appareilNom), 0, 120) ?: null, 'jeton_hash' => hash('sha256', $jeton),
            'cree_le' => $this->bd->maintenant(), 'vu_le' => $this->bd->maintenant(),
            'expire_le' => date('Y-m-d H:i:s', time() + self::DUREE_JETON_JOURS * 86400),
        ]);
        return ['jeton' => $jeton, 'utilisateur_id' => $uid, 'organisation_id' => (int) $u['organisation_id']];
    }

    /** Jeton valide -> le contexte du recolteur ; sinon null. Chaque usage prolonge le jeton. */
    public function contexteDuJeton(string $jeton): ?Contexte
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) {
            return null;
        }
        $j = $this->bd->un('SELECT * FROM avecxy_api_jetons WHERE jeton_hash = ? AND revoque_le IS NULL AND expire_le > ?', [hash('sha256', $jeton), $this->bd->maintenant()]);
        if ($j === null) {
            return null;
        }
        $c = $this->noyau->authentification()->contexte((int) $j['organisation_id'], (int) $j['utilisateur_id']);
        if ($c === null) {
            return null;
        }
        $this->bd->modifier('avecxy_api_jetons', ['vu_le' => $this->bd->maintenant(), 'expire_le' => date('Y-m-d H:i:s', time() + self::DUREE_JETON_JOURS * 86400)], ['id' => $j['id']]);
        return $c;
    }

    public function deconnecter(string $jeton): void
    {
        $this->bd->executer('UPDATE avecxy_api_jetons SET revoque_le = ? WHERE jeton_hash = ? AND revoque_le IS NULL', [$this->bd->maintenant(), hash('sha256', $jeton)]);
    }

    /** Appareils connectes d'une organisation (pour les desactiver en cas de perte ou de vol). */
    public function appareils(int $org): array
    {
        return $this->bd->tous(
            'SELECT j.id, j.appareil_nom, j.appareil_id, j.cree_le, j.vu_le, u.nom AS utilisateur_nom, u.identifiant
             FROM avecxy_api_jetons j JOIN avecxy_utilisateurs u ON u.id = j.utilisateur_id
             WHERE j.organisation_id = ? AND j.revoque_le IS NULL AND j.expire_le > ? ORDER BY j.vu_le DESC',
            [$org, $this->bd->maintenant()]
        );
    }

    public function desactiverAppareil(Contexte $c, int $id): void
    {
        if ($this->bd->executer('UPDATE avecxy_api_jetons SET revoque_le = ? WHERE organisation_id = ? AND id = ? AND revoque_le IS NULL', [$this->bd->maintenant(), $c->organisationId(), $id]) === 0) {
            throw new ErreurMetier('mobile.appareil_introuvable');
        }
        $this->noyau->audit()->noter($c->organisationId(), $c->utilisateurId(), 'mobile.desactiver_appareil', 'appareil', $id);
    }

    // ------------------------------------------------------------------
    // Portefeuille telecharge dans le telephone
    // ------------------------------------------------------------------

    /** Montants en centimes (entiers) : le telephone ne fait aucun calcul en virgule flottante. */
    public function portefeuille(Contexte $c): array
    {
        $org = $c->organisationId();
        $agent = (int) $c->utilisateur['agent_id'];
        $cts = static fn ($v): int => Montant::enCentimes($v);
        $recolteur = $this->noyau->recolteurs()->parId($org, $agent);

        $clients = array_map(static fn ($l) => [
            'id' => (int) $l['id'], 'code' => $l['code'], 'nom' => $l['nom'], 'telephone' => $l['telephone'], 'statut' => $l['statut'],
        ], $this->bd->tous('SELECT id, code, nom, telephone, statut FROM avecxy_clients WHERE organisation_id = ? AND agent_id = ? AND supprime_le IS NULL ORDER BY nom', [$org, $agent]));

        $carnets = array_map(static fn ($k) => [
            'id' => (int) $k['id'], 'client_id' => (int) $k['client_id'], 'numero' => (int) $k['numero'], 'type_nom' => $k['type_nom'], 'mode' => $k['mode'],
            'blocage' => $k['blocage'] ?? null, 'date_echeance' => $k['date_echeance'],
            'mise_c' => $k['mise'] === null ? 0 : $cts($k['mise']), 'nombre_parts' => (int) $k['nombre_parts'], 'parts_validees' => (int) $k['parts_validees'],
            'parts_en_attente' => (int) $k['parts_en_attente'], 'epargne_c' => $cts($k['epargne']), 'en_attente_c' => $cts($k['en_attente']),
            'date_ouverture' => $k['date_ouverture'], 'validation' => $k['validation'],
        ], $this->bd->tous(
            "SELECT k.*, ty.nom AS type_nom,
                (SELECT COALESCE(SUM(o.nombre_parts),0) FROM avecxy_cotisations o WHERE o.carnet_id = k.id AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL) AS parts_en_attente,
                (SELECT COALESCE(SUM(o.montant_total),0) FROM avecxy_cotisations o WHERE o.carnet_id = k.id AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL) AS en_attente
             FROM avecxy_carnets k JOIN avecxy_carnet_types ty ON ty.id = k.carnet_type_id JOIN avecxy_clients c ON c.id = k.client_id
             WHERE k.organisation_id = ? AND k.agent_id = ? AND k.statut = 'ACTIF' AND k.supprime_le IS NULL AND c.supprime_le IS NULL ORDER BY k.id",
            [$org, $agent]
        ));

        $avecCredit = $c->module('credit');
        $credits = !$avecCredit ? [] : array_map(static fn ($cr) => [
            'id' => (int) $cr['id'], 'client_id' => (int) $cr['client_id'], 'numero' => (int) $cr['numero'], 'produit_nom' => $cr['produit_nom'], 'statut' => $cr['statut'],
            'montant_c' => $cts($cr['montant']), 'total_du_c' => $cts($cr['total_du']), 'rembourse_c' => $cts($cr['total_rembourse']), 'restant_c' => $cts($cr['restant']),
            'en_attente_c' => $cts($cr['en_attente']), 'date_credit' => $cr['date_credit'], 'date_echeance' => $cr['date_echeance'],
        ], $this->bd->tous(
            "SELECT cr.*, p.nom AS produit_nom,
                (SELECT COALESCE(SUM(r.montant),0) FROM avecxy_credit_remboursements r WHERE r.credit_id = cr.id AND r.statut = 'EN_ATTENTE' AND r.supprime_le IS NULL) AS en_attente
             FROM avecxy_credits cr JOIN avecxy_credit_produits p ON p.id = cr.credit_produit_id
             WHERE cr.organisation_id = ? AND cr.agent_id = ? AND cr.supprime_le IS NULL AND cr.statut IN ('EN_COURS','EN_RETARD') ORDER BY cr.id",
            [$org, $agent]
        ));

        $types = array_map(static fn ($t) => [
            'id' => (int) $t['id'], 'nom' => $t['nom'], 'mode' => $t['mode'], 'prix_c' => $cts($t['prix']), 'nombre_parts' => (int) $t['nombre_parts'],
            'mise_minimum_c' => $cts($t['mise_minimum']), 'mise_maximum_c' => $t['mise_maximum'] === null ? null : $cts($t['mise_maximum']),
            // compte bloque : durees proposees au client, et ce qui est bloque
            'blocage' => $t['blocage'] ?? null, 'taux_base' => $t['taux_base'] ?? 'AN',
            'options' => array_map(static fn ($o) => ['mois' => (int) $o['mois'], 'taux' => (string) $o['taux']], \AvecXY\Domaine\TypeCarnet::options($t)),
            'reserve_parts' => (int) ($t['parts_reserve'] ?? 0), 'solde_minimum_c' => ($t['solde_minimum'] ?? null) === null ? null : $cts($t['solde_minimum']),
        ], array_values(array_filter($this->noyau->typesCarnet()->liste($org), static fn ($t) => (int) $t['actif'] === 1)));

        // regles de credit : le telephone s'en sert pour une eligibilite indicative ; le serveur la refait a la reception
        $produits = !$avecCredit ? [] : array_map(static fn ($p) => [
            'id' => (int) $p['id'], 'nom' => $p['nom'], 'taux' => (float) $p['taux_interet'], 'duree_jours' => (int) $p['duree_jours'],
            'minimum_c' => $p['montant_minimum'] === null ? null : $cts($p['montant_minimum']), 'maximum_c' => $p['montant_maximum'] === null ? null : $cts($p['montant_maximum']),
            'multiple_epargne' => $p['multiple_epargne_max'] === null ? null : (float) $p['multiple_epargne_max'], 'simultanes_max' => (int) $p['credits_simultanes_max'],
            'sans_retard' => (int) $p['sans_retard_anterieur'] === 1,
            'mode_calcul' => $p['mode_calcul'] ?? 'FIXE', 'garantie_pct' => ($p['garantie_pct'] ?? null) === null ? null : (float) $p['garantie_pct'],
            'echeances' => ($p['mode_remboursement'] ?? 'LIBRE') === 'CALENDRIER' ? (int) $p['echeances_nombre'] : 0, 'echeances_unite' => $p['echeances_unite'] ?? 'JOUR',
            'echeances_intervalle' => (int) ($p['echeances_intervalle'] ?? 1),
        ], $this->noyau->credits()->produits($org, true));

        return [
            'serveur' => ['heure' => $this->bd->maintenant(), 'jour' => date('Y-m-d'), 'adresse' => $this->adresse()],
            'profil' => self::profil($c),
            'utilisateur' => ['id' => $c->utilisateurId(), 'nom' => $c->utilisateur['nom'], 'identifiant' => $c->utilisateur['identifiant']],
            'organisation' => ['id' => $org, 'nom' => $c->organisation['nom'], 'devise' => $c->organisation['devise_symbole'], 'decimales' => $c->decimales(), 'credit' => $avecCredit],
            'recolteur' => ['id' => $agent, 'code' => $recolteur['code'] ?? '', 'nom' => $recolteur['nom'] ?? ''],
            'clients' => $clients, 'carnets' => $carnets, 'credits' => $credits, 'types_carnet' => $types, 'produits_credit' => $produits,
            'saisies' => $this->saisies($c), 'a_deposer' => $this->aDeposer($org, $agent), 'solde_mois' => $this->soldeDuMois($org, $agent),
            'demandes' => !$avecCredit ? [] : array_map(static fn ($d) => [
                'id' => (int) $d['id'], 'client_id' => (int) $d['client_id'], 'statut' => $d['supprime_le'] !== null ? 'REJETE' : $d['statut'],
                'montant_c' => $cts($d['montant_demande']), 'jour' => substr((string) $d['cree_le'], 0, 10), 'motif' => $d['motif_suppression'],
            ], $this->bd->tous('SELECT * FROM avecxy_credit_eligibilites WHERE organisation_id = ? AND agent_id = ? ORDER BY id DESC LIMIT 100', [$org, $agent])),
            'notifications' => array_map(static fn ($n) => ['id' => (int) $n['id'], 'texte' => $n['texte'], 'le' => $n['cree_le'], 'lue' => $n['lu_le'] !== null],
                $this->noyau->notifications()->liste(ServiceNotifications::UTILISATEUR, $c->utilisateurId(), 30)),
        ];
    }

    /** Saisies du recolteur sur 45 jours, avec leur etat et s'il peut encore les corriger (pas encore validees). */
    private function saisies(Contexte $c): array
    {
        $org = $c->organisationId();
        $agent = (int) $c->utilisateur['agent_id'];
        $depuis = date('Y-m-d', strtotime('-45 day'));
        $moi = $c->utilisateurId();
        $lignes = $this->bd->tous(
            "SELECT 'cotisation' AS type, o.id, o.sync_uuid AS uuid, o.date_cotisation AS jour, o.client_id, o.carnet_id AS objet_id, o.nombre_parts AS parts, o.montant_total AS montant,
                    o.statut, o.cree_par, o.supprime_le, o.motif_suppression AS motif
               FROM avecxy_cotisations o WHERE o.organisation_id = ? AND o.agent_id = ? AND o.date_cotisation >= ?
             UNION ALL
             SELECT 'carnet', k.id, NULL, k.date_ouverture, k.client_id, k.id, NULL, k.prix, k.validation, k.cree_par, k.supprime_le, k.motif_suppression
               FROM avecxy_carnets k WHERE k.organisation_id = ? AND k.agent_id = ? AND k.date_ouverture >= ? AND k.reprise_id IS NULL
             UNION ALL
             SELECT 'remboursement', r.id, r.sync_uuid, r.date_paiement, cr.client_id, r.credit_id, NULL, r.montant, r.statut, r.cree_par, r.supprime_le, r.motif_suppression
               FROM avecxy_credit_remboursements r JOIN avecxy_credits cr ON cr.id = r.credit_id
               WHERE r.organisation_id = ? AND cr.agent_id = ? AND r.date_paiement >= ?
             ORDER BY jour DESC, id DESC LIMIT 1500",
            [$org, $agent, $depuis, $org, $agent, $depuis, $org, $agent, $depuis]
        );
        return array_map(static function ($l) use ($moi): array {
            $etat = $l['supprime_le'] !== null ? 'REJETEE' : ($l['statut'] === 'VALIDE' ? 'VALIDEE' : 'EN_ATTENTE');
            return [
                'type' => $l['type'], 'id' => (int) $l['id'], 'uuid' => $l['uuid'], 'jour' => $l['jour'], 'client_id' => (int) $l['client_id'], 'objet_id' => (int) $l['objet_id'],
                'parts' => $l['parts'] === null ? null : (int) $l['parts'], 'montant_c' => Montant::enCentimes($l['montant']), 'etat' => $etat, 'motif' => $l['motif'],
                // corrigeable tant que ce n'est pas valide ; ensuite seules les personnes autorisees le peuvent, sur le web
                'modifiable' => $etat === 'EN_ATTENTE' && (int) $l['cree_par'] === $moi,
            ];
        }, $lignes);
    }

    /** Argent pas encore passe au controle, jour par jour : carnets, cotisations, paiements de credit. */
    private function aDeposer(int $org, int $agent): array
    {
        $jours = [];
        $ajouter = static function (array $lignes, string $cle) use (&$jours): void {
            foreach ($lignes as $l) {
                $jours[$l['jour']] ??= ['jour' => $l['jour'], 'carnets_c' => 0, 'cotisations_c' => 0, 'paiements_c' => 0];
                $jours[$l['jour']][$cle] += Montant::enCentimes($l['total']);
            }
        };
        $ajouter($this->bd->tous("SELECT date_ouverture AS jour, SUM(prix) AS total FROM avecxy_carnets WHERE organisation_id = ? AND agent_id = ? AND validation = 'EN_ATTENTE' AND statut = 'ACTIF' AND supprime_le IS NULL GROUP BY date_ouverture", [$org, $agent]), 'carnets_c');
        $ajouter($this->bd->tous("SELECT date_cotisation AS jour, SUM(montant_total) AS total FROM avecxy_cotisations WHERE organisation_id = ? AND agent_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL GROUP BY date_cotisation", [$org, $agent]), 'cotisations_c');
        $ajouter($this->bd->tous("SELECT r.date_paiement AS jour, SUM(r.montant) AS total FROM avecxy_credit_remboursements r JOIN avecxy_credits cr ON cr.id = r.credit_id
            WHERE r.organisation_id = ? AND cr.agent_id = ? AND r.statut = 'EN_ATTENTE' AND r.supprime_le IS NULL AND cr.supprime_le IS NULL GROUP BY r.date_paiement", [$org, $agent]), 'paiements_c');
        krsort($jours);
        return array_values($jours);
    }

    /** Ce que le recolteur doit recevoir pour le mois en cours : sa part des benefices moins les manquants a deduire. */
    private function soldeDuMois(int $org, int $agent): array
    {
        $periode = date('Y-m');
        $part = Montant::enCentimes($this->bd->valeur('SELECT COALESCE(SUM(part_agent),0) FROM avecxy_benefices WHERE organisation_id = ? AND agent_id = ? AND periode = ?', [$org, $agent, $periode]));
        $manquants = 0;
        if ($this->noyau->serviceCaisse()->manquantsDeduits($org)) {
            $manquants = (int) ($this->noyau->caisse()->manquantsDus($org, $agent)[0]['du'] ?? 0);
        }
        $paye = (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_paiements_agents WHERE organisation_id = ? AND agent_id = ? AND periode = ? AND supprime_le IS NULL', [$org, $agent, $periode]);
        return ['periode' => $periode, 'part_c' => $part, 'manquants_c' => $manquants, 'net_c' => max(0, $part - $manquants), 'deja_paye' => $paye];
    }

    // ------------------------------------------------------------------
    // Synchronisation des saisies hors ligne
    // ------------------------------------------------------------------

    /**
     * @param array<int,array{uuid:string,type:string,donnees:array}> $operations dans l'ordre ou elles ont ete saisies
     * @return array<int,array{uuid:string,statut:string,message:?string,resultat:?array}>
     */
    public function synchroniser(Contexte $c, array $operations): array
    {
        $reponses = [];
        foreach (array_slice($operations, 0, 500) as $op) {
            $uuid = strtolower((string) ($op['uuid'] ?? ''));
            $type = (string) ($op['type'] ?? '');
            $donnees = is_array($op['donnees'] ?? null) ? $op['donnees'] : [];
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) || !in_array($type, self::TYPES, true)) {
                $reponses[] = ['uuid' => $uuid, 'statut' => 'REJETEE', 'message' => t('mobile.operation_invalide'), 'resultat' => null];
                continue;
            }
            $reponses[] = ['uuid' => $uuid] + $this->appliquer($c, $uuid, $type, $donnees);
        }
        return $reponses;
    }

    private function appliquer(Contexte $c, string $uuid, string $type, array $donnees): array
    {
        $org = $c->organisationId();
        $deja = $this->bd->un('SELECT * FROM avecxy_sync_operations WHERE uuid = ?', [$uuid]);
        if ($deja !== null) {
            if ((int) $deja['organisation_id'] !== $org || (int) $deja['utilisateur_id'] !== $c->utilisateurId()) {
                return ['statut' => 'REJETEE', 'message' => t('mobile.operation_invalide'), 'resultat' => null];
            }
            if ($deja['statut'] !== 'EN_COURS') {
                return json_decode((string) $deja['reponse'], true) ?: ['statut' => $deja['statut'], 'message' => null, 'resultat' => null];
            }
            $this->bd->executer('DELETE FROM avecxy_sync_operations WHERE uuid = ?', [$uuid]);   // envoi interrompu : on recommence
        }
        try {
            $reponse = $this->bd->transaction(function () use ($c, $org, $uuid, $type, $donnees): array {
                $this->bd->inserer('avecxy_sync_operations', [
                    'uuid' => $uuid, 'organisation_id' => $org, 'utilisateur_id' => $c->utilisateurId(), 'type' => $type,
                    'charge_hash' => hash('sha256', (string) json_encode($donnees)), 'statut' => 'EN_COURS', 'cree_le' => $this->bd->maintenant(), 'modifie_le' => $this->bd->maintenant(),
                ]);
                $reponse = ['statut' => 'APPLIQUEE', 'message' => null, 'resultat' => $this->executer($c, $uuid, $type, $donnees)];
                $this->bd->modifier('avecxy_sync_operations', ['statut' => 'APPLIQUEE', 'reponse' => json_encode($reponse, JSON_UNESCAPED_UNICODE), 'modifie_le' => $this->bd->maintenant()], ['uuid' => $uuid]);
                return $reponse;
            });
        } catch (DoublonAConfirmer $e) {
            $reponse = ['statut' => 'REJETEE', 'message' => t('clients.masse_doublon', ['code' => $e->homonymes[0]['code'] ?? '']), 'resultat' => null];
        } catch (ErreurMetier $e) {
            $reponse = ['statut' => 'REJETEE', 'message' => t($e->cle, $e->valeurs), 'resultat' => null];
        }
        if ($reponse['statut'] === 'REJETEE') {
            // le refus est garde : le telephone recevra toujours la meme reponse pour cette saisie
            $this->bd->inserer('avecxy_sync_operations', [
                'uuid' => $uuid, 'organisation_id' => $org, 'utilisateur_id' => $c->utilisateurId(), 'type' => $type,
                'charge_hash' => hash('sha256', (string) json_encode($donnees)), 'statut' => 'REJETEE', 'reponse' => json_encode($reponse, JSON_UNESCAPED_UNICODE),
                'cree_le' => $this->bd->maintenant(), 'modifie_le' => $this->bd->maintenant(),
            ]);
        }
        return $reponse;
    }

    /** Identifiant serveur d'un objet : donne directement, ou cree par une saisie precedente du meme telephone (son uuid). */
    private function identifiant(Contexte $c, array $d, string $nom): int
    {
        if ((int) ($d[$nom . '_id'] ?? 0) > 0) {
            return (int) $d[$nom . '_id'];
        }
        $uuid = strtolower((string) ($d[$nom . '_uuid'] ?? ''));
        if ($uuid !== '') {
            $op = $this->bd->un("SELECT reponse FROM avecxy_sync_operations WHERE uuid = ? AND organisation_id = ? AND utilisateur_id = ? AND statut = 'APPLIQUEE'", [$uuid, $c->organisationId(), $c->utilisateurId()]);
            $id = (int) (json_decode((string) ($op['reponse'] ?? ''), true)['resultat'][$nom . '_id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
            throw new ErreurMetier('mobile.dependance_refusee');
        }
        throw new ErreurMetier('mobile.operation_invalide');
    }

    private function executer(Contexte $c, string $uuid, string $type, array $d): array
    {
        $org = $c->organisationId();
        $agent = (int) $c->utilisateur['agent_id'];
        $texte = static fn (string $k): string => trim((string) ($d[$k] ?? ''));
        $jour = $texte('jour') !== '' ? $texte('jour') : date('Y-m-d');
        switch ($type) {
            case 'client.creer':
                $carnet = (int) ($d['type_id'] ?? 0) > 0 ? ['type_id' => (int) $d['type_id'], 'mise' => $texte('mise'), 'date' => $jour, 'versement' => $texte('versement'), 'option_blocage' => $texte('option_blocage')] : null;
                $r = $this->noyau->serviceClients()->creer($c, ['nom' => $texte('nom'), 'telephone' => $texte('telephone'), 'adresse' => $texte('adresse'), 'activite' => $texte('activite'), 'agent_id' => $agent], $carnet, 'GARDER');
                return ['client_id' => (int) $r['client_id'], 'code' => $r['code'], 'carnet_id' => $r['carnet_id'] === null ? null : (int) $r['carnet_id']];
            case 'carnet.vendre':
                $id = $this->noyau->serviceCarnets()->vendre($c, $this->identifiant($c, $d, 'client'), (int) ($d['type_id'] ?? 0), $texte('mise'), $jour, $texte('versement'), $texte('option_blocage'));
                return ['carnet_id' => $id];
            case 'cotisation.saisir':
                $r = $this->noyau->serviceCotisations()->saisir($c, $this->identifiant($c, $d, 'carnet'), $texte('valeur'), $jour, $texte('commentaire'));
                $this->bd->modifier('avecxy_cotisations', ['sync_uuid' => $uuid], ['id' => $r['id']]);
                return ['cotisation_id' => (int) $r['id'], 'etat' => $r['statut']];
            case 'cotisation.corriger':
                $id = $this->identifiant($c, $d, 'cotisation');
                $this->noyau->serviceCotisations()->corriger($c, $id, $texte('valeur'), $jour);
                return ['cotisation_id' => $id];
            case 'cotisation.supprimer':
                $id = $this->identifiant($c, $d, 'cotisation');
                $this->noyau->serviceCotisations()->supprimer($c, $id, $texte('motif') !== '' ? $texte('motif') : t('mobile.annulee_par_recolteur'));
                return ['cotisation_id' => $id];
            case 'remboursement.saisir':
                $creditId = $this->identifiant($c, $d, 'credit');
                $this->noyau->serviceCredits()->rembourser($c, $creditId, $texte('montant'), $jour, $texte('commentaire'));
                $id = (int) $this->bd->valeur('SELECT MAX(id) FROM avecxy_credit_remboursements WHERE credit_id = ? AND cree_par = ?', [$creditId, $c->utilisateurId()]);
                $this->bd->modifier('avecxy_credit_remboursements', ['sync_uuid' => $uuid], ['id' => $id]);
                return ['remboursement_id' => $id];
            case 'remboursement.supprimer':
                $id = $this->identifiant($c, $d, 'remboursement');
                $r = $this->bd->un('SELECT * FROM avecxy_credit_remboursements WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
                if ($r === null || $r['statut'] !== 'EN_ATTENTE' || (int) $r['cree_par'] !== $c->utilisateurId()) {
                    throw new ErreurMetier('cotisations.verrouillee');   // deja validee : seule une personne autorisee peut la modifier
                }
                $this->noyau->serviceCredits()->supprimer($c, 'remboursements', $id, t('mobile.annulee_par_recolteur'));
                return ['remboursement_id' => $id];
            case 'credit.demander':
                $client = $this->bd->un('SELECT code FROM avecxy_clients WHERE organisation_id = ? AND id = ? AND agent_id = ?', [$org, $this->identifiant($c, $d, 'client'), $agent]) ?? throw new ErreurMetier('credits.client_introuvable');
                $id = $this->noyau->serviceCredits()->proposer($c, (string) $client['code'], (int) ($d['produit_id'] ?? 0), $texte('montant'), $texte('commentaire'));
                return ['demande_id' => $id];
        }
        throw new ErreurMetier('mobile.operation_invalide');
    }
}

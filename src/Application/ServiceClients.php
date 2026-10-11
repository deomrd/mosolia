<?php
namespace AvecXY\Application;

use AvecXY\Domaine\DoublonAConfirmer;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotCarnets;
use AvecXY\Infrastructure\DepotClients;
use AvecXY\Infrastructure\DepotParametres;
use AvecXY\Infrastructure\DepotRecolteurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Clients epargnants. Les noms identiques suivent la regle choisie par l'organisation. */
final class ServiceClients
{
    public const PORTEES = ['RECOLTEUR', 'ORGANISATION', 'AUCUN'];
    public const ACTIONS = ['DEMANDER', 'FUSIONNER', 'RENOMMER', 'GARDER'];

    public const PIECES = ['CARTE_ELECTEUR', 'CARTE_IDENTITE', 'PASSEPORT', 'PERMIS', 'AUTRE'];
    public const CHAMPS_DOSSIER = ['sexe', 'date_naissance', 'lieu_naissance', 'piece_type', 'piece_numero', 'piece_expire_le', 'contact_urgence'];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotClients $clients,
        private readonly DepotCarnets $carnets,
        private readonly DepotRecolteurs $recolteurs,
        private readonly DepotParametres $parametres,
        private readonly ServiceCarnets $serviceCarnets,
        private readonly Audit $audit,
    ) {
    }

    /**
     * Cree un client et, si demande, lui vend son premier carnet.
     * @param array{nom:string,telephone:string,adresse:string,activite:string,agent_id:int} $d
     * @param ?array{type_id:int,mise:string,date:string,versement:string} $carnet
     * @param string $choix reponse a un doublon : FUSIONNER, RENOMMER ou GARDER (vide = suivre la regle)
     * @return array{client_id:int,carnet_id:?int,code:string,fusionne:bool}
     * @throws DoublonAConfirmer quand l'organisation demande de trancher a chaque fois
     */
    public function creer(Contexte $c, array $d, ?array $carnet, string $choix = ''): array
    {
        $org = $c->organisationId();
        $nom = Texte::nom($d['nom']);
        if ($nom === '') {
            throw new ErreurMetier('clients.nom_obligatoire');
        }
        $agentId = $this->recolteurPour($c, (int) $d['agent_id']);

        $homonymes = $this->clients->homonymes($org, $nom, $this->parametres->lire($org, 'clients.doublon_portee'), $agentId);
        $action = '';
        if ($homonymes) {
            $action = $this->parametres->lire($org, 'clients.doublon_action');
            if ($action === 'DEMANDER') {
                if (!in_array($choix, ['FUSIONNER', 'RENOMMER', 'GARDER'], true)) {
                    throw new DoublonAConfirmer($homonymes);
                }
                $action = $choix;
            }
        }

        return $this->bd->transaction(function () use ($c, $org, $d, $nom, $agentId, $carnet, $homonymes, $action): array {
            if ($action === 'FUSIONNER') {
                // on ne cree pas de second client : le carnet s'ouvre sur celui qui existe
                if ($carnet === null) {
                    throw new ErreurMetier('clients.existe_deja', ['code' => $homonymes[0]['code']]);
                }
                $existant = $homonymes[0];
                $carnetId = $this->serviceCarnets->vendre($c, (int) $existant['id'], $carnet['type_id'], $carnet['mise'], $carnet['date'], $carnet['versement'], (string) ($carnet['option_blocage'] ?? ''));
                return ['client_id' => (int) $existant['id'], 'carnet_id' => $carnetId, 'code' => $existant['code'], 'fusionne' => true];
            }
            if ($action === 'RENOMMER') {
                $pris = $this->clients->nomsCommencantPar($org, $nom);
                for ($n = 2; in_array($nom . ' ' . $n, $pris, true); $n++);
                $nom .= ' ' . $n;
            }
            if ($action === 'GARDER' && $carnet !== null && trim($carnet['mise']) !== '') {
                // deux clients du meme nom restent distinguables par leur mise
                $mise = Montant::enCentimes(Montant::lire($carnet['mise'], $c->decimales(), 'carnets.mise_invalide'));
                foreach ($homonymes as $h) {
                    foreach ($this->carnets->duClient($org, (int) $h['id']) as $k) {
                        if ($k['statut'] === 'ACTIF' && $k['mode'] === 'PARTS' && Montant::enCentimes($k['mise']) === $mise) {
                            throw new ErreurMetier('clients.meme_mise', ['code' => $h['code']]);
                        }
                    }
                }
            }

            $max = $c->forfait['max_clients'] ?? null;
            if ($max !== null && $this->clients->compter($org) >= (int) $max) {
                throw new ErreurMetier('clients.limite_forfait', ['n' => (int) $max]);
            }
            $code = $this->clients->prochainCode($org, $agentId);
            $ligne = [
                'organisation_id' => $org, 'agent_id' => $agentId, 'code' => $code, 'nom' => $nom,
                'telephone' => trim($d['telephone']) !== '' ? Texte::identifiant($d['telephone']) : null,
                'adresse' => trim($d['adresse']) !== '' ? Texte::nom($d['adresse']) : ($c->organisation['ville_defaut'] ?: null),
                'activite' => trim($d['activite']) !== '' ? Texte::nom($d['activite']) : null,
                'statut' => 'ACTIF', 'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ] + self::dossier($d);
            $id = $this->clients->creer($ligne);
            $this->audit->noter($org, $c->utilisateurId(), 'client.creer', 'client', $id, null, $ligne);
            $carnetId = $carnet === null ? null
                : $this->serviceCarnets->vendre($c, $id, $carnet['type_id'], $carnet['mise'], $carnet['date'], $carnet['versement'], (string) ($carnet['option_blocage'] ?? ''));
            return ['client_id' => $id, 'carnet_id' => $carnetId, 'code' => $code, 'fusionne' => false];
        });
    }

    /** @param array{nom:string,telephone:string,adresse:string,activite:string,statut:string} $d */
    public function modifier(Contexte $c, int $id, array $d): void
    {
        $org = $c->organisationId();
        $avant = $this->clients->parId($org, $id, $c->recolteurRestreint()) ?? throw new ErreurMetier('clients.introuvable');
        $nom = Texte::nom($d['nom']);
        if ($nom === '') {
            throw new ErreurMetier('clients.nom_obligatoire');
        }
        if ($nom !== $avant['nom'] && $this->parametres->lire($org, 'clients.doublon_action') !== 'GARDER'
            && $this->clients->homonymes($org, $nom, $this->parametres->lire($org, 'clients.doublon_portee'), (int) $avant['agent_id'], $id)) {
            throw new ErreurMetier('clients.nom_pris');
        }
        $ligne = [
            'nom' => $nom,
            'telephone' => trim($d['telephone']) !== '' ? Texte::identifiant($d['telephone']) : null,
            'adresse' => trim($d['adresse']) !== '' ? Texte::nom($d['adresse']) : null,
            'activite' => trim($d['activite']) !== '' ? Texte::nom($d['activite']) : null,
            'statut' => $d['statut'] === 'INACTIF' ? 'INACTIF' : 'ACTIF',
        ] + self::dossier($d);
        $this->clients->modifier($org, $id, $ligne);
        $this->audit->noter($org, $c->utilisateurId(), 'client.modifier', 'client', $id, array_intersect_key($avant, $ligne), $ligne);
    }

    /**
     * Dossier du client : identite, piece, personne a prevenir. Tout est facultatif ; ce qui est saisi est controle.
     * @return array<string,?string>
     */
    public static function dossier(array $d): array
    {
        $texte = static fn (string $cle, int $max): ?string => trim((string) ($d[$cle] ?? '')) === '' ? null : mb_substr(trim((string) $d[$cle]), 0, $max);
        $date = static function (string $cle, bool $passee) use ($d): ?string {
            $v = trim((string) ($d[$cle] ?? ''));
            if ($v === '') {
                return null;
            }
            return $passee ? Dates::passee($v, 'clients.date_invalide') : (Dates::ou($v, '') ?: throw new ErreurMetier('clients.date_invalide'));
        };
        $sexe = strtoupper(trim((string) ($d['sexe'] ?? '')));
        $piece = strtoupper(trim((string) ($d['piece_type'] ?? '')));
        $numero = $texte('piece_numero', 60);
        return [
            'sexe' => in_array($sexe, ['F', 'M'], true) ? $sexe : null,
            'date_naissance' => $date('date_naissance', true),
            'lieu_naissance' => ($l = $texte('lieu_naissance', 120)) === null ? null : Texte::nom($l),
            'piece_type' => in_array($piece, self::PIECES, true) ? $piece : null,
            'piece_numero' => $numero === null ? null : strtoupper(preg_replace('/\s+/', '', $numero)),
            'piece_expire_le' => $date('piece_expire_le', false),
            'contact_urgence' => $texte('contact_urgence', 160),
        ];
    }

    /**
     * Photo du client (JPEG, PNG ou WebP, 3 Mo au plus), rangee hors du dossier public.
     * @param array{tmp_name:string,size:int,error:int} $fichier
     */
    public function photo(Contexte $c, int $id, array $fichier, string $dossier): void
    {
        $org = $c->organisationId();
        $client = $this->clients->parId($org, $id, $c->recolteurRestreint()) ?? throw new ErreurMetier('clients.introuvable');
        if (($fichier['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($fichier['tmp_name'] ?? ''))) {
            throw new ErreurMetier('clients.photo_invalide');
        }
        if ((int) $fichier['size'] > 3 * 1024 * 1024) {
            throw new ErreurMetier('clients.photo_trop_grande');
        }
        $info = @getimagesize($fichier['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if ($extension === null) {
            throw new ErreurMetier('clients.photo_invalide');
        }
        if (!is_dir($dossier)) {
            mkdir($dossier, 0750, true);
        }
        $nom = $org . '-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!move_uploaded_file($fichier['tmp_name'], $dossier . '/' . $nom)) {
            throw new ErreurMetier('clients.photo_invalide');
        }
        if (!empty($client['photo']) && is_file($dossier . '/' . basename((string) $client['photo']))) {
            @unlink($dossier . '/' . basename((string) $client['photo']));
        }
        $this->clients->modifier($org, $id, ['photo' => $nom]);
        $this->audit->noter($org, $c->utilisateurId(), 'client.photo', 'client', $id);
    }

    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        $org = $c->organisationId();
        $client = $this->clients->parId($org, $id) ?? throw new ErreurMetier('clients.introuvable');
        if ($this->clients->compterEngagements($id) > 0) {
            throw new ErreurMetier('clients.a_des_carnets');
        }
        $this->clients->modifier($org, $id, ['supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(), 'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null]);
        $this->audit->noter($org, $c->utilisateurId(), 'client.supprimer', 'client', $id, $client, null, $motif ?: null);
        return $client['code'] . ' ' . $client['nom'];
    }

    public function restaurer(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $client = $this->clients->parId($org, $id, null, true);
        if ($client === null || $client['supprime_le'] === null) {
            throw new ErreurMetier('clients.introuvable');
        }
        $this->clients->modifier($org, $id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
        $this->audit->noter($org, $c->utilisateurId(), 'client.restaurer', 'client', $id);
        return $client['code'] . ' ' . $client['nom'];
    }

    /** Recolteur du nouveau client : le sien pour un recolteur, celui choisi pour le bureau. */
    private function recolteurPour(Contexte $c, int $demande): int
    {
        $restreint = $c->recolteurRestreint();
        if ($restreint !== null) {
            if ($restreint === 0) {
                throw new ErreurMetier('clients.sans_fiche_recolteur');
            }
            $demande = $restreint;
        }
        $r = $this->recolteurs->parId($c->organisationId(), $demande);
        if ($r === null || $r['statut'] !== 'ACTIF') {
            throw new ErreurMetier('clients.recolteur_obligatoire');
        }
        return $demande;
    }
}

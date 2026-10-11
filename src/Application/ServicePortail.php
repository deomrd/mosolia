<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Infrastructure\DepotClients;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotPortail;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Espace client : l'epargnant consulte son solde et son historique.
 * Connexion sans code d'organisation : code client (ex. JM4K-12) + code secret a chiffres.
 * Module du forfait, que l'organisation active ou non.
 */
final class ServicePortail
{
    private const ESSAIS_MAX = 5;
    private const FENETRE = 900;

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotPortail $portail,
        private readonly DepotClients $clients,
        private readonly DepotOrganisations $organisations,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly Audit $audit,
    ) {
    }

    /** L'organisation a-t-elle activé le module (et son forfait le comprend-il) ? */
    public static function actif(?string $modulesActifs, bool $dansLeForfait): bool
    {
        $m = json_decode((string) $modulesActifs, true);
        return $dansLeForfait && is_array($m) && !empty($m['espace_client']);
    }

    public function actifPour(Contexte $c): bool
    {
        return self::actif($c->organisation['modules_actifs'] ?? null, $c->module('espace_client'));
    }

    // ---------------- cote organisation ----------------

    public function activerModule(Contexte $c, bool $activer): void
    {
        if ($activer && !$c->module('espace_client')) {
            throw new ErreurMetier('erreur.module.texte');
        }
        $m = json_decode((string) ($c->organisation['modules_actifs'] ?? ''), true) ?: [];
        $m['espace_client'] = $activer;
        $this->organisations->modifier($c->organisationId(), ['modules_actifs' => json_encode($m)]);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'espace_client.' . ($activer ? 'activer' : 'desactiver'), 'organisation', $c->organisationId());
    }

    /** Ouvre (ou reinitialise) l'acces d'un client. @return string le code secret, a remettre au client : il n'est affiche qu'une fois */
    public function ouvrirAcces(Contexte $c, int $clientId): string
    {
        if (!$this->actifPour($c)) {
            throw new ErreurMetier('portail.module_inactif');
        }
        $client = $this->clients->parId($c->organisationId(), $clientId, $c->recolteurRestreint()) ?? throw new ErreurMetier('clients.introuvable');
        $secret = $this->attribuer($c, $client);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'espace_client.acces', 'client', $clientId, null, ['client' => $client['code']]);
        return $secret;
    }

    /** PIN par defaut a 4 chiffres, jamais trivial ; le client devra le changer a sa premiere connexion. */
    public static function pinParDefaut(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '0123', '4321', '2580', '1212'], true));
        return $pin;
    }

    private function attribuer(Contexte $c, array $client): string
    {
        $secret = self::pinParDefaut();
        $ligne = ['code_secret' => password_hash($secret, PASSWORD_BCRYPT, ['cost' => 8]), 'doit_changer_code' => 1, 'actif' => 1, 'active_par' => $c->utilisateurId()];
        if ($this->portail->acces((int) $client['id']) === null) {
            $this->portail->creerAcces($ligne + ['client_id' => $client['id'], 'organisation_id' => $c->organisationId(), 'telephone' => $client['telephone'], 'cree_le' => $this->bd->maintenant()]);
        } else {
            $this->portail->modifierAcces((int) $client['id'], $ligne);
        }
        return $secret;
    }

    /**
     * Genere les identifiants de tous les clients actifs qui n'en ont pas encore (les anciens clients).
     * @return array{liste:array<int,array{code:string,nom:string,pin:string,recolteur:string}>,restant:int}
     */
    public function genererPourTous(Contexte $c, int $maximum = 500): array
    {
        if (!$this->actifPour($c)) {
            throw new ErreurMetier('portail.module_inactif');
        }
        $org = $c->organisationId();
        $sans = $this->portail->clientsSansAcces($org);
        $liste = [];
        $this->bd->transaction(function () use ($c, $sans, $maximum, &$liste): void {
            foreach (array_slice($sans, 0, $maximum) as $client) {
                $liste[] = ['code' => $client['code'], 'nom' => $client['nom'], 'pin' => $this->attribuer($c, $client), 'recolteur' => $client['recolteur_nom']];
            }
        });
        $this->audit->noter($org, $c->utilisateurId(), 'espace_client.generer', 'organisation', $org, null, ['nombre' => count($liste)]);
        return ['liste' => $liste, 'restant' => max(0, count($sans) - count($liste))];
    }

    public function fermerAcces(Contexte $c, int $clientId): void
    {
        $client = $this->clients->parId($c->organisationId(), $clientId, $c->recolteurRestreint()) ?? throw new ErreurMetier('clients.introuvable');
        $this->portail->modifierAcces($clientId, ['actif' => 0, 'groupe_id' => null]);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'espace_client.fermer', 'client', $clientId, null, ['client' => $client['code']]);
    }

    // ---------------- cote epargnant ----------------

    /** Compte utilisable : acces ouvert, client actif, module actif, organisation non suspendue. */
    public function compteUtilisable(?array $compte): bool
    {
        return $compte !== null && (int) $compte['actif'] === 1 && $compte['statut'] === 'ACTIF' && $compte['organisation_statut'] !== 'SUSPENDUE'
            && self::actif($compte['modules_actifs'], $this->portail->forfaitAvecModule((int) $compte['organisation_id']));
    }

    /** Verifie un code client et son code secret, avec limitation des essais. @return array le compte */
    private function verifier(string $code, string $secret, string $ip): array
    {
        $code = strtoupper(trim($code));
        $cle = hash('sha256', 'portail|' . $ip . '|' . $code);
        $maintenant = time();
        $t = $this->utilisateurs->tentative($cle);
        if ($t !== null && (int) $t['bloque_jusqua'] > $maintenant) {
            throw new ErreurMetier('connexion.trop_de_tentatives');
        }
        $compte = $this->portail->compteParCode($code);
        $correct = password_verify($secret, $compte['code_secret'] ?? '$2y$10$qdplnI0IrQsBcc6moZWM7.ViO/n1JJzBhkYy5ukXMDBswc3QSpJkC');
        if (!$correct || !$this->compteUtilisable($compte)) {
            $nombre = 1;
            $debut = $maintenant;
            if ($t !== null && (int) $t['debut_fenetre'] >= $maintenant - self::FENETRE) {
                $nombre = (int) $t['nombre'] + 1;
                $debut = (int) $t['debut_fenetre'];
            }
            $this->utilisateurs->enregistrerTentative($cle, $nombre, $debut, $nombre >= self::ESSAIS_MAX ? $maintenant + self::FENETRE : 0);
            throw new ErreurMetier('portail.identifiants_incorrects');
        }
        $this->utilisateurs->effacerTentatives($cle);
        return $compte;
    }

    public function connecter(string $code, string $secret, string $ip): int
    {
        $compte = $this->verifier($code, $secret, $ip);
        $this->portail->modifierAcces((int) $compte['id'], ['derniere_connexion' => $this->bd->maintenant()]);
        return (int) $compte['id'];
    }

    public function changerCode(int $clientId, string $actuel, string $nouveau, string $confirmation): void
    {
        $acces = $this->portail->acces($clientId);
        if ($acces === null || !password_verify($actuel, $acces['code_secret'])) {
            throw new ErreurMetier('portail.code_actuel_incorrect');
        }
        if (!preg_match('/^\d{4,8}$/', $nouveau)) {
            throw new ErreurMetier('portail.code_format');
        }
        if ($nouveau !== $confirmation) {
            throw new ErreurMetier('portail.code_confirmation');
        }
        if ($nouveau === $actuel || preg_match('/^(\d)\1+$/', $nouveau) || in_array($nouveau, ['1234', '12345', '123456', '0123', '4321'], true)) {
            throw new ErreurMetier('portail.code_trop_simple');
        }
        $this->portail->modifierAcces($clientId, ['code_secret' => password_hash($nouveau, PASSWORD_DEFAULT), 'doit_changer_code' => 0]);
    }

    /** Rattache un autre compte du meme epargnant : il prouve qu'il en connait le code secret. */
    public function lier(int $clientId, string $code, string $secret, string $ip): void
    {
        $autre = $this->verifier($code, $secret, $ip);
        if ((int) $autre['id'] === $clientId) {
            throw new ErreurMetier('portail.deja_lie');
        }
        $this->bd->transaction(function () use ($clientId, $autre): void {
            $moi = $this->portail->acces($clientId);
            $groupe = $moi['groupe_id'] ?: self::uuid();
            if (empty($moi['groupe_id'])) {
                $this->portail->modifierAcces($clientId, ['groupe_id' => $groupe]);
            }
            if (!empty($autre['groupe_id']) && $autre['groupe_id'] !== $groupe) {
                $this->portail->fusionnerGroupes((string) $autre['groupe_id'], $groupe);
            } else {
                $this->portail->modifierAcces((int) $autre['id'], ['groupe_id' => $groupe]);
            }
        });
    }

    private static function uuid(): string
    {
        $o = random_bytes(16);
        $o[6] = chr((ord($o[6]) & 0x0f) | 0x40);
        $o[8] = chr((ord($o[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($o), 4));
    }
}

<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CleAppareil;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Connexions rapides : PIN, et empreinte ou visage (cle de l'appareil).
 * Le PIN sert de connexion secondaire sur le web et de connexion principale dans l'application mobile.
 */
final class ServiceSecurite
{
    private const ESSAIS_MAX = 5;
    private const FENETRE = 900;

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly Audit $audit,
    ) {
    }

    // ---------------- PIN ----------------

    public function definirPin(Contexte $c, string $motDePasse, string $pin, string $confirmation): void
    {
        $u = $this->utilisateurs->parId($c->organisationId(), $c->utilisateurId()) ?? throw new ErreurMetier('utilisateurs.introuvable');
        if (!password_verify($motDePasse, (string) $u['mot_de_passe'])) {
            throw new ErreurMetier('compte.mot_de_passe_actuel_incorrect');
        }
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            throw new ErreurMetier('pin.format');
        }
        if ($pin !== $confirmation) {
            throw new ErreurMetier('pin.confirmation');
        }
        if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '12345', '123456', '0123', '4321', '654321'], true)) {
            throw new ErreurMetier('pin.trop_simple');
        }
        $this->utilisateurs->modifier($c->organisationId(), $c->utilisateurId(), ['pin' => password_hash($pin, PASSWORD_DEFAULT)]);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'compte.pin', 'utilisateur', $c->utilisateurId());
    }

    /** @return array l'utilisateur connecte */
    public function connecterParPin(string $identifiant, string $pin, string $ip): array
    {
        $identifiant = Texte::identifiant($identifiant);
        $cle = hash('sha256', 'pin|' . $ip . '|' . $identifiant);
        $maintenant = time();
        $t = $this->utilisateurs->tentative($cle);
        if ($t !== null && (int) $t['bloque_jusqua'] > $maintenant) {
            throw new ErreurMetier('connexion.trop_de_tentatives');
        }
        $u = $this->utilisateurs->parIdentifiant($identifiant);
        $correct = password_verify($pin, (string) ($u['pin'] ?? '') !== '' ? (string) $u['pin'] : '$2y$10$qdplnI0IrQsBcc6moZWM7.ViO/n1JJzBhkYy5ukXMDBswc3QSpJkC');
        if ($u === null || empty($u['pin']) || !$correct || $u['statut'] !== 'ACTIF') {
            $nombre = 1;
            $debut = $maintenant;
            if ($t !== null && (int) $t['debut_fenetre'] >= $maintenant - self::FENETRE) {
                $nombre = (int) $t['nombre'] + 1;
                $debut = (int) $t['debut_fenetre'];
            }
            $this->utilisateurs->enregistrerTentative($cle, $nombre, $debut, $nombre >= self::ESSAIS_MAX ? $maintenant + self::FENETRE : 0);
            throw new ErreurMetier('pin.incorrect');
        }
        $this->utilisateurs->effacerTentatives($cle);
        $this->ouvrir($u, 'connexion.pin');
        return $u;
    }

    private function ouvrir(array $u, string $action): void
    {
        $this->utilisateurs->modifier((int) $u['organisation_id'], (int) $u['id'], ['derniere_connexion' => date('Y-m-d H:i:s')]);
        $this->audit->noter((int) $u['organisation_id'], (int) $u['id'], $action, 'utilisateur', (int) $u['id']);
    }

    // ---------------- empreinte ou visage ----------------

    public static function disponible(): bool
    {
        return function_exists('openssl_verify');
    }

    public function nouveauDefi(): string
    {
        return CleAppareil::versTexte(random_bytes(32));
    }

    public function cles(int $utilisateurId): array
    {
        return $this->bd->tous('SELECT id, identifiant_cle, nom, cree_le, derniere_utilisation FROM avecxy_utilisateur_cles WHERE utilisateur_id = ? ORDER BY id', [$utilisateurId]);
    }

    /** Identifiants des cles d'un compte, pour proposer la bonne a l'appareil. Liste vide si le compte n'existe pas. */
    public function clesDuCompte(string $identifiant): array
    {
        $u = $this->utilisateurs->parIdentifiant(Texte::identifiant($identifiant));
        return $u === null || $u['statut'] !== 'ACTIF' ? [] : array_column($this->cles((int) $u['id']), 'identifiant_cle');
    }

    /** Enregistre la cle creee par l'appareil apres reconnaissance de la personne. */
    public function enregistrerCle(Contexte $c, string $defi, string $origine, string $domaine, string $clientData, string $attestation, string $nom): void
    {
        if ($defi === '' || !self::disponible()) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $clientDataJson = CleAppareil::versOctets($clientData);
        CleAppareil::verifierClient($clientDataJson, 'webauthn.create', $defi, $origine);
        $d = CleAppareil::lireDonnees(CleAppareil::donneesDeLAttestation(CleAppareil::versOctets($attestation)), $domaine);
        if ($d['identifiant'] === null || $d['cle'] === null) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        if ($this->bd->valeur('SELECT COUNT(*) FROM avecxy_utilisateur_cles WHERE identifiant_cle = ?', [$d['identifiant']])) {
            throw new ErreurMetier('cle.deja_enregistree');
        }
        $this->bd->inserer('avecxy_utilisateur_cles', [
            'utilisateur_id' => $c->utilisateurId(), 'identifiant_cle' => $d['identifiant'], 'cle_publique' => $d['cle'], 'compteur' => $d['compteur'],
            'nom' => trim($nom) !== '' ? mb_substr(trim($nom), 0, 120) : null, 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'compte.cle_ajoutee', 'utilisateur', $c->utilisateurId());
    }

    public function supprimerCle(Contexte $c, int $id): void
    {
        $this->bd->executer('DELETE FROM avecxy_utilisateur_cles WHERE id = ? AND utilisateur_id = ?', [$id, $c->utilisateurId()]);
        $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'compte.cle_retiree', 'utilisateur', $c->utilisateurId());
    }

    /** @return array l'utilisateur connecte */
    public function connecterParCle(string $defi, string $origine, string $domaine, string $identifiantCle, string $clientData, string $donnees, string $signature, string $identifiantSaisi = ''): array
    {
        if ($defi === '' || !self::disponible()) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $cle = $this->bd->un('SELECT * FROM avecxy_utilisateur_cles WHERE identifiant_cle = ?', [$identifiantCle]) ?? throw new ErreurMetier('cle.inconnue');
        $clientDataJson = CleAppareil::versOctets($clientData);
        $donneesBrutes = CleAppareil::versOctets($donnees);
        CleAppareil::verifierClient($clientDataJson, 'webauthn.get', $defi, $origine);
        $d = CleAppareil::lireDonnees($donneesBrutes, $domaine);
        if (!CleAppareil::signatureValide($donneesBrutes, $clientDataJson, CleAppareil::versOctets($signature), (string) $cle['cle_publique'])) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        // un compteur qui recule signale une cle copiee
        if ($d['compteur'] !== 0 && $d['compteur'] <= (int) $cle['compteur']) {
            throw new ErreurMetier('cle.reponse_invalide');
        }
        $u = $this->bd->un("SELECT * FROM avecxy_utilisateurs WHERE id = ? AND supprime_le IS NULL AND statut = 'ACTIF'", [$cle['utilisateur_id']]) ?? throw new ErreurMetier('cle.inconnue');
        // l'appareil doit appartenir au compte saisi sur la page de connexion
        if ($identifiantSaisi !== '' && $u['identifiant'] !== $identifiantSaisi) {
            throw new ErreurMetier('cle.inconnue');
        }
        $this->bd->modifier('avecxy_utilisateur_cles', ['compteur' => $d['compteur'], 'derniere_utilisation' => $this->bd->maintenant()], ['id' => $cle['id']]);
        $this->ouvrir($u, 'connexion.cle');
        return $u;
    }
}

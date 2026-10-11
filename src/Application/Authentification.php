<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\EtatAbonnement;
use AvecXY\Domaine\ResolveurDroits;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotProfils;
use AvecXY\Infrastructure\DepotUtilisateurs;

/** Connexion du personnel : e-mail ou telephone + mot de passe, sans code d'organisation. */
final class Authentification
{
    private const ESSAIS_MAX = 5;
    private const FENETRE = 900; // 15 minutes

    public function __construct(
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly DepotProfils $profils,
        private readonly DepotOrganisations $organisations,
        private readonly Audit $audit,
    ) {
    }

    /** @return int identifiant de l'utilisateur connecte */
    public function connecter(string $identifiant, string $motDePasse, string $ip): int
    {
        $identifiant = Texte::identifiant($identifiant);
        $cle = hash('sha256', $ip . '|' . $identifiant);
        $maintenant = time();

        $t = $this->utilisateurs->tentative($cle);
        if ($t !== null && (int) $t['bloque_jusqua'] > $maintenant) {
            throw new ErreurMetier('connexion.trop_de_tentatives');
        }

        $u = $this->utilisateurs->parIdentifiant($identifiant);
        // On verifie toujours un hachage, meme si le compte n'existe pas,
        // pour que le temps de reponse ne revele pas quels comptes existent.
        $hachage = $u['mot_de_passe'] ?? '$2y$10$qdplnI0IrQsBcc6moZWM7.ViO/n1JJzBhkYy5ukXMDBswc3QSpJkC';
        $correct = password_verify($motDePasse, $hachage);

        if ($u === null || !$correct || $u['statut'] !== 'ACTIF') {
            $nombre = 1;
            $debut = $maintenant;
            if ($t !== null && (int) $t['debut_fenetre'] >= $maintenant - self::FENETRE) {
                $nombre = (int) $t['nombre'] + 1;
                $debut = (int) $t['debut_fenetre'];
            }
            $this->utilisateurs->enregistrerTentative($cle, $nombre, $debut, $nombre >= self::ESSAIS_MAX ? $maintenant + self::FENETRE : 0);
            throw new ErreurMetier('connexion.incorrecte');
        }

        $this->utilisateurs->effacerTentatives($cle);
        if (password_needs_rehash($u['mot_de_passe'], PASSWORD_DEFAULT)) {
            $this->utilisateurs->modifier((int) $u['organisation_id'], (int) $u['id'], ['mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT)]);
        }
        $this->utilisateurs->modifier((int) $u['organisation_id'], (int) $u['id'], ['derniere_connexion' => date('Y-m-d H:i:s')]);
        $this->audit->noter((int) $u['organisation_id'], (int) $u['id'], 'connexion', 'utilisateur', (int) $u['id']);
        return (int) $u['id'];
    }

    /** Recharge l'utilisateur et ses droits ; null si le compte n'est plus utilisable. */
    public function contexte(int $organisationId, int $utilisateurId): ?Contexte
    {
        $u = $this->utilisateurs->parId($organisationId, $utilisateurId);
        if ($u === null || $u['statut'] !== 'ACTIF') {
            return null;
        }
        $organisation = $this->organisations->parId($organisationId);
        $profil = $this->profils->parId($organisationId, (int) $u['role_id']);
        if ($organisation === null || $profil === null) {
            return null;
        }
        $u['a_un_pin'] = !empty($u['pin']);
        unset($u['mot_de_passe'], $u['pin']);

        $droits = ResolveurDroits::resoudre(
            (int) $profil['est_admin'] === 1,
            $this->profils->droits((int) $profil['id']),
            $this->utilisateurs->reglesPersonnelles($utilisateurId),
        );
        $abonnement = $this->organisations->dernierAbonnement($organisationId);
        $etat = EtatAbonnement::calculer(
            $abonnement,
            (string) $organisation['statut'],
            date('Y-m-d'),
            (int) $this->organisations->parametrePlateforme('abonnement.jours_rappel', '7'),
            (int) $this->organisations->parametrePlateforme('abonnement.jours_grace', '5'),
        );
        return new Contexte($u, $organisation, $profil, $droits, $etat, $abonnement);
    }
}

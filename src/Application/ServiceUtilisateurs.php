<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotProfils;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Comptes du personnel : creation, profil, regles personnelles, corbeille. */
final class ServiceUtilisateurs
{
    public const MOT_DE_PASSE_MIN = 8;

    /** Identifiant et mot de passe provisoire du compte qui vient d'etre cree, a montrer une seule fois. */
    public ?array $acces = null;

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly DepotProfils $profils,
        private readonly Audit $audit,
    ) {
    }

    /**
     * @param array{nom:string,identifiant:string,role_id:int,statut:string,langue:string,mot_de_passe:string} $d
     * @param array<string,string> $regles code du droit => ACCORDE | REFUSE | '' (suit le profil)
     */
    public function enregistrer(Contexte $c, int $id, array $d, array $regles): int
    {
        $org = $c->organisationId();
        $nom = Texte::nom($d['nom']);
        $identifiant = Texte::identifiant($d['identifiant']);
        if ($nom === '') {
            throw new ErreurMetier('utilisateurs.nom_obligatoire');
        }
        $this->acces = null;
        if ($id === 0 && $identifiant === '') {
            $identifiant = $this->identifiantLibre($c, $nom);   // marie@tuungane
        }
        if ($id === 0 && $d['mot_de_passe'] === '') {
            $d['mot_de_passe'] = Texte::motDePasseProvisoire();
        }
        if (!Texte::estIdentifiantValide($identifiant)) {
            throw new ErreurMetier('utilisateurs.identifiant_invalide');
        }
        if ($this->utilisateurs->identifiantPris($identifiant, $id)) {
            throw new ErreurMetier('utilisateurs.identifiant_pris');
        }
        $profil = $this->profils->parId($org, (int) $d['role_id']) ?? throw new ErreurMetier('profils.introuvable');
        $statut = $d['statut'] === 'INACTIF' ? 'INACTIF' : 'ACTIF';
        $langue = in_array($d['langue'], ['fr', 'en'], true) ? $d['langue'] : null;

        $ids = $this->profils->idsDesDroits();
        $reglesParId = [];
        foreach ($regles as $code => $effet) {
            if (isset($ids[$code]) && in_array($effet, ['ACCORDE', 'REFUSE'], true) && in_array($code, CatalogueDroits::codes(), true)) {
                $reglesParId[$ids[$code]] = $effet;
            }
        }

        return $this->bd->transaction(function () use ($c, $org, $id, $d, $nom, $identifiant, $profil, $statut, $langue, $reglesParId, $regles): int {
            $ligne = [
                'nom' => $nom, 'identifiant' => $identifiant, 'role_id' => (int) $profil['id'],
                'statut' => $statut, 'langue' => $langue,
                'email' => str_contains($identifiant, '@') && !Texte::estIdentifiantLocal($identifiant) ? $identifiant : null,
                'telephone' => str_contains($identifiant, '@') ? null : $identifiant,
            ];
            if (array_key_exists('agence_id', $d)) {
                $agence = (int) $d['agence_id'];
                if ($agence > 0 && $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agences WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $agence]) == 0) {
                    throw new ErreurMetier('agences.introuvable');
                }
                $ligne['agence_id'] = $c->agenceRestreinte() ?? ($agence > 0 ? $agence : null);
            } elseif ($c->agenceRestreinte() !== null && $id === 0) {
                $ligne['agence_id'] = $c->agenceRestreinte();
            }
            if ($id === 0) {
                $this->verifierLimiteForfait($c);
                $this->exigerMotDePasse($d['mot_de_passe']);
                $ligne += [
                    'organisation_id' => $org,
                    'mot_de_passe' => password_hash($d['mot_de_passe'], PASSWORD_DEFAULT),
                    'doit_changer_mot_de_passe' => 1,
                    'cree_le' => $this->bd->maintenant(),
                ];
                $id = $this->utilisateurs->creer($ligne);
                $this->acces = ['nom' => $nom, 'identifiant' => $identifiant, 'mot_de_passe' => $d['mot_de_passe']];
                $this->utilisateurs->definirRegles($id, $reglesParId, $c->utilisateurId());
                $this->audit->noter($org, $c->utilisateurId(), 'utilisateur.creer', 'utilisateur', $id, null, $ligne + ['regles' => array_filter($regles)]);
                return $id;
            }

            $avant = $this->utilisateurs->parId($org, $id) ?? throw new ErreurMetier('utilisateurs.introuvable');
            $perdAdmin = (int) $profil['est_admin'] !== 1 || $statut !== 'ACTIF';
            if ($perdAdmin && $this->estAdmin($org, $avant) && $this->utilisateurs->compterAdminsActifs($org, $id) === 0) {
                throw new ErreurMetier('utilisateurs.dernier_admin');
            }
            if ($d['mot_de_passe'] !== '') {
                $this->exigerMotDePasse($d['mot_de_passe']);
                $ligne['mot_de_passe'] = password_hash($d['mot_de_passe'], PASSWORD_DEFAULT);
                $ligne['doit_changer_mot_de_passe'] = $id === $c->utilisateurId() ? 0 : 1;
            }
            $this->utilisateurs->modifier($org, $id, $ligne);
            $this->utilisateurs->definirRegles($id, $reglesParId, $c->utilisateurId());
            $this->audit->noter($org, $c->utilisateurId(), 'utilisateur.modifier', 'utilisateur', $id, $avant, $ligne + ['regles' => array_filter($regles)]);
            return $id;
        });
    }

    /** Identifiant genere : prenom@nomcourt, avec un chiffre si le prenom est deja pris dans l'organisation. */
    public function identifiantLibre(Contexte $c, string $nom): string
    {
        $court = (string) ($c->organisation['nom_court'] ?? '');
        if ($court === '') {
            throw new ErreurMetier('utilisateurs.nom_court_requis');
        }
        $prenom = Texte::prenomPourIdentifiant($nom) ?: 'compte';
        $identifiant = $prenom . '@' . $court;
        for ($n = 2; $this->utilisateurs->identifiantPris($identifiant); $n++) {
            $identifiant = $prenom . $n . '@' . $court;
        }
        return $identifiant;
    }

    /** Envoie le compte a la corbeille : il ne peut plus se connecter, mais reste restaurable. */
    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        $org = $c->organisationId();
        $u = $this->utilisateurs->parId($org, $id) ?? throw new ErreurMetier('utilisateurs.introuvable');
        if ($id === $c->utilisateurId()) {
            throw new ErreurMetier('utilisateurs.pas_soi_meme');
        }
        if ($this->estAdmin($org, $u) && $this->utilisateurs->compterAdminsActifs($org, $id) === 0) {
            throw new ErreurMetier('utilisateurs.dernier_admin');
        }
        $this->bd->transaction(function () use ($c, $org, $id, $u, $motif): void {
            $this->utilisateurs->modifier($org, $id, [
                'supprime_le' => $this->bd->maintenant(),
                'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'utilisateur.supprimer', 'utilisateur', $id, $u, null, $motif ?: null);
        });
        return (string) $u['nom'];
    }

    public function restaurer(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $u = $this->utilisateurs->parId($org, $id, true) ?? throw new ErreurMetier('utilisateurs.introuvable');
        if ($u['supprime_le'] === null) {
            throw new ErreurMetier('utilisateurs.introuvable');
        }
        $this->verifierLimiteForfait($c);
        $this->bd->transaction(function () use ($c, $org, $id): void {
            $this->utilisateurs->modifier($org, $id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            $this->audit->noter($org, $c->utilisateurId(), 'utilisateur.restaurer', 'utilisateur', $id);
        });
        return (string) $u['nom'];
    }

    /** L'utilisateur connecte modifie son propre nom, sa langue et son mot de passe. */
    public function modifierMonCompte(Contexte $c, string $nom, string $langue, string $actuel, string $nouveau, string $confirmation): void
    {
        $org = $c->organisationId();
        $id = $c->utilisateurId();
        $u = $this->utilisateurs->parId($org, $id) ?? throw new ErreurMetier('utilisateurs.introuvable');
        $nom = Texte::nom($nom);
        if ($nom === '') {
            throw new ErreurMetier('utilisateurs.nom_obligatoire');
        }
        $ligne = ['nom' => $nom, 'langue' => in_array($langue, ['fr', 'en'], true) ? $langue : null];

        $doitChanger = (int) $u['doit_changer_mot_de_passe'] === 1;
        if ($nouveau !== '' || $doitChanger) {
            if (!password_verify($actuel, (string) $u['mot_de_passe'])) {
                throw new ErreurMetier('compte.mot_de_passe_actuel_incorrect');
            }
            $this->exigerMotDePasse($nouveau);
            if ($nouveau !== $confirmation) {
                throw new ErreurMetier('compte.confirmation_differente');
            }
            if (password_verify($nouveau, (string) $u['mot_de_passe'])) {
                throw new ErreurMetier('compte.mot_de_passe_identique');
            }
            $ligne['mot_de_passe'] = password_hash($nouveau, PASSWORD_DEFAULT);
            $ligne['doit_changer_mot_de_passe'] = 0;
        }
        $this->utilisateurs->modifier($org, $id, $ligne);
        $this->audit->noter($org, $id, 'compte.modifier', 'utilisateur', $id, null, ['nom' => $nom, 'mot_de_passe_change' => isset($ligne['mot_de_passe'])]);
    }

    private function estAdmin(int $organisationId, array $utilisateur): bool
    {
        $profil = $this->profils->parId($organisationId, (int) $utilisateur['role_id']);
        return $profil !== null && (int) $profil['est_admin'] === 1 && $utilisateur['statut'] === 'ACTIF';
    }

    private function exigerMotDePasse(string $motDePasse): void
    {
        if (mb_strlen($motDePasse) < self::MOT_DE_PASSE_MIN) {
            throw new ErreurMetier('utilisateurs.mot_de_passe_court', ['n' => self::MOT_DE_PASSE_MIN]);
        }
    }

    /** Le forfait fixe un nombre maximum de comptes (NULL = illimite). */
    private function verifierLimiteForfait(Contexte $c): void
    {
        $max = $c->forfait['max_utilisateurs'] ?? null;
        if ($max !== null && $this->utilisateurs->compterActifs($c->organisationId()) >= (int) $max) {
            throw new ErreurMetier('utilisateurs.limite_forfait', ['n' => (int) $max]);
        }
    }
}

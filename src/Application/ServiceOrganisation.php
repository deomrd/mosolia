<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotParametres;
use AvecXY\Infrastructure\DepotProfils;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Ouverture d'une organisation et reglage de ses informations. */
final class ServiceOrganisation
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotOrganisations $organisations,
        private readonly DepotProfils $profils,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly Audit $audit,
        private readonly ?DepotParametres $parametres = null,
    ) {
    }

    /**
     * Cree l'organisation, ses profils modeles, son premier administrateur
     * et son abonnement de depart. Utilise par bin/creer-organisation.php
     * (et plus tard par l'inscription en ligne).
     *
     * @return array{organisation_id:int,utilisateur_id:int}
     */
    public function ouvrir(string $nom, string $devise, string $adminNom, string $adminIdentifiant, string $adminMotDePasse, string $planCode, string $langue = 'fr', string $nomCourt = ''): array
    {
        $nom = Texte::nom($nom);
        $nomCourt = strtolower(trim($nomCourt));
        if ($nomCourt !== '' && (Texte::nomCourt($nomCourt) !== $nomCourt || strlen($nomCourt) < 2)) {
            throw new ErreurMetier('organisation.nom_court_invalide');
        }
        if ($nomCourt !== '' && $this->organisations->nomCourtPris($nomCourt)) {
            throw new ErreurMetier('organisation.nom_court_pris', ['nom' => $nomCourt]);
        }
        $adminIdentifiant = Texte::identifiant($adminIdentifiant);
        if ($nom === '') {
            throw new ErreurMetier('organisation.nom_obligatoire');
        }
        if (!Texte::estIdentifiantValide($adminIdentifiant)) {
            throw new ErreurMetier('utilisateurs.identifiant_invalide');
        }
        if ($this->utilisateurs->identifiantPris($adminIdentifiant)) {
            throw new ErreurMetier('utilisateurs.identifiant_pris');
        }
        if (mb_strlen($adminMotDePasse) < ServiceUtilisateurs::MOT_DE_PASSE_MIN) {
            throw new ErreurMetier('utilisateurs.mot_de_passe_court', ['n' => ServiceUtilisateurs::MOT_DE_PASSE_MIN]);
        }
        $plan = $this->organisations->plan($planCode) ?? throw new ErreurMetier('organisation.forfait_introuvable');
        if (!in_array($devise, array_column($this->organisations->devises(), 'code'), true)) {
            throw new ErreurMetier('organisation.devise_inconnue');
        }

        return $this->bd->transaction(function () use ($nom, $devise, $adminNom, $adminIdentifiant, $adminMotDePasse, $plan, $langue, $nomCourt): array {
            $maintenant = $this->bd->maintenant();
            $essai = (float) $plan['prix'] <= 0;
            $orgId = $this->organisations->creer([
                'code' => $this->codeLibre($nom),
                'nom' => $nom,
                'nom_court' => $nomCourt !== '' ? $nomCourt : null,
                'devise_code' => $devise,
                'langue' => $langue === 'en' ? 'en' : 'fr',
                'statut' => $essai ? 'ESSAI' : 'ACTIVE',
                'cree_le' => $maintenant,
            ]);

            $adminRoleId = 0;
            foreach (CatalogueDroits::PROFILS_MODELES as $code => $modele) {
                $roleId = $this->profils->creer([
                    'organisation_id' => $orgId, 'code' => $code, 'nom' => $modele['nom'],
                    'est_admin' => $modele['est_admin'], 'est_agent' => $modele['est_agent'],
                    'systeme' => $modele['est_admin'],
                ]);
                $this->profils->definirDroits($roleId, $modele['droits']);
                if ($modele['est_admin'] === 1) {
                    $adminRoleId = $roleId;
                }
            }

            $userId = $this->utilisateurs->creer([
                'organisation_id' => $orgId,
                'role_id' => $adminRoleId,
                'nom' => Texte::nom($adminNom),
                'identifiant' => $adminIdentifiant,
                'email' => str_contains($adminIdentifiant, '@') ? $adminIdentifiant : null,
                'telephone' => str_contains($adminIdentifiant, '@') ? null : $adminIdentifiant,
                'mot_de_passe' => password_hash($adminMotDePasse, PASSWORD_DEFAULT),
                'doit_changer_mot_de_passe' => 1,
                'statut' => 'ACTIF',
                'cree_le' => $maintenant,
            ]);

            $this->organisations->creerAbonnement([
                'organisation_id' => $orgId,
                'plan_id' => (int) $plan['id'],
                'statut' => $essai ? 'ESSAI' : 'ACTIF',
                'debut' => date('Y-m-d'),
                'fin' => date('Y-m-d', strtotime('+' . (int) $plan['duree_jours'] . ' days')),
                'cree_le' => $maintenant,
            ]);
            // regle de depart : le recolteur gagne la moitie des benefices de ses propres clients,
            // l'organisation garde l'autre moitie. L'administrateur la change dans Partage des benefices.
            foreach (ServiceCaisse::SOURCES as $source) {
                $this->bd->inserer('avecxy_regles_partage', [
                    'organisation_id' => $orgId, 'source' => $source, 'pourcentage_agent' => '50.00', 'pourcentage_organisation' => '50.00',
                    'applicable_du' => '2000-01-01', 'cree_le' => $maintenant,
                ]);
            }
            $this->audit->noter($orgId, null, 'organisation.ouvrir', 'organisation', $orgId, null, ['nom' => $nom, 'forfait' => $plan['code']]);
            return ['organisation_id' => $orgId, 'utilisateur_id' => $userId];
        });
    }

    /**
     * Fixe le nom court de l'organisation (12 caracteres au plus, unique sur la plateforme).
     * Les identifiants deja crees avec l'ancien nom court restent valables.
     */
    public function definirNomCourt(int $org, string $valeur): string
    {
        $court = Texte::nomCourt($valeur);
        if (strlen($court) < 2 || $court !== strtolower(trim($valeur))) {
            throw new ErreurMetier('organisation.nom_court_invalide');
        }
        if ($this->organisations->nomCourtPris($court, $org)) {
            throw new ErreurMetier('organisation.nom_court_pris', ['nom' => $court]);
        }
        $this->organisations->modifier($org, ['nom_court' => $court]);
        return $court;
    }

    /** Nom court libre propose pour cette organisation. */
    public function nomCourtLibre(string $nomOrganisation, int $org = 0): string
    {
        $base = Texte::nomCourtPropose($nomOrganisation) ?: 'org';
        $court = $base;
        for ($n = 2; strlen($court) < 2 || $this->organisations->nomCourtPris($court, $org); $n++) {
            $court = substr($base, 0, 12 - strlen((string) $n)) . $n;
        }
        return $court;
    }

    public function modifierInfos(Contexte $c, array $d): void
    {
        $nom = Texte::nom((string) ($d['nom'] ?? ''));
        if ($nom === '') {
            throw new ErreurMetier('organisation.nom_obligatoire');
        }
        $couleur = (string) ($d['couleur_principale'] ?? '');
        $fuseau = (string) ($d['fuseau'] ?? '');
        $ligne = [
            'nom' => $nom,
            'telephone' => self::ouNull($d['telephone'] ?? ''),
            'email' => self::ouNull(mb_strtolower((string) ($d['email'] ?? ''))),
            'adresse' => self::ouNull($d['adresse'] ?? ''),
            'ville_defaut' => self::ouNull(Texte::nom((string) ($d['ville_defaut'] ?? ''))),
            'langue' => ($d['langue'] ?? 'fr') === 'en' ? 'en' : 'fr',
            'fuseau' => in_array($fuseau, timezone_identifiers_list(), true) ? $fuseau : $c->organisation['fuseau'],
            'couleur_principale' => preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur) ? strtoupper($couleur) : null,
        ];
        if ($ligne['email'] !== null && filter_var($ligne['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ErreurMetier('organisation.email_invalide');
        }
        $avant = array_intersect_key($c->organisation, $ligne);
        $nomCourt = strtolower(trim((string) ($d['nom_court'] ?? '')));
        $this->bd->transaction(function () use ($c, $ligne, $avant, $nomCourt): void {
            if ($nomCourt !== '' && $nomCourt !== (string) ($c->organisation['nom_court'] ?? '')) {
                $this->definirNomCourt($c->organisationId(), $nomCourt);
            }
            $this->organisations->modifier($c->organisationId(), $ligne);
            $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'organisation.modifier', 'organisation', $c->organisationId(), $avant, $ligne);
        });
    }

    /** Regle des noms de clients identiques : ou le controle s'applique, et quoi faire. */
    public function reglerDoublons(Contexte $c, string $portee, string $action): void
    {
        if (!in_array($portee, ServiceClients::PORTEES, true) || !in_array($action, ServiceClients::ACTIONS, true)) {
            throw new ErreurMetier('organisation.regle_invalide');
        }
        $org = $c->organisationId();
        $this->parametres->ecrire($org, 'clients.doublon_portee', $portee, $c->utilisateurId());
        $this->parametres->ecrire($org, 'clients.doublon_action', $action, $c->utilisateurId());
        $this->audit->noter($org, $c->utilisateurId(), 'organisation.regle_doublons', 'organisation', $org, null, ['portee' => $portee, 'action' => $action]);
    }

    private static function ouNull(mixed $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    /** Code interne court et unique, derive du nom (sert de reference au support). */
    private function codeLibre(string $nom): string
    {
        $base = substr(preg_replace('/[^A-Z0-9]/', '', $nom) ?: 'ORG', 0, 12);
        $code = $base;
        for ($n = 2; $this->organisations->codePris($code); $n++) {
            $code = $base . $n;
        }
        return $code;
    }
}

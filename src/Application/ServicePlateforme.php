<?php
namespace AvecXY\Application;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotPlateforme;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Gestion de la plateforme par AvecXY : connexion, forfaits, activation des abonnements, suspension, support. */
final class ServicePlateforme
{
    public const MODULES = ['credit', 'espace_client'];
    private const ESSAIS_MAX = 5;
    private const FENETRE = 900;

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotPlateforme $plateforme,
        private readonly DepotOrganisations $organisations,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly ?ServiceNotifications $notifications = null,
    ) {
    }

    public function connecter(string $email, string $motDePasse, string $ip): int
    {
        $email = mb_strtolower(trim($email));
        $cle = hash('sha256', 'plateforme|' . $ip . '|' . $email);
        $maintenant = time();
        $t = $this->utilisateurs->tentative($cle);
        if ($t !== null && (int) $t['bloque_jusqua'] > $maintenant) {
            throw new ErreurMetier('connexion.trop_de_tentatives');
        }
        $a = $this->plateforme->adminParEmail($email);
        $correct = password_verify($motDePasse, $a['mot_de_passe'] ?? '$2y$10$qdplnI0IrQsBcc6moZWM7.ViO/n1JJzBhkYy5ukXMDBswc3QSpJkC');
        if ($a === null || !$correct || $a['statut'] !== 'ACTIF') {
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
        $this->plateforme->modifierAdmin((int) $a['id'], ['derniere_connexion' => $this->bd->maintenant()]);
        return (int) $a['id'];
    }

    public function creerAdmin(string $nom, string $email, string $motDePasse): int
    {
        $email = mb_strtolower(trim($email));
        if (trim($nom) === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ErreurMetier('plateforme.admin_invalide');
        }
        if (mb_strlen($motDePasse) < 10) {
            throw new ErreurMetier('utilisateurs.mot_de_passe_court', ['n' => 10]);
        }
        if ($this->plateforme->adminParEmail($email) !== null) {
            throw new ErreurMetier('utilisateurs.identifiant_pris');
        }
        return $this->plateforme->creerAdmin(trim($nom), $email, password_hash($motDePasse, PASSWORD_DEFAULT));
    }

    public function changerMotDePasse(int $adminId, string $actuel, string $nouveau): void
    {
        $a = $this->plateforme->admin($adminId);
        $complet = $a ? $this->plateforme->adminParEmail($a['email']) : null;
        if ($complet === null || !password_verify($actuel, $complet['mot_de_passe'])) {
            throw new ErreurMetier('compte.mot_de_passe_actuel_incorrect');
        }
        if (mb_strlen($nouveau) < 10) {
            throw new ErreurMetier('utilisateurs.mot_de_passe_court', ['n' => 10]);
        }
        $this->plateforme->modifierAdmin($adminId, ['mot_de_passe' => password_hash($nouveau, PASSWORD_DEFAULT)]);
    }

    // ---------------- forfaits ----------------

    public function enregistrerForfait(int $adminId, int $id, array $d): void
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) ($d['code'] ?? '')));
        $nom = trim((string) ($d['nom'] ?? ''));
        if ($code === '' || $nom === '') {
            throw new ErreurMetier('plateforme.forfait_incomplet');
        }
        if ($this->plateforme->codePlanPris($code, $id)) {
            throw new ErreurMetier('plateforme.code_pris');
        }
        if (!in_array($d['devise_code'] ?? '', array_column($this->organisations->devises(), 'code'), true)) {
            throw new ErreurMetier('organisation.devise_inconnue');
        }
        $entier = static function (mixed $v, bool $vide = true): ?int {
            $v = trim((string) $v);
            if ($v === '' && $vide) {
                return null;
            }
            if (!ctype_digit($v)) {
                throw new ErreurMetier('credits.nombre_invalide');
            }
            return (int) $v;
        };
        $modules = [];
        foreach (self::MODULES as $m) {
            $modules[$m] = in_array($m, (array) ($d['modules'] ?? []), true);
        }
        $duree = $entier($d['duree_jours'] ?? '', false);
        if ($duree < 1 || $duree > 3660) {
            throw new ErreurMetier('credits.duree_invalide');
        }
        $ligne = [
            'code' => $code, 'nom' => mb_substr($nom, 0, 80), 'description' => trim((string) ($d['description'] ?? '')) ?: null,
            'prix' => Montant::lire((string) ($d['prix'] ?? '0') === '' ? '0' : (string) $d['prix'], 2), 'devise_code' => $d['devise_code'], 'duree_jours' => $duree,
            'max_agents' => $entier($d['max_agents'] ?? ''), 'max_clients' => $entier($d['max_clients'] ?? ''), 'max_utilisateurs' => $entier($d['max_utilisateurs'] ?? ''),
            'modules' => json_encode($modules), 'ordre' => (int) $entier($d['ordre'] ?? '0', false), 'actif' => ($d['actif'] ?? '') === '1' ? 1 : 0,
        ];
        $id = $this->plateforme->enregistrerPlan($id, $ligne);
        $this->plateforme->noter($adminId, null, 'plateforme.forfait', 'plan', $id, $ligne);
    }

    // ---------------- abonnements ----------------

    /**
     * Active ou renouvelle l'abonnement d'une organisation apres reception du paiement.
     * Meme forfait encore en cours : la nouvelle periode commence a la fin de l'actuelle. Sinon elle commence aujourd'hui.
     * @param int $paiementId demande de l'organisation a confirmer (0 = paiement saisi directement)
     */
    public function activer(int $adminId, int $org, int $planId, string $montant, string $moyen, string $note, int $paiementId = 0): string
    {
        return $this->bd->transaction(function () use ($adminId, $org, $planId, $montant, $moyen, $note, $paiementId): string {
            $o = $this->organisations->parId($org) ?? throw new ErreurMetier('plateforme.organisation_introuvable');
            $plan = $this->organisations->planParId($planId) ?? throw new ErreurMetier('organisation.forfait_introuvable');
            $m = Montant::lire($montant === '' ? (string) $plan['prix'] : $montant, 2);
            $demande = null;
            if ($paiementId > 0) {
                $demande = $this->plateforme->paiement($paiementId);
                if ($demande === null || (int) $demande['organisation_id'] !== $org || $demande['statut'] !== 'EN_ATTENTE') {
                    throw new ErreurMetier('plateforme.demande_introuvable');
                }
            }
            $actuel = $this->organisations->dernierAbonnement($org);
            $aujourdhui = date('Y-m-d');
            $prolonge = $actuel !== null && $actuel['statut'] === 'ACTIF' && (int) $actuel['plan_id'] === $planId && $actuel['fin'] >= $aujourdhui;
            $debut = $prolonge ? Dates::ajouterJours((string) $actuel['fin'], 1) : $aujourdhui;
            $fin = Dates::ajouterJours($debut, (int) $plan['duree_jours']);
            $aboId = $this->organisations->creerAbonnement([
                'organisation_id' => $org, 'plan_id' => $planId, 'statut' => 'ACTIF', 'debut' => $debut, 'fin' => $fin, 'cree_le' => $this->bd->maintenant(),
            ]);
            $paiement = [
                'abonnement_id' => $aboId, 'plan_id' => $planId, 'montant' => $m, 'devise_code' => $plan['devise_code'], 'mode' => 'MANUEL',
                'moyen' => trim($moyen) !== '' ? mb_substr(trim($moyen), 0, 40) : ($demande['moyen'] ?? null), 'statut' => 'CONFIRME',
                'paye_le' => $this->bd->maintenant(), 'enregistre_par' => $adminId,
                'note' => trim(($demande['note'] ?? '') . "\n" . trim($note)) ?: null,
            ];
            if ($demande !== null) {
                $this->plateforme->modifierPaiement($paiementId, $paiement);
            } else {
                $this->plateforme->creerPaiement($paiement + ['organisation_id' => $org, 'cree_le' => $this->bd->maintenant()]);
            }
            $this->organisations->modifier($org, ['statut' => 'ACTIVE']);
            $this->plateforme->noter($adminId, $org, 'plateforme.activer', 'abonnement', $aboId, ['forfait' => $plan['code'], 'debut' => $debut, 'fin' => $fin, 'montant' => $m]);
            if ($this->notifications !== null) {
                $d = ['organisation' => (string) $o['nom'], 'forfait' => (string) $plan['nom'], 'fin' => date('d/m/Y', strtotime($fin))];
                $this->notifications->utilisateurs($org, $this->notifications->comptesAyant($org, ['abonnement.gerer']), 'abonnement_active', $d, '/abonnement');
                $this->notifications->plateforme($org, 'abonnement_active_plateforme', $d, '/plateforme/organisations/' . $org);
            }
            return $fin;
        });
    }

    /** Donne un mot de passe provisoire a un compte d'une organisation (il devra le changer). @return array{nom:string,identifiant:string,mot_de_passe:string} */
    public function reinitialiserMotDePasse(int $adminId, int $org, int $utilisateurId): array
    {
        $u = $this->utilisateurs->parId($org, $utilisateurId) ?? throw new ErreurMetier('utilisateurs.introuvable');
        $provisoire = \AvecXY\Domaine\Texte::motDePasseProvisoire();
        $this->utilisateurs->modifier($org, $utilisateurId, ['mot_de_passe' => password_hash($provisoire, PASSWORD_DEFAULT), 'doit_changer_mot_de_passe' => 1]);
        $this->plateforme->noter($adminId, $org, 'plateforme.reinitialiser_mot_de_passe', 'utilisateur', $utilisateurId, ['identifiant' => $u['identifiant']]);
        $this->notifications?->utilisateurs($org, [$utilisateurId], 'mot_de_passe_reinitialise', [], '/mon-compte');
        return ['nom' => (string) $u['nom'], 'identifiant' => (string) $u['identifiant'], 'mot_de_passe' => $provisoire];
    }

    public function refuserDemande(int $adminId, int $org, int $paiementId, string $note): void
    {
        $this->bd->transaction(function () use ($adminId, $org, $paiementId, $note): void {
            $demande = $this->plateforme->paiement($paiementId);
            if ($demande === null || (int) $demande['organisation_id'] !== $org || $demande['statut'] !== 'EN_ATTENTE') {
                throw new ErreurMetier('plateforme.demande_introuvable');
            }
            $this->plateforme->modifierPaiement($paiementId, ['statut' => 'ECHEC', 'enregistre_par' => $adminId, 'note' => trim($demande['note'] . "\n" . trim($note)) ?: null]);
            $this->plateforme->noter($adminId, $org, 'plateforme.refuser_demande', 'abonnement_paiement', $paiementId, null, $note ?: null);
            $this->notifications?->utilisateurs($org, $this->notifications->comptesAyant($org, ['abonnement.gerer']), 'abonnement_refuse', ['motif' => trim($note) !== '' ? trim($note) : '—'], '/abonnement');
        });
    }

    public function suspendre(int $adminId, int $org, bool $suspendre, string $motif): void
    {
        $o = $this->organisations->parId($org) ?? throw new ErreurMetier('plateforme.organisation_introuvable');
        if ($suspendre && mb_strlen(trim($motif)) < 5) {
            throw new ErreurMetier('plateforme.motif_obligatoire');
        }
        $this->organisations->modifier($org, ['statut' => $suspendre ? 'SUSPENDUE' : 'ACTIVE']);
        $this->plateforme->noter($adminId, $org, $suspendre ? 'plateforme.suspendre' : 'plateforme.reactiver', 'organisation', $org, ['avant' => $o['statut']], trim($motif) ?: null);
    }

    public function reglages(int $adminId, string $rappel, string $grace): void
    {
        foreach (['abonnement.jours_rappel' => $rappel, 'abonnement.jours_grace' => $grace] as $cle => $v) {
            if (!ctype_digit($v) || (int) $v > 90) {
                throw new ErreurMetier('credits.nombre_invalide');
            }
            $this->plateforme->ecrireParametre($cle, $v);
        }
        $this->plateforme->noter($adminId, null, 'plateforme.reglages', 'parametres', null, ['rappel' => $rappel, 'grace' => $grace]);
    }

    /**
     * Adresse officielle du site pour l'application Mosolia : les telephones l'adoptent a la synchronisation suivante
     * (changement de domaine sans reinstaller l'application). Vide = les telephones gardent celle qu'ils ont.
     */
    public function adresseMobile(int $adminId, string $adresse): void
    {
        $adresse = rtrim(trim($adresse), '/');
        if ($adresse !== '' && !preg_match('#^https://[a-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~/-]*)?$#i', $adresse)) {
            throw new ErreurMetier('plateforme.adresse_mobile_invalide');
        }
        $this->plateforme->ecrireParametre('mobile.adresse', $adresse);
        $this->plateforme->noter($adminId, null, 'plateforme.adresse_mobile', 'parametres', null, ['adresse' => $adresse]);
    }

    /** Coordonnees affichees sur la page d'accueil publique. */
    public function contact(int $adminId, string $email, string $telephone, string $whatsapp): void
    {
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ErreurMetier('organisation.email_invalide');
        }
        foreach (['contact.email' => $email, 'contact.telephone' => $telephone, 'contact.whatsapp' => $whatsapp] as $cle => $v) {
            $this->plateforme->ecrireParametre($cle, mb_substr(trim($v), 0, 160), 'TEXTE');
        }
        $this->plateforme->noter($adminId, null, 'plateforme.contact', 'parametres', null);
    }

    public function repondre(int $adminId, int $ticketId, string $message, bool $resolu): void
    {
        $t = $this->plateforme->ticket($ticketId) ?? throw new ErreurMetier('support.introuvable');
        $message = trim($message);
        if ($message === '' && !$resolu) {
            throw new ErreurMetier('support.message_obligatoire');
        }
        if ($message !== '') {
            $this->plateforme->ajouterMessage($ticketId, null, $adminId, mb_substr($message, 0, 5000), $resolu ? 'RESOLU' : 'EN_ATTENTE_CLIENT');
        } else {
            $this->plateforme->changerStatutTicket($ticketId, 'RESOLU');
        }
    }
}

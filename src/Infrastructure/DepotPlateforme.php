<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Donnees de la plateforme : administrateurs AvecXY, forfaits, abonnements, demandes de paiement, support. */
final class DepotPlateforme
{
    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    // ---------------- administrateurs ----------------

    public function adminParEmail(string $email): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_plateforme_admins WHERE email = ?', [$email]);
    }

    public function admin(int $id): ?array
    {
        return $this->bd->un("SELECT id, nom, email FROM avecxy_plateforme_admins WHERE id = ? AND statut = 'ACTIF'", [$id]);
    }

    public function creerAdmin(string $nom, string $email, string $hachage): int
    {
        return $this->bd->inserer('avecxy_plateforme_admins', ['nom' => $nom, 'email' => $email, 'mot_de_passe' => $hachage, 'statut' => 'ACTIF', 'cree_le' => $this->bd->maintenant()]);
    }

    public function modifierAdmin(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_plateforme_admins', $d, ['id' => $id]);
    }

    public function noter(int $adminId, ?int $org, string $action, string $objet, ?int $objetId, ?array $apres = null, ?string $motif = null): void
    {
        $this->bd->inserer('avecxy_journal_audit', [
            'organisation_id' => $org, 'plateforme_admin_id' => $adminId, 'action' => $action, 'objet_type' => $objet, 'objet_id' => $objetId,
            'apres' => $apres === null ? null : json_encode($apres, JSON_UNESCAPED_UNICODE), 'motif' => $motif, 'cree_le' => $this->bd->maintenant(),
        ]);
    }

    // ---------------- organisations ----------------

    public function organisations(): array
    {
        return $this->bd->tous(
            "SELECT o.*,
                (SELECT COUNT(*) FROM avecxy_utilisateurs u WHERE u.organisation_id = o.id AND u.supprime_le IS NULL) AS utilisateurs,
                (SELECT COUNT(*) FROM avecxy_clients c WHERE c.organisation_id = o.id AND c.supprime_le IS NULL) AS clients,
                (SELECT COUNT(*) FROM avecxy_abonnement_paiements p WHERE p.organisation_id = o.id AND p.statut = 'EN_ATTENTE') AS demandes
             FROM avecxy_organisations o ORDER BY o.nom"
        );
    }

    public function abonnements(int $org): array
    {
        return $this->bd->tous(
            'SELECT a.*, p.nom AS plan_nom FROM avecxy_abonnements a JOIN avecxy_plans p ON p.id = a.plan_id WHERE a.organisation_id = ? ORDER BY a.id DESC LIMIT 50',
            [$org]
        );
    }

    public function usage(int $org): array
    {
        return $this->bd->un(
            'SELECT (SELECT COUNT(*) FROM avecxy_utilisateurs WHERE organisation_id = ? AND supprime_le IS NULL) AS utilisateurs,
                    (SELECT COUNT(*) FROM avecxy_agents WHERE organisation_id = ? AND supprime_le IS NULL) AS agents,
                    (SELECT COUNT(*) FROM avecxy_clients WHERE organisation_id = ? AND supprime_le IS NULL) AS clients',
            [$org, $org, $org]
        );
    }

    /** Activite d'une organisation : ce qu'elle gere aujourd'hui. */
    public function activite(int $org): array
    {
        return $this->bd->un(
            "SELECT (SELECT COUNT(*) FROM avecxy_carnets WHERE organisation_id = ? AND supprime_le IS NULL AND statut = 'ACTIF') AS carnets,
                    (SELECT COALESCE(SUM(epargne),0) FROM avecxy_carnets WHERE organisation_id = ? AND supprime_le IS NULL AND statut = 'ACTIF') AS epargne,
                    (SELECT COUNT(*) FROM avecxy_credits WHERE organisation_id = ? AND supprime_le IS NULL AND statut IN ('EN_COURS','EN_RETARD')) AS credits,
                    (SELECT COALESCE(SUM(restant),0) FROM avecxy_credits WHERE organisation_id = ? AND supprime_le IS NULL AND statut IN ('EN_COURS','EN_RETARD')) AS encours,
                    (SELECT COUNT(*) FROM avecxy_credits WHERE organisation_id = ? AND supprime_le IS NULL AND statut = 'EN_RETARD') AS credits_en_retard,
                    (SELECT COUNT(*) FROM avecxy_client_acces x JOIN avecxy_clients c ON c.id = x.client_id WHERE c.organisation_id = ? AND x.actif = 1 AND c.supprime_le IS NULL) AS acces_clients,
                    (SELECT MAX(derniere_connexion) FROM avecxy_utilisateurs WHERE organisation_id = ?) AS dernier_acces,
                    (SELECT MAX(date_cotisation) FROM avecxy_cotisations WHERE organisation_id = ? AND supprime_le IS NULL) AS derniere_cotisation",
            array_fill(0, 8, $org)
        );
    }

    /** Comptes du personnel d'une organisation, avec leur profil. */
    public function utilisateurs(int $org): array
    {
        return $this->bd->tous(
            'SELECT u.id, u.nom, u.identifiant, u.statut, u.derniere_connexion, u.cree_le, u.pin, r.nom AS profil_nom, r.est_admin, a.code AS recolteur_code
             FROM avecxy_utilisateurs u JOIN avecxy_roles r ON r.id = u.role_id LEFT JOIN avecxy_agents a ON a.id = u.agent_id
             WHERE u.organisation_id = ? AND u.supprime_le IS NULL ORDER BY r.est_admin DESC, u.nom',
            [$org]
        );
    }

    /** Ce que les administrateurs de la plateforme ont fait sur cette organisation. */
    public function journal(int $org): array
    {
        return $this->bd->tous(
            'SELECT j.cree_le, j.action, j.motif, p.nom AS admin_nom FROM avecxy_journal_audit j LEFT JOIN avecxy_plateforme_admins p ON p.id = j.plateforme_admin_id
             WHERE j.organisation_id = ? AND j.plateforme_admin_id IS NOT NULL ORDER BY j.id DESC LIMIT 40',
            [$org]
        );
    }

    // ---------------- forfaits ----------------

    public function plans(bool $actifsSeulement = false): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_plans' . ($actifsSeulement ? ' WHERE actif = 1' : '') . ' ORDER BY ordre, prix, id');
    }

    public function enregistrerPlan(int $id, array $d): int
    {
        if ($id > 0) {
            $this->bd->modifier('avecxy_plans', $d, ['id' => $id]);
            return $id;
        }
        return $this->bd->inserer('avecxy_plans', $d + ['cree_le' => $this->bd->maintenant()]);
    }

    public function codePlanPris(string $code, int $saufId): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_plans WHERE code = ? AND id <> ?', [$code, $saufId]);
    }

    // ---------------- paiements d'abonnement ----------------

    public function paiements(int $org): array
    {
        return $this->bd->tous(
            'SELECT p.*, pl.nom AS plan_nom FROM avecxy_abonnement_paiements p LEFT JOIN avecxy_plans pl ON pl.id = p.plan_id WHERE p.organisation_id = ? ORDER BY p.id DESC LIMIT 50',
            [$org]
        );
    }

    public function paiement(int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_abonnement_paiements WHERE id = ?' . $this->bd->verrou(), [$id]);
    }

    public function demandeEnAttente(int $org): bool
    {
        return (bool) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_abonnement_paiements WHERE organisation_id = ? AND statut = 'EN_ATTENTE'", [$org]);
    }

    public function creerPaiement(array $d): int
    {
        return $this->bd->inserer('avecxy_abonnement_paiements', $d);
    }

    public function modifierPaiement(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_abonnement_paiements', $d, ['id' => $id]);
    }

    public function ecrireParametre(string $cle, string $valeur, string $type = 'ENTIER'): void
    {
        $this->bd->executer('DELETE FROM avecxy_plateforme_parametres WHERE cle = ?', [$cle]);
        $this->bd->inserer('avecxy_plateforme_parametres', ['cle' => $cle, 'valeur' => $valeur, 'type' => $type, 'modifie_le' => $this->bd->maintenant()]);
    }

    // ---------------- support ----------------

    public function tickets(?int $org, bool $ouvertsSeulement = false): array
    {
        $sql = 'SELECT t.*, o.nom AS organisation_nom, u.nom AS auteur_nom FROM avecxy_support_tickets t
                JOIN avecxy_organisations o ON o.id = t.organisation_id JOIN avecxy_utilisateurs u ON u.id = t.utilisateur_id WHERE 1 = 1';
        $p = [];
        if ($org !== null) {
            $sql .= ' AND t.organisation_id = ?';
            $p[] = $org;
        }
        if ($ouvertsSeulement) {
            $sql .= " AND t.statut IN ('OUVERT','EN_COURS','EN_ATTENTE_CLIENT')";
        }
        return $this->bd->tous($sql . ' ORDER BY t.dernier_message_le DESC, t.id DESC LIMIT 200', $p);
    }

    public function ticket(int $id, ?int $org = null): ?array
    {
        $sql = 'SELECT t.*, o.nom AS organisation_nom, u.nom AS auteur_nom FROM avecxy_support_tickets t
                JOIN avecxy_organisations o ON o.id = t.organisation_id JOIN avecxy_utilisateurs u ON u.id = t.utilisateur_id WHERE t.id = ?';
        $p = [$id];
        if ($org !== null) {
            $sql .= ' AND t.organisation_id = ?';
            $p[] = $org;
        }
        return $this->bd->un($sql, $p);
    }

    public function messages(int $ticketId): array
    {
        return $this->bd->tous(
            'SELECT m.*, u.nom AS utilisateur_nom, a.nom AS admin_nom FROM avecxy_support_messages m
             LEFT JOIN avecxy_utilisateurs u ON u.id = m.utilisateur_id LEFT JOIN avecxy_plateforme_admins a ON a.id = m.plateforme_admin_id
             WHERE m.ticket_id = ? ORDER BY m.id',
            [$ticketId]
        );
    }

    public function creerTicket(array $d): int
    {
        $d['numero'] = 1 + (int) $this->bd->valeur('SELECT COALESCE(MAX(numero),0) FROM avecxy_support_tickets WHERE organisation_id = ?', [$d['organisation_id']]);
        return $this->bd->inserer('avecxy_support_tickets', $d);
    }

    public function ajouterMessage(int $ticketId, ?int $utilisateurId, ?int $adminId, string $message, string $statut): void
    {
        $maintenant = $this->bd->maintenant();
        $this->bd->inserer('avecxy_support_messages', ['ticket_id' => $ticketId, 'utilisateur_id' => $utilisateurId, 'plateforme_admin_id' => $adminId, 'message' => $message, 'cree_le' => $maintenant]);
        $this->bd->modifier('avecxy_support_tickets', ['statut' => $statut, 'dernier_message_le' => $maintenant], ['id' => $ticketId]);
    }

    public function changerStatutTicket(int $ticketId, string $statut): void
    {
        $this->bd->modifier('avecxy_support_tickets', ['statut' => $statut], ['id' => $ticketId]);
    }

    /** Paiements d'abonnement confirmes ce mois, par devise. */
    public function encaisseDuMois(): array
    {
        return $this->bd->tous("SELECT devise_code, COALESCE(SUM(montant),0) AS total FROM avecxy_abonnement_paiements WHERE statut = 'CONFIRME' AND paye_le >= ? GROUP BY devise_code ORDER BY devise_code", [date('Y-m-01 00:00:00')]);
    }

    public function compterTicketsOuverts(): int
    {
        return (int) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_support_tickets WHERE statut IN ('OUVERT','EN_COURS')");
    }
}

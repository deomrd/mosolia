<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ResolveurDroits;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Notifications dans l'application : pour le personnel (cloche), pour chaque client dans son espace,
 * et pour les administrateurs de la plateforme. Le texte est traduit a l'affichage (cle "notif.<type>").
 * Une notification qui echoue ne doit jamais faire echouer l'operation : tout est protege.
 */
final class ServiceNotifications
{
    public const UTILISATEUR = 'UTILISATEUR';
    public const CLIENT = 'CLIENT';
    public const PLATEFORME = 'PLATEFORME';

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    private function creer(?int $org, string $destinataire, int $id, string $type, array $donnees, ?string $lien): void
    {
        try {
            $this->bd->inserer('avecxy_notifications', [
                'organisation_id' => $org, 'destinataire' => $destinataire, 'destinataire_id' => $id, 'type' => $type,
                'donnees' => json_encode($donnees, JSON_UNESCAPED_UNICODE), 'lien' => $lien, 'cree_le' => $this->bd->maintenant(),
            ]);
        } catch (\Throwable) {
            // table absente (base pas encore mise a jour) ou autre : l'operation continue
        }
    }

    /** @param int[] $utilisateurs */
    public function utilisateurs(int $org, array $utilisateurs, string $type, array $donnees = [], ?string $lien = null): void
    {
        foreach (array_unique(array_map('intval', $utilisateurs)) as $id) {
            if ($id > 0) {
                $this->creer($org, self::UTILISATEUR, $id, $type, $donnees, $lien);
            }
        }
    }

    /** Comptes du personnel rattaches a un recolteur. @return int[] */
    public function comptesDuRecolteur(int $org, int $agentId): array
    {
        try {
            return array_map('intval', array_column($this->bd->tous(
                "SELECT id FROM avecxy_utilisateurs WHERE organisation_id = ? AND agent_id = ? AND supprime_le IS NULL AND statut = 'ACTIF'", [$org, $agentId]
            ), 'id'));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Comptes actifs qui ont au moins un de ces droits (les administrateurs les ont tous). @return int[] */
    public function comptesAyant(int $org, array $droits): array
    {
        try {
            $ids = [];
            $comptes = $this->bd->tous(
                "SELECT u.id, u.role_id, r.est_admin FROM avecxy_utilisateurs u JOIN avecxy_roles r ON r.id = u.role_id
                 WHERE u.organisation_id = ? AND u.supprime_le IS NULL AND u.statut = 'ACTIF'", [$org]
            );
            foreach ($comptes as $u) {
                $duProfil = array_column($this->bd->tous(
                    'SELECT p.code FROM avecxy_role_permissions rp JOIN avecxy_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$u['role_id']]
                ), 'code');
                $perso = array_column($this->bd->tous(
                    'SELECT p.code, up.effet FROM avecxy_utilisateur_permissions up JOIN avecxy_permissions p ON p.id = up.permission_id WHERE up.utilisateur_id = ?', [$u['id']]
                ), 'effet', 'code');
                if (array_intersect($droits, ResolveurDroits::resoudre((int) $u['est_admin'] === 1, $duProfil, $perso))) {
                    $ids[] = (int) $u['id'];
                }
            }
            return $ids;
        } catch (\Throwable) {
            return [];
        }
    }

    public function client(int $org, int $clientId, string $type, array $donnees = [], ?string $lien = null): void
    {
        $this->creer($org, self::CLIENT, $clientId, $type, $donnees, $lien);
    }

    public function plateforme(?int $org, string $type, array $donnees = [], ?string $lien = null): void
    {
        $this->creer($org, self::PLATEFORME, 0, $type, $donnees, $lien);
    }

    // ---------------- lecture ----------------

    public function nonLues(string $destinataire, int $id): int
    {
        try {
            return (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_notifications WHERE destinataire = ? AND destinataire_id = ? AND lu_le IS NULL', [$destinataire, $id]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Les plus recentes d'abord, avec leur texte deja traduit. */
    public function liste(string $destinataire, int $id, int $limite = 100): array
    {
        try {
            $lignes = $this->bd->tous('SELECT * FROM avecxy_notifications WHERE destinataire = ? AND destinataire_id = ? ORDER BY id DESC LIMIT ' . (int) $limite, [$destinataire, $id]);
        } catch (\Throwable) {
            return [];
        }
        foreach ($lignes as &$l) {
            $l['texte'] = t('notif.' . $l['type'], json_decode((string) $l['donnees'], true) ?: []);
        }
        return $lignes;
    }

    public function toutLire(string $destinataire, int $id): void
    {
        try {
            $this->bd->executer('UPDATE avecxy_notifications SET lu_le = ? WHERE destinataire = ? AND destinataire_id = ? AND lu_le IS NULL', [$this->bd->maintenant(), $destinataire, $id]);
        } catch (\Throwable) {
        }
    }
}

<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Operations d'un carnet : cotisations et depots, retraits, frais, et les benefices qui en decoulent. */
final class DepotOperations
{
    private const COTISATION = 'SELECT o.*, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, a.nom AS recolteur_nom,
            k.numero AS carnet_numero, k.mode AS carnet_mode, k.statut AS carnet_statut, u.nom AS auteur_nom
        FROM avecxy_cotisations o
        JOIN avecxy_carnets k ON k.id = o.carnet_id
        JOIN avecxy_clients c ON c.id = o.client_id
        JOIN avecxy_agents a ON a.id = o.agent_id
        LEFT JOIN avecxy_utilisateurs u ON u.id = o.cree_par';

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    // ---------------- cotisations et depots ----------------

    public function cotisation(int $org, int $id, ?int $recolteur = null): ?array
    {
        $sql = self::COTISATION . ' WHERE o.organisation_id = ? AND o.id = ? AND o.supprime_le IS NULL';
        $p = [$org, $id];
        $sql .= Portee::sql('o.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND o.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->un($sql, $p);
    }

    public function cotisationsDuCarnet(int $carnetId): array
    {
        return $this->bd->tous(
            'SELECT o.*, u.nom AS auteur_nom FROM avecxy_cotisations o LEFT JOIN avecxy_utilisateurs u ON u.id = o.cree_par
             WHERE o.carnet_id = ? AND o.supprime_le IS NULL ORDER BY o.date_cotisation DESC, o.id DESC',
            [$carnetId]
        );
    }

    /** Cotisations validees, de la plus ancienne a la plus recente (ordre du calcul). */
    public function validees(int $carnetId): array
    {
        return $this->bd->tous(
            "SELECT id, nombre_parts, montant_total, date_cotisation FROM avecxy_cotisations
             WHERE carnet_id = ? AND statut = 'VALIDE' AND supprime_le IS NULL ORDER BY date_cotisation, id",
            [$carnetId]
        );
    }

    public function partsEnAttente(int $carnetId, int $saufId = 0): int
    {
        return (int) $this->bd->valeur(
            "SELECT COALESCE(SUM(nombre_parts),0) FROM avecxy_cotisations WHERE carnet_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL AND id <> ?",
            [$carnetId, $saufId]
        );
    }

    public function compterEnAttente(int $carnetId): int
    {
        return (int) $this->bd->valeur("SELECT COUNT(*) FROM avecxy_cotisations WHERE carnet_id = ? AND statut = 'EN_ATTENTE' AND supprime_le IS NULL", [$carnetId]);
    }

    /** @return array{lignes:array,total:int,somme:mixed} */
    public function cotisations(int $org, ?int $recolteur, array $f, int $page, int $parPage = 50): array
    {
        $ou = 'o.organisation_id = ? AND o.supprime_le IS NULL';
        $p = [$org];
        $ou .= Portee::sql('o.agent_id', $p);
        if ($recolteur !== null) {
            $ou .= ' AND o.agent_id = ?';
            $p[] = $recolteur;
        } elseif (($f['recolteur'] ?? 0) > 0) {
            $ou .= ' AND o.agent_id = ?';
            $p[] = (int) $f['recolteur'];
        }
        if (in_array($f['statut'] ?? '', ['EN_ATTENTE', 'VALIDE', 'REJETE'], true)) {
            $ou .= ' AND o.statut = ?';
            $p[] = $f['statut'];
        }
        if (($f['du'] ?? '') !== '') {
            $ou .= ' AND o.date_cotisation >= ?';
            $p[] = $f['du'];
        }
        if (($f['au'] ?? '') !== '') {
            $ou .= ' AND o.date_cotisation <= ?';
            $p[] = $f['au'];
        }
        if (($f['q'] ?? '') !== '') {
            $ou .= ' AND (c.nom LIKE ? OR c.code LIKE ?)';
            array_push($p, '%' . $f['q'] . '%', $f['q'] . '%');
        }
        $base = ' FROM avecxy_cotisations o JOIN avecxy_clients c ON c.id = o.client_id WHERE ' . $ou;
        $r = $this->bd->un('SELECT COUNT(*) AS n, COALESCE(SUM(o.montant_total),0) AS s' . $base, $p);
        $lignes = $this->bd->tous(self::COTISATION . " WHERE $ou ORDER BY o.date_cotisation DESC, o.id DESC LIMIT " . (int) $parPage . ' OFFSET ' . max(0, ($page - 1) * $parPage), $p);
        return ['lignes' => $lignes, 'total' => (int) $r['n'], 'somme' => $r['s']];
    }

    public function enAttente(int $org, ?int $recolteur): array
    {
        $sql = self::COTISATION . " WHERE o.organisation_id = ? AND o.statut = 'EN_ATTENTE' AND o.supprime_le IS NULL";
        $p = [$org];
        $sql .= Portee::sql('o.agent_id', $p);
        if ($recolteur !== null) {
            $sql .= ' AND o.agent_id = ?';
            $p[] = $recolteur;
        }
        return $this->bd->tous($sql . ' ORDER BY a.nom, o.date_cotisation, o.id', $p);
    }

    public function creerCotisation(array $d): int
    {
        return $this->bd->inserer('avecxy_cotisations', $d);
    }

    public function modifierCotisation(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_cotisations', $d, ['id' => $id]);
    }

    // ---------------- retraits et frais ----------------

    public function retraitsDuCarnet(int $carnetId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_retraits WHERE carnet_id = ? AND supprime_le IS NULL ORDER BY date_retrait DESC, id DESC', [$carnetId]);
    }

    /** @return array{lignes:array,total:int,somme:mixed} */
    public function retraits(int $org, ?int $recolteur, array $f, int $page, int $parPage = 50): array
    {
        $ou = 'r.organisation_id = ? AND r.supprime_le IS NULL';
        $p = [$org];
        $ou .= Portee::sql('r.agent_id', $p);
        if ($recolteur !== null) {
            $ou .= ' AND r.agent_id = ?';
            $p[] = $recolteur;
        } elseif (($f['recolteur'] ?? 0) > 0) {
            $ou .= ' AND r.agent_id = ?';
            $p[] = (int) $f['recolteur'];
        }
        if (($f['du'] ?? '') !== '') {
            $ou .= ' AND r.date_retrait >= ?';
            $p[] = $f['du'];
        }
        if (($f['au'] ?? '') !== '') {
            $ou .= ' AND r.date_retrait <= ?';
            $p[] = $f['au'];
        }
        if (($f['q'] ?? '') !== '') {
            $ou .= ' AND (c.nom LIKE ? OR c.code LIKE ?)';
            array_push($p, '%' . $f['q'] . '%', $f['q'] . '%');
        }
        $t = $this->bd->un("SELECT COUNT(*) AS n, COALESCE(SUM(r.montant_retire),0) AS s FROM avecxy_retraits r JOIN avecxy_clients c ON c.id = r.client_id WHERE $ou", $p);
        $lignes = $this->bd->tous(
            "SELECT r.*, c.code AS client_code, c.nom AS client_nom, a.code AS recolteur_code, k.numero AS carnet_numero
             FROM avecxy_retraits r JOIN avecxy_clients c ON c.id = r.client_id JOIN avecxy_agents a ON a.id = r.agent_id JOIN avecxy_carnets k ON k.id = r.carnet_id
             WHERE $ou ORDER BY r.date_retrait DESC, r.id DESC LIMIT " . (int) $parPage . ' OFFSET ' . max(0, ($page - 1) * $parPage),
            $p
        );
        return ['lignes' => $lignes, 'total' => (int) $t['n'], 'somme' => $t['s']];
    }

    public function retrait(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_retraits WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
    }

    /** @return array{retire:mixed,tranches:int} */
    public function totauxRetraits(int $carnetId): array
    {
        $r = $this->bd->un(
            "SELECT COALESCE(SUM(montant_retire),0) AS retire, COALESCE(SUM(CASE WHEN tranche IS NOT NULL THEN 1 ELSE 0 END),0) AS tranches
             FROM avecxy_retraits WHERE carnet_id = ? AND statut = 'VALIDE' AND supprime_le IS NULL",
            [$carnetId]
        );
        return ['retire' => $r['retire'], 'tranches' => (int) $r['tranches']];
    }

    public function creerRetrait(array $d): int
    {
        return $this->bd->inserer('avecxy_retraits', $d);
    }

    public function modifierRetrait(int $id, array $d): void
    {
        $this->bd->modifier('avecxy_retraits', $d, ['id' => $id]);
    }

    public function fraisDuCarnet(int $carnetId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_frais_tenue WHERE carnet_id = ? AND supprime_le IS NULL ORDER BY date_frais DESC, id DESC', [$carnetId]);
    }

    public function fraisDejaPreleves(int $carnetId, string $periode, string $origine): bool
    {
        return (bool) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_frais_tenue WHERE carnet_id = ? AND periode = ? AND origine = ? AND supprime_le IS NULL', [$carnetId, $periode, $origine]);
    }

    public function creerFrais(array $d): int
    {
        return $this->bd->inserer('avecxy_frais_tenue', $d);
    }

    public function supprimerFraisDuRetrait(int $retraitId, string $quand, int $par): void
    {
        $this->bd->executer('UPDATE avecxy_frais_tenue SET supprime_le = ?, supprime_par = ? WHERE retrait_id = ? AND supprime_le IS NULL', [$quand, $par, $retraitId]);
    }

    // ---------------- benefices ----------------

    public function effacerBeneficesDuCarnet(int $carnetId): void
    {
        $this->bd->executer('DELETE FROM avecxy_benefices WHERE carnet_id = ?', [$carnetId]);
    }

    public function creerBenefice(array $d): int
    {
        return $this->bd->inserer('avecxy_benefices', $d);
    }

    /** Regle de partage en vigueur a cette date pour cette source de benefice. */
    public function reglePartage(int $org, string $source, string $date): ?array
    {
        return $this->bd->un(
            'SELECT * FROM avecxy_regles_partage WHERE organisation_id = ? AND source = ? AND applicable_du <= ? ORDER BY applicable_du DESC, id DESC LIMIT 1',
            [$org, $source, $date]
        );
    }

    // ------------------------------------------------------------------
    // Interets verses sur les comptes bloques
    // ------------------------------------------------------------------

    public function interetsDuCarnet(int $carnetId): array
    {
        return $this->bd->tous('SELECT * FROM avecxy_interets_epargne WHERE carnet_id = ? AND supprime_le IS NULL ORDER BY date_interet, id', [$carnetId]);
    }

    public function interetDejaVerse(int $carnetId, string $periode): bool
    {
        return $this->bd->valeur('SELECT COUNT(*) FROM avecxy_interets_epargne WHERE carnet_id = ? AND periode = ? AND supprime_le IS NULL', [$carnetId, $periode]) > 0;
    }

    public function creerInteret(array $d): int
    {
        return $this->bd->inserer('avecxy_interets_epargne', $d);
    }

    /**
     * Variations du solde d'un carnet libre, jour par jour (centimes signes) :
     * depots valides, retraits, frais et penalites, interets deja verses, reprise du papier.
     * @return array<int,array{jour:string,montant:int}>
     */
    public function mouvementsEpargne(array $carnet): array
    {
        $id = (int) $carnet['id'];
        $liste = [];
        $ajouter = static function (array $lignes, int $signe) use (&$liste): void {
            foreach ($lignes as $l) {
                $liste[] = ['jour' => (string) $l['jour'], 'montant' => $signe * \AvecXY\Domaine\Montant::enCentimes($l['montant'])];
            }
        };
        $ajouter($this->bd->tous("SELECT date_cotisation AS jour, montant_epargne AS montant FROM avecxy_cotisations WHERE carnet_id = ? AND statut = 'VALIDE' AND supprime_le IS NULL", [$id]), 1);
        $ajouter($this->bd->tous("SELECT date_retrait AS jour, montant_retire AS montant FROM avecxy_retraits WHERE carnet_id = ? AND statut = 'VALIDE' AND supprime_le IS NULL", [$id]), -1);
        $ajouter($this->bd->tous('SELECT date_frais AS jour, montant FROM avecxy_frais_tenue WHERE carnet_id = ? AND supprime_le IS NULL', [$id]), -1);
        $ajouter($this->bd->tous('SELECT date_interet AS jour, montant FROM avecxy_interets_epargne WHERE carnet_id = ? AND supprime_le IS NULL', [$id]), 1);
        $reprise = \AvecXY\Domaine\Montant::enCentimes($carnet['reprise_montant'] ?? 0) - \AvecXY\Domaine\Montant::enCentimes($carnet['reprise_retire'] ?? 0);
        if ($reprise !== 0) {
            $liste[] = ['jour' => (string) $carnet['date_ouverture'], 'montant' => $reprise];
        }
        return $liste;
    }
}

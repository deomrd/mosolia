<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Chiffres du tableau de bord et rapports detailles. Lecture seule. */
final class DepotRapports
{
    public const MAX_LIGNES = 5000;

    /** rapport => droit supplementaire exige (null = rapports.voir suffit) */
    public const RAPPORTS = [
        'operations' => null, 'cotisations' => null, 'retraits' => null, 'carnets' => null, 'soldes' => null,
        'credits' => null, 'remboursements' => null, 'recolteurs' => null, 'manquants' => null, 'benefices' => 'montants.benefices', 'audit' => 'audit.voir',
    ];

    public function __construct(private readonly BaseDeDonnees $bd, private readonly DepotCaisse $caisse)
    {
    }

    /**
     * Chiffres du tableau de bord, pour toute l'organisation ou pour un seul recolteur.
     * Les stocks (clients, carnets, epargne, encours) sont ceux d'aujourd'hui ; les flux
     * (collecte, benefices, parts, manquants, paiements) sont ceux de la periode. Seul ce qui est valide compte.
     */
    public function tableau(int $org, ?int $agent, string $du, string $au): array
    {
        $periode = [$du, $au];
        // chaque chiffre : [table, expression, condition, parametres]
        $chiffres = [
            'clients' => ['avecxy_clients', 'COUNT(*)', 'supprime_le IS NULL', []],
            'carnets' => ['avecxy_carnets', 'COUNT(*)', "supprime_le IS NULL AND statut = 'ACTIF'", []],
            'epargne' => ['avecxy_carnets', 'COALESCE(SUM(epargne),0)', "supprime_le IS NULL AND statut = 'ACTIF'", []],
            'collecte' => ['avecxy_cotisations', 'COALESCE(SUM(montant_total),0)', "supprime_le IS NULL AND statut = 'VALIDE' AND date_cotisation >= ? AND date_cotisation <= ?", $periode],
            'ventes' => ['avecxy_carnets', 'COALESCE(SUM(prix),0)', "supprime_le IS NULL AND validation = 'VALIDE' AND date_ouverture >= ? AND date_ouverture <= ?", $periode],
            'encours' => ['avecxy_credits', 'COALESCE(SUM(restant),0)', "supprime_le IS NULL AND statut IN ('EN_COURS','EN_RETARD')", []],
            'en_retard' => ['avecxy_credits', 'COALESCE(SUM(restant),0)', "supprime_le IS NULL AND statut = 'EN_RETARD'", []],
            'benefices' => ['avecxy_benefices', 'COALESCE(SUM(montant_total),0)', 'date_operation >= ? AND date_operation <= ?', $periode],
            'part_recolteurs' => ['avecxy_benefices', 'COALESCE(SUM(part_agent),0)', 'date_operation >= ? AND date_operation <= ?', $periode],
            'part_organisation' => ['avecxy_benefices', 'COALESCE(SUM(part_organisation),0)', 'date_operation >= ? AND date_operation <= ?', $periode],
            'manquants' => ['avecxy_remises_fonds', 'COALESCE(SUM(manquant),0)', 'supprime_le IS NULL AND date_remise >= ? AND date_remise <= ?', $periode],
            'deja_paye' => ['avecxy_paiements_agents', 'COALESCE(SUM(montant_paye),0)', 'supprime_le IS NULL AND date_paiement >= ? AND date_paiement <= ?', $periode],
        ];
        $parties = [];
        $p = [];
        foreach ($chiffres as $nom => [$table, $expression, $condition, $params]) {
            array_push($p, $org, ...$params);
            $agence = Portee::sql('agent_id', $p);
            $parties[] = "(SELECT $expression FROM $table WHERE organisation_id = ? AND $condition" . $agence . ($agent !== null ? ' AND agent_id = ?' : '') . ") AS $nom";
            if ($agent !== null) {
                $p[] = $agent;
            }
        }
        return $this->bd->un('SELECT ' . implode(', ', $parties), $p);
    }

    /** Credits ouverts dont l'echeance tombe d'ici $jours jours, ou deja depassee. */
    public function echeancesProches(int $org, ?int $agent, int $jours): array
    {
        $sql = "SELECT cr.id, cr.numero, cr.date_echeance, cr.restant, cr.statut, c.code AS client_code, c.nom AS client_nom
                FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id
                WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.date_echeance <= ?";
        $p = [$org, date('Y-m-d', strtotime('+' . $jours . ' days'))];
        $sql .= Portee::sql('cr.agent_id', $p);
        if ($agent !== null) {
            $sql .= ' AND cr.agent_id = ?';
            $p[] = $agent;
        }
        return $this->bd->tous($sql . ' ORDER BY cr.date_echeance LIMIT 20', $p);
    }

    /**
     * @return array{colonnes:array<string,string>,lignes:array} colonnes : nom => type (texte, date, argent, nombre, flux, source, statut)
     */
    public function executer(string $rapport, int $org, string $du, string $au, ?int $agent): array
    {
        $p = [$org, $du, $au];
        $filtre = static function (string $colonne) use ($agent, &$p): string {
            $agence = Portee::sql($colonne, $p);
            if ($agent === null) {
                return $agence;
            }
            $p[] = $agent;
            return $agence . " AND $colonne = ?";
        };
        switch ($rapport) {
            case 'operations':
                [$flux, $pf] = $this->caisse->requeteFlux($org);
                $p = array_merge($pf, [$du, $au]);
                $colonnes = ['jour' => 'date', 'type' => 'flux', 'reference' => 'texte', 'recolteur' => 'texte', 'entree' => 'argent', 'sortie' => 'argent'];
                $sql = "SELECT f.jour, f.type, f.libelle AS reference, a.code AS recolteur, f.entree, f.sortie FROM ($flux) f LEFT JOIN avecxy_agents a ON a.id = f.agent_id
                        WHERE f.jour >= ? AND f.jour <= ?" . $filtre('f.agent_id') . ' ORDER BY f.jour, f.type';
                break;
            case 'cotisations':
                $colonnes = ['jour' => 'date', 'code' => 'texte', 'client' => 'texte', 'carnet' => 'texte', 'parts' => 'nombre', 'montant' => 'argent', 'benefice' => 'argent', 'epargne' => 'argent', 'statut' => 'statut', 'recolteur' => 'texte', 'saisi_par' => 'texte'];
                $sql = "SELECT o.date_cotisation AS jour, c.code, c.nom AS client, k.numero AS carnet, o.nombre_parts AS parts, o.montant_total AS montant,
                            o.montant_benefice AS benefice, o.montant_epargne AS epargne, o.statut, a.code AS recolteur, u.nom AS saisi_par
                        FROM avecxy_cotisations o JOIN avecxy_clients c ON c.id = o.client_id JOIN avecxy_carnets k ON k.id = o.carnet_id
                        JOIN avecxy_agents a ON a.id = o.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = o.cree_par
                        WHERE o.organisation_id = ? AND o.supprime_le IS NULL AND o.date_cotisation >= ? AND o.date_cotisation <= ?" . $filtre('o.agent_id') . ' ORDER BY o.date_cotisation, o.id';
                break;
            case 'retraits':
                $colonnes = ['jour' => 'date', 'code' => 'texte', 'client' => 'texte', 'carnet' => 'texte', 'tranche' => 'nombre', 'remis' => 'argent', 'frais' => 'argent', 'recolteur' => 'texte', 'fait_par' => 'texte'];
                $sql = "SELECT r.date_retrait AS jour, c.code, c.nom AS client, k.numero AS carnet, r.tranche, r.montant_retire AS remis, r.frais, a.code AS recolteur, u.nom AS fait_par
                        FROM avecxy_retraits r JOIN avecxy_clients c ON c.id = r.client_id JOIN avecxy_carnets k ON k.id = r.carnet_id
                        JOIN avecxy_agents a ON a.id = r.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = r.cree_par
                        WHERE r.organisation_id = ? AND r.supprime_le IS NULL AND r.statut = 'VALIDE' AND r.date_retrait >= ? AND r.date_retrait <= ?" . $filtre('r.agent_id') . ' ORDER BY r.date_retrait, r.id';
                break;
            case 'carnets':
                $colonnes = ['jour' => 'date', 'carnet' => 'texte', 'code' => 'texte', 'client' => 'texte', 'type' => 'texte', 'mise' => 'argent', 'prix' => 'argent', 'cotise' => 'argent', 'epargne' => 'argent', 'statut' => 'statut', 'recolteur' => 'texte'];
                $sql = "SELECT k.date_ouverture AS jour, k.numero AS carnet, c.code, c.nom AS client, ty.nom AS type, k.mise, k.prix, k.total_cotise AS cotise, k.epargne, k.statut, a.code AS recolteur
                        FROM avecxy_carnets k JOIN avecxy_clients c ON c.id = k.client_id JOIN avecxy_carnet_types ty ON ty.id = k.carnet_type_id JOIN avecxy_agents a ON a.id = k.agent_id
                        WHERE k.organisation_id = ? AND k.supprime_le IS NULL AND k.date_ouverture >= ? AND k.date_ouverture <= ?" . $filtre('k.agent_id') . ' ORDER BY k.date_ouverture, k.id';
                break;
            case 'soldes':
                // situation a ce jour : la periode ne s'applique pas
                $p = [$org];
                $colonnes = ['code' => 'texte', 'client' => 'texte', 'telephone' => 'texte', 'recolteur' => 'texte', 'carnets_ouverts' => 'nombre', 'epargne' => 'argent', 'credit_restant' => 'argent'];
                $sql = "SELECT c.code, c.nom AS client, c.telephone, a.code AS recolteur,
                            (SELECT COUNT(*) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.statut = 'ACTIF' AND k.supprime_le IS NULL) AS carnets_ouverts,
                            (SELECT COALESCE(SUM(k.epargne),0) FROM avecxy_carnets k WHERE k.client_id = c.id AND k.statut = 'ACTIF' AND k.supprime_le IS NULL) AS epargne,
                            (SELECT COALESCE(SUM(cr.restant),0) FROM avecxy_credits cr WHERE cr.client_id = c.id AND cr.statut IN ('EN_COURS','EN_RETARD') AND cr.supprime_le IS NULL) AS credit_restant
                        FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id WHERE c.organisation_id = ? AND c.supprime_le IS NULL" . $filtre('c.agent_id') . ' ORDER BY a.code, c.id';
                break;
            case 'credits':
                $colonnes = ['jour' => 'date', 'credit' => 'texte', 'code' => 'texte', 'client' => 'texte', 'prete' => 'argent', 'interet' => 'argent', 'penalites' => 'argent', 'rembourse' => 'argent', 'restant' => 'argent', 'echeance' => 'date', 'statut' => 'statut', 'recolteur' => 'texte'];
                $sql = "SELECT cr.date_credit AS jour, cr.numero AS credit, c.code, c.nom AS client, cr.montant AS prete, cr.montant_interet AS interet, cr.total_penalites AS penalites,
                            cr.total_rembourse AS rembourse, cr.restant, cr.date_echeance AS echeance, cr.statut, a.code AS recolteur
                        FROM avecxy_credits cr JOIN avecxy_clients c ON c.id = cr.client_id JOIN avecxy_agents a ON a.id = cr.agent_id
                        WHERE cr.organisation_id = ? AND cr.supprime_le IS NULL AND cr.date_credit >= ? AND cr.date_credit <= ?" . $filtre('cr.agent_id') . ' ORDER BY cr.date_credit, cr.id';
                break;
            case 'remboursements':
                $colonnes = ['jour' => 'date', 'credit' => 'texte', 'code' => 'texte', 'client' => 'texte', 'montant' => 'argent', 'statut' => 'statut', 'recolteur' => 'texte', 'saisi_par' => 'texte'];
                $sql = "SELECT rb.date_paiement AS jour, cr.numero AS credit, c.code, c.nom AS client, rb.montant, rb.statut, a.code AS recolteur, u.nom AS saisi_par
                        FROM avecxy_credit_remboursements rb JOIN avecxy_credits cr ON cr.id = rb.credit_id JOIN avecxy_clients c ON c.id = cr.client_id
                        JOIN avecxy_agents a ON a.id = cr.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = rb.cree_par
                        WHERE rb.organisation_id = ? AND rb.supprime_le IS NULL AND cr.supprime_le IS NULL AND rb.date_paiement >= ? AND rb.date_paiement <= ?" . $filtre('cr.agent_id') . ' ORDER BY rb.date_paiement, rb.id';
                break;
            case 'benefices':
                $colonnes = ['jour' => 'date', 'source' => 'source', 'code' => 'texte', 'client' => 'texte', 'recolteur' => 'texte', 'total' => 'argent', 'part_recolteur' => 'argent', 'part_organisation' => 'argent'];
                $sql = "SELECT b.date_operation AS jour, b.source, c.code, c.nom AS client, a.code AS recolteur, b.montant_total AS total, b.part_agent AS part_recolteur, b.part_organisation
                        FROM avecxy_benefices b LEFT JOIN avecxy_clients c ON c.id = b.client_id LEFT JOIN avecxy_agents a ON a.id = b.agent_id
                        WHERE b.organisation_id = ? AND b.date_operation >= ? AND b.date_operation <= ?" . $filtre('b.agent_id') . ' ORDER BY b.date_operation, b.id';
                break;
            case 'recolteurs':
                $p = [$du, $au, $du, $au, $du, $au, $du, $au, $du, $au, $du, $au, $org];
                $colonnes = ['recolteur' => 'texte', 'nom' => 'texte', 'cotise' => 'argent', 'ventes' => 'argent', 'retraits' => 'argent', 'benefices' => 'argent', 'part_recolteur' => 'argent', 'manquants' => 'argent'];
                $sql = "SELECT a.code AS recolteur, a.nom,
                          (SELECT COALESCE(SUM(o.montant_total),0) FROM avecxy_cotisations o WHERE o.agent_id = a.id AND o.statut = 'VALIDE' AND o.supprime_le IS NULL AND o.date_cotisation >= ? AND o.date_cotisation <= ?) AS cotise,
                          (SELECT COALESCE(SUM(k.prix),0) FROM avecxy_carnets k WHERE k.agent_id = a.id AND k.supprime_le IS NULL AND k.date_ouverture >= ? AND k.date_ouverture <= ?) AS ventes,
                          (SELECT COALESCE(SUM(r.montant_retire),0) FROM avecxy_retraits r WHERE r.agent_id = a.id AND r.statut = 'VALIDE' AND r.supprime_le IS NULL AND r.date_retrait >= ? AND r.date_retrait <= ?) AS retraits,
                          (SELECT COALESCE(SUM(b.montant_total),0) FROM avecxy_benefices b WHERE b.agent_id = a.id AND b.date_operation >= ? AND b.date_operation <= ?) AS benefices,
                          (SELECT COALESCE(SUM(b.part_agent),0) FROM avecxy_benefices b WHERE b.agent_id = a.id AND b.date_operation >= ? AND b.date_operation <= ?) AS part_recolteur,
                          (SELECT COALESCE(SUM(rf.manquant),0) FROM avecxy_remises_fonds rf WHERE rf.agent_id = a.id AND rf.supprime_le IS NULL AND rf.date_remise >= ? AND rf.date_remise <= ?) AS manquants
                        FROM avecxy_agents a WHERE a.organisation_id = ? AND a.supprime_le IS NULL" . $filtre('a.id') . ' ORDER BY a.code';
                break;
            case 'manquants':
                $colonnes = ['jour' => 'date', 'recolteur' => 'texte', 'nom' => 'texte', 'attendu' => 'argent', 'remis' => 'argent', 'manquant' => 'argent', 'excedent' => 'argent', 'commentaire' => 'texte', 'saisi_par' => 'texte'];
                $sql = "SELECT rf.date_remise AS jour, a.code AS recolteur, a.nom, rf.montant_attendu AS attendu, rf.montant_verse AS remis, rf.manquant, rf.excedent, rf.commentaire, u.nom AS saisi_par
                        FROM avecxy_remises_fonds rf JOIN avecxy_agents a ON a.id = rf.agent_id LEFT JOIN avecxy_utilisateurs u ON u.id = rf.cree_par
                        WHERE rf.organisation_id = ? AND rf.supprime_le IS NULL AND (rf.manquant > 0 OR rf.excedent > 0) AND rf.date_remise >= ? AND rf.date_remise <= ?" . $filtre('rf.agent_id') . ' ORDER BY a.code, rf.date_remise, rf.id';
                break;
            case 'audit':
                $p = [$org, $du . ' 00:00:00', $au . ' 23:59:59'];
                $colonnes = ['quand' => 'texte', 'utilisateur' => 'texte', 'action' => 'texte', 'objet' => 'texte', 'motif' => 'texte'];
                $sql = "SELECT j.cree_le AS quand, u.nom AS utilisateur, j.action, j.objet_type AS objet, j.motif
                        FROM avecxy_journal_audit j LEFT JOIN avecxy_utilisateurs u ON u.id = j.utilisateur_id
                        WHERE j.organisation_id = ? AND j.cree_le >= ? AND j.cree_le <= ? ORDER BY j.id DESC";
                break;
            default:
                throw new \InvalidArgumentException('Rapport inconnu.');
        }
        return ['colonnes' => $colonnes, 'lignes' => $this->bd->tous($sql . ' LIMIT ' . self::MAX_LIGNES, $p)];
    }
}

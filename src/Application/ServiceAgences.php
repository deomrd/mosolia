<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Agences d'une organisation. Chaque agence a ses recolteurs (donc ses clients) et sa caisse ;
 * un utilisateur rattache a une agence ne voit qu'elle. Sans agence, rien ne change : tout est au siege.
 */
final class ServiceAgences
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotCaisse $caisse,
        private readonly Audit $audit,
    ) {
    }

    /** @return array<int,array> agences (non supprimees), avec recolteurs, clients et argent en main */
    public function liste(int $org, bool $avecChiffres = false): array
    {
        $agences = $this->bd->tous(
            'SELECT g.*,
                (SELECT COUNT(*) FROM avecxy_agents a WHERE a.agence_id = g.id AND a.supprime_le IS NULL) AS nb_recolteurs,
                (SELECT COUNT(*) FROM avecxy_clients c JOIN avecxy_agents a ON a.id = c.agent_id WHERE a.agence_id = g.id AND c.supprime_le IS NULL) AS nb_clients,
                (SELECT COUNT(*) FROM avecxy_utilisateurs u WHERE u.agence_id = g.id AND u.supprime_le IS NULL) AS nb_utilisateurs
             FROM avecxy_agences g WHERE g.organisation_id = ? AND g.supprime_le IS NULL ORDER BY g.nom',
            [$org]
        );
        if ($avecChiffres) {
            $soldes = array_column($this->caisse->soldesParAgence($org), 'solde', 'agence');
            foreach ($agences as &$a) {
                $a['en_main'] = $soldes[$a['id']] ?? 0;
            }
        }
        return $agences;
    }

    public function parId(int $org, int $id): ?array
    {
        return $this->bd->un('SELECT * FROM avecxy_agences WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $id]);
    }

    /** L'agence existe-t-elle dans l'organisation ? 0 = siege, toujours valable. */
    public function exiger(int $org, int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }
        return $this->parId($org, $id) !== null ? $id : throw new ErreurMetier('agences.introuvable');
    }

    public function enregistrer(Contexte $c, int $id, array $d): int
    {
        $org = $c->organisationId();
        $nom = Texte::nom((string) ($d['nom'] ?? ''));
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['code'] ?? '')) ?? '');
        if ($nom === '') {
            throw new ErreurMetier('agences.nom_obligatoire');
        }
        if ($code === '' || strlen($code) > 10) {
            throw new ErreurMetier('agences.code_invalide');
        }
        if ($this->bd->valeur('SELECT COUNT(*) FROM avecxy_agences WHERE organisation_id = ? AND code = ? AND id <> ? AND supprime_le IS NULL', [$org, $code, $id]) > 0) {
            throw new ErreurMetier('agences.code_pris');
        }
        $ligne = [
            'code' => $code, 'nom' => $nom,
            'adresse' => trim((string) ($d['adresse'] ?? '')) !== '' ? Texte::nom((string) $d['adresse']) : null,
            'telephone' => trim((string) ($d['telephone'] ?? '')) !== '' ? Texte::identifiant((string) $d['telephone']) : null,
            'actif' => ($d['actif'] ?? '1') === '0' ? 0 : 1,
        ];
        if ($id === 0) {
            $id = $this->bd->inserer('avecxy_agences', $ligne + ['organisation_id' => $org, 'cree_le' => $this->bd->maintenant()]);
            $this->audit->noter($org, $c->utilisateurId(), 'agence.creer', 'agence', $id, null, $ligne);
            return $id;
        }
        $avant = $this->parId($org, $id) ?? throw new ErreurMetier('agences.introuvable');
        $this->bd->modifier('avecxy_agences', $ligne, ['id' => $id]);
        $this->audit->noter($org, $c->utilisateurId(), 'agence.modifier', 'agence', $id, $avant, $ligne);
        return $id;
    }

    /** Une agence qui a encore des recolteurs, des utilisateurs ou de l'argent ne se supprime pas. */
    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        $org = $c->organisationId();
        $a = $this->parId($org, $id) ?? throw new ErreurMetier('agences.introuvable');
        $occupee = (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agents WHERE agence_id = ? AND supprime_le IS NULL', [$id])
            + (int) $this->bd->valeur('SELECT COUNT(*) FROM avecxy_utilisateurs WHERE agence_id = ? AND supprime_le IS NULL', [$id]);
        if ($occupee > 0) {
            throw new ErreurMetier('agences.occupee');
        }
        if ((float) $this->caisse->solde($org, null, $id) != 0.0) {
            throw new ErreurMetier('agences.argent_en_main');
        }
        $this->bd->modifier('avecxy_agences', ['supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(), 'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null], ['id' => $id]);
        $this->audit->noter($org, $c->utilisateurId(), 'agence.supprimer', 'agence', $id, $a, null, $motif ?: null);
        return (string) $a['nom'];
    }
}

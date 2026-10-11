<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceCaisse;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Infrastructure\DepotCredits;
use AvecXY\Noyau\Reponse;

/** Corbeille : tout ce qui a ete supprime, par categorie, avec restauration. */
final class CorbeilleControleur extends Controleur
{
    public function index(): Reponse
    {
        $org = $this->contexte()->organisationId();
        $sections = [];
        foreach ($this->noyau->utilisateurs()->liste($org, true) as $u) {
            $sections['utilisateurs'][] = ['id' => $u['id'], 'nom' => $u['nom'], 'detail' => $u['identifiant'], 'le' => $u['supprime_le'], 'motif' => $u['motif_suppression']];
        }
        foreach ($this->noyau->recolteurs()->liste($org, true) as $r) {
            $sections['recolteurs'][] = ['id' => $r['id'], 'nom' => $r['nom'], 'detail' => $r['code'], 'le' => $r['supprime_le'], 'motif' => $r['motif_suppression']];
        }
        foreach ($this->noyau->typesCarnet()->liste($org, true) as $t) {
            $sections['types-carnet'][] = ['id' => $t['id'], 'nom' => $t['nom'], 'detail' => t('carnets.mode.' . strtolower($t['mode'])), 'le' => $t['supprime_le'], 'motif' => $t['motif_suppression']];
        }
        foreach ($this->noyau->clients()->liste($org, null, '', 0, 1, 200, true)['lignes'] as $cl) {
            $sections['clients'][] = ['id' => $cl['id'], 'nom' => $cl['nom'], 'detail' => $cl['code'], 'le' => $cl['supprime_le'], 'motif' => $cl['motif_suppression']];
        }
        foreach ($this->noyau->carnets()->liste($org, null, '', '', 1, 200, true)['lignes'] as $k) {
            $sections['carnets'][] = ['id' => $k['id'], 'nom' => '#' . $k['numero'] . ' ' . $k['client_nom'], 'detail' => $k['client_code'] . ' · ' . $k['type_nom'], 'le' => $k['supprime_le'], 'motif' => $k['motif_suppression']];
        }
        $bd = $this->noyau->bd();
        // cotisations et retraits supprimes seuls (ceux partis avec leur carnet reviennent avec lui)
        foreach ($bd->tous('SELECT o.id, o.montant_total, o.date_cotisation, o.supprime_le, o.motif_suppression, c.code, c.nom FROM avecxy_cotisations o
                JOIN avecxy_carnets k ON k.id = o.carnet_id JOIN avecxy_clients c ON c.id = o.client_id
                WHERE o.organisation_id = ? AND o.supprime_le IS NOT NULL AND k.supprime_le IS NULL ORDER BY o.supprime_le DESC LIMIT 200', [$org]) as $o) {
            $sections['cotisations'][] = ['id' => $o['id'], 'nom' => argent($o['montant_total']), 'detail' => $o['code'] . ' ' . $o['nom'] . ' · ' . jour($o['date_cotisation']), 'le' => $o['supprime_le'], 'motif' => $o['motif_suppression']];
        }
        foreach ($bd->tous('SELECT r.id, r.montant_retire, r.date_retrait, r.supprime_le, r.motif_suppression, c.code, c.nom FROM avecxy_retraits r
                JOIN avecxy_carnets k ON k.id = r.carnet_id JOIN avecxy_clients c ON c.id = r.client_id
                WHERE r.organisation_id = ? AND r.supprime_le IS NOT NULL AND k.supprime_le IS NULL ORDER BY r.supprime_le DESC LIMIT 200', [$org]) as $r) {
            $sections['retraits'][] = ['id' => $r['id'], 'nom' => argent($r['montant_retire']), 'detail' => $r['code'] . ' ' . $r['nom'] . ' · ' . jour($r['date_retrait']), 'le' => $r['supprime_le'], 'motif' => $r['motif_suppression']];
        }
        foreach ($bd->tous('SELECT e.id, e.montant_demande, e.supprime_le, e.motif_suppression, c.code, c.nom FROM avecxy_credit_eligibilites e JOIN avecxy_clients c ON c.id = e.client_id
                WHERE e.organisation_id = ? AND e.supprime_le IS NOT NULL ORDER BY e.supprime_le DESC LIMIT 200', [$org]) as $d) {
            $sections['demandes-credit'][] = ['id' => $d['id'], 'nom' => $d['code'] . ' ' . $d['nom'], 'detail' => argent($d['montant_demande']), 'le' => $d['supprime_le'], 'motif' => $d['motif_suppression']];
        }
        $caisse = $this->noyau->caisse();
        foreach (ServiceCaisse::TABLES as $type => $table) {
            foreach ($caisse->supprimees($table, $org) as $l) {
                $montant = $l['montant_verse'] ?? $l['montant_paye'] ?? $l['montant_base'] ?? $l['montant'] ?? 0;
                $sections[$type][] = [
                    'id' => $l['id'], 'nom' => argent($montant),
                    'detail' => jour($l['date_mouvement'] ?? $l['date_remise'] ?? $l['date_emprunt'] ?? $l['date_paiement'] ?? null) . (isset($l['preteur']) ? ' · ' . $l['preteur'] : '') . (isset($l['periode']) ? ' · ' . $l['periode'] : ''),
                    'le' => $l['supprime_le'], 'motif' => $l['motif_suppression'],
                ];
            }
        }
        foreach (DepotCredits::TABLES as $type => $table) {
            foreach ($this->noyau->credits()->supprimees($table, $org) as $l) {
                $sections[$type][] = [
                    'id' => $l['id'], 'nom' => $l['nom'] ?? argent($l['montant']),
                    'detail' => isset($l['numero']) ? '#' . $l['numero'] : jour($l['date_paiement'] ?? $l['date_penalite'] ?? null),
                    'le' => $l['supprime_le'], 'motif' => $l['motif_suppression'],
                ];
            }
        }
        return $this->page('parametres/corbeille', ['titre' => t('menu.corbeille'), 'sections' => $sections]);
    }

    public function restaurer(string $type, string $id): Reponse
    {
        try {
            $nom = match ($type) {
                'utilisateurs' => $this->noyau->serviceUtilisateurs()->restaurer($this->contexte(), (int) $id),
                'recolteurs' => $this->noyau->serviceRecolteurs()->restaurer($this->contexte(), (int) $id),
                'clients' => $this->noyau->serviceClients()->restaurer($this->contexte(), (int) $id),
                'carnets' => $this->noyau->serviceCarnets()->restaurer($this->contexte(), (int) $id),
                'types-carnet' => $this->noyau->serviceTypesCarnet()->restaurer($this->contexte(), (int) $id),
                'mouvements', 'remises', 'paiements-recolteurs', 'repartitions', 'reglements-manquants', 'emprunts', 'remboursements-emprunt' => $this->noyau->serviceCaisse()->restaurer($this->contexte(), $type, (int) $id),
                'credits', 'remboursements', 'penalites', 'produits-credit' => $this->noyau->serviceCredits()->restaurer($this->contexte(), $type, (int) $id),
                'cotisations' => $this->noyau->serviceCotisations()->restaurer($this->contexte(), (int) $id),
                'retraits' => $this->noyau->serviceRetraits()->restaurer($this->contexte(), (int) $id),
                'demandes-credit' => $this->noyau->serviceCredits()->restaurerDemande($this->contexte(), (int) $id),
                default => throw new ErreurMetier('erreur.404.titre'),
            };
            $this->succes('corbeille.restaure', ['nom' => $nom]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/corbeille');
    }
}

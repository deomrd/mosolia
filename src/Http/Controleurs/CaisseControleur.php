<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceCaisse;
use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Journal de caisse, fonds propres, remises, paiement des recolteurs, repartition. */
final class CaisseControleur extends Controleur
{
    private const DROIT_SUPPRESSION = [
        'mouvements' => 'caisse.mouvements', 'remises' => 'remises.gerer',
        'paiements-recolteurs' => 'agents.payer', 'repartitions' => 'repartition.payer',
        'reglements-manquants' => 'remises.gerer', 'emprunts' => 'caisse.mouvements', 'remboursements-emprunt' => 'caisse.mouvements',
        'transferts' => 'caisse.transferts',
    ];
    private const RETOUR = [
        'mouvements' => '/caisse', 'remises' => '/remises', 'paiements-recolteurs' => '/paiements-recolteurs', 'repartitions' => '/repartition',
        'reglements-manquants' => '/remises', 'emprunts' => '/caisse', 'remboursements-emprunt' => '/caisse', 'transferts' => '/caisse',
    ];

    public function journal(): Reponse
    {
        $c = $this->contexte();
        $org = $c->organisationId();
        $g = $this->requete->get;
        $agences = $c->agenceRestreinte() === null ? $this->noyau->serviceAgences()->liste($org) : [];
        // caisse affichee : celle de l'agence de l'utilisateur, ou celle choisie ('' = toutes, 0 = siege)
        $choix = (string) ($g['agence'] ?? '');
        $agence = $c->agenceRestreinte() ?? ($choix === '' || !$agences ? null : max(0, (int) $choix));
        $du = Dates::ou((string) ($g['du'] ?? ''), date('Y-m-01'));
        $au = Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $caisse = $this->noyau->caisse();
        $jours = [];
        foreach ($caisse->journal($org, $du, $au, $agence) as $l) {
            $jours[$l['jour']][] = $l;
        }
        $nomAgence = $agence === null ? null : ($agence === 0 ? t('agences.siege') : (string) ($this->noyau->serviceAgences()->parId($org, $agence)['nom'] ?? ''));
        return $this->page('caisse/journal', [
            'titre' => t('menu.journal_caisse'), 'du' => $du, 'au' => $au, 'jours' => $jours,
            'agences' => $agences, 'agence' => $agence, 'nomAgence' => $nomAgence, 'restreint' => $c->agenceRestreinte() !== null,
            'transferts' => $agences || $c->agenceRestreinte() !== null ? $caisse->transferts($org, $du, $au) : [],
            'ouverture' => $caisse->solde($org, Dates::ajouterJours($du, -1), $agence), 'cloture' => $caisse->solde($org, $au, $agence),
            'enMain' => $caisse->solde($org, null, $agence), 'mouvements' => $caisse->mouvements($org, substr($au, 0, 7)),
            'emprunts' => $caisse->emprunts($org), 'remboursementsEmprunts' => $caisse->remboursementsEmprunts($org),
        ]);
    }

    public function mouvement(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->mouvement($this->contexte(), $r->texte('type'), $r->texte('montant'), $r->texte('date'), $r->texte('source'), $r->texte('commentaire'), $r->entier('agence_id'));
            $this->succes('caisse.mouvement_enregistre');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/caisse');
    }

    public function transferer(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->transferer($this->contexte(), $r->entier('de'), $r->entier('vers'), $r->texte('montant'), $r->texte('date'), $r->texte('motif'));
            $this->succes('agences.transfert_fait');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/caisse');
    }

    public function emprunter(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->emprunter($this->contexte(), $r->texte('preteur'), $r->texte('montant'), $r->texte('taux'), $r->texte('date'), $r->texte('echeance'), $r->texte('commentaire'));
            $this->succes('emprunts.enregistre');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/caisse');
    }

    public function rembourserEmprunt(string $id): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->rembourserEmprunt($this->contexte(), (int) $id, $r->texte('montant'), $r->texte('date', date('Y-m-d')), $r->texte('commentaire'));
            $this->succes('emprunts.rembourse');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/caisse');
    }

    public function reglerManquant(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->reglerManquant($this->contexte(), $r->entier('recolteur'), $r->texte('montant'), $r->texte('date', date('Y-m-d')), $r->texte('commentaire'));
            $this->succes('manquants.regle');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/remises');
    }

    public function remises(): Reponse
    {
        $org = $this->contexte()->organisationId();
        $jour = Dates::ou((string) ($this->requete->get['jour'] ?? ''), date('Y-m-d'));
        return $this->page('caisse/remises', [
            'titre' => t('menu.remises'), 'jour' => $jour,
            'collecte' => $this->noyau->caisse()->collecteDuJour($org, $jour),
            'remises' => $this->noyau->caisse()->remises($org, substr($jour, 0, 7)),
            'dus' => array_filter($this->noyau->caisse()->manquantsDus($org), static fn ($l) => (float) $l['constates'] > 0),
            'reglements' => $this->noyau->caisse()->reglements($org, substr($jour, 0, 7)),
        ]);
    }

    public function remettre(): Reponse
    {
        $r = $this->requete;
        try {
            $this->noyau->serviceCaisse()->remise($this->contexte(), $r->entier('recolteur'), $r->texte('date'), $r->texte('attendu'), $r->texte('verse'), $r->texte('commentaire'));
            $this->succes('remises.enregistree');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/remises?jour=' . urlencode(Dates::ou($r->texte('date'), date('Y-m-d'))));
    }

    public function paiements(): Reponse
    {
        $periode = ServiceCaisse::periode((string) ($this->requete->get['mois'] ?? ''));
        $org = $this->contexte()->organisationId();
        return $this->page('caisse/paiements', [
            'titre' => t('menu.paiement_agents'), 'periode' => $periode,
            'lignes' => $this->noyau->caisse()->syntheseRecolteurs($org, $periode), 'enMain' => $this->noyau->caisse()->solde($org),
            'deduire' => $this->noyau->serviceCaisse()->manquantsDeduits($org),
            'dus' => array_column($this->noyau->caisse()->manquantsDus($org), 'du', 'id'),
        ]);
    }

    public function payer(): Reponse
    {
        $r = $this->requete;
        $periode = ServiceCaisse::periode($r->texte('mois'));
        try {
            $net = $this->noyau->serviceCaisse()->payerRecolteur($this->contexte(), $r->entier('recolteur'), $periode, $r->texte('date', date('Y-m-d')));
            $this->succes('paiements.paye', ['montant' => argent($net / 100)]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/paiements-recolteurs?mois=' . $periode);
    }

    public function repartition(): Reponse
    {
        $periode = ServiceCaisse::periode((string) ($this->requete->get['mois'] ?? ''));
        $org = $this->contexte()->organisationId();
        return $this->page('caisse/repartition', [
            'titre' => t('menu.repartition'), 'periode' => $periode,
            'projet' => $this->noyau->serviceCaisse()->projetRepartition($org, $periode, $this->contexte()->decimales()),
            'faite' => $this->noyau->caisse()->repartition($org, $periode),
            'historique' => $this->noyau->caisse()->repartitions($org), 'enMain' => $this->noyau->caisse()->solde($org),
        ]);
    }

    public function repartir(): Reponse
    {
        $r = $this->requete;
        $periode = ServiceCaisse::periode($r->texte('mois'));
        try {
            $base = $this->noyau->serviceCaisse()->payerRepartition($this->contexte(), $periode, $r->texte('date', date('Y-m-d')));
            $this->succes('repartition.payee', ['montant' => argent($base / 100)]);
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/repartition?mois=' . $periode);
    }

    public function supprimer(string $type, string $id): Reponse
    {
        $droit = self::DROIT_SUPPRESSION[$type] ?? null;
        if ($droit === null || !$this->contexte()->peut($droit)) {
            return $this->noyau->pageErreur(403, 'erreur.403');
        }
        try {
            $this->noyau->serviceCaisse()->supprimer($this->contexte(), $type, (int) $id, $this->requete->texte('motif'));
            $this->succes('caisse.ligne_supprimee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        $retour = $this->requete->texte('retour');
        return $this->rediriger(self::RETOUR[$type] . (preg_match('/^[a-z]+=[0-9-]+$/', $retour) ? '?' . $retour : ''));
    }
}

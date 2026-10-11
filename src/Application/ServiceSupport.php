<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Infrastructure\DepotOrganisations;
use AvecXY\Infrastructure\DepotPlateforme;
use AvecXY\Noyau\BaseDeDonnees;

/** Cote organisation : demandes au support et demande de forfait. */
final class ServiceSupport
{
    public const CATEGORIES = ['QUESTION', 'PROBLEME', 'ABONNEMENT', 'SUGGESTION'];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotPlateforme $plateforme,
        private readonly DepotOrganisations $organisations,
        private readonly Audit $audit,
        private readonly ?ServiceNotifications $notifications = null,
    ) {
    }

    public function ouvrir(Contexte $c, string $sujet, string $categorie, bool $urgent, string $message): int
    {
        $sujet = trim($sujet);
        $message = trim($message);
        if ($sujet === '' || $message === '') {
            throw new ErreurMetier('support.message_obligatoire');
        }
        return $this->bd->transaction(function () use ($c, $sujet, $categorie, $urgent, $message): int {
            $id = $this->plateforme->creerTicket([
                'organisation_id' => $c->organisationId(), 'utilisateur_id' => $c->utilisateurId(), 'sujet' => mb_substr($sujet, 0, 190),
                'categorie' => in_array($categorie, self::CATEGORIES, true) ? $categorie : 'QUESTION', 'priorite' => $urgent ? 'URGENTE' : 'NORMALE',
                'statut' => 'OUVERT', 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->plateforme->ajouterMessage($id, $c->utilisateurId(), null, mb_substr($message, 0, 5000), 'OUVERT');
            return $id;
        });
    }

    public function repondre(Contexte $c, int $ticketId, string $message, bool $fermer): void
    {
        $t = $this->plateforme->ticket($ticketId, $c->organisationId()) ?? throw new ErreurMetier('support.introuvable');
        $message = trim($message);
        if ($fermer) {
            if ($message !== '') {
                $this->plateforme->ajouterMessage($ticketId, $c->utilisateurId(), null, mb_substr($message, 0, 5000), 'FERME');
            } else {
                $this->plateforme->changerStatutTicket($ticketId, 'FERME');
            }
            return;
        }
        if ($message === '') {
            throw new ErreurMetier('support.message_obligatoire');
        }
        $this->plateforme->ajouterMessage($ticketId, $c->utilisateurId(), null, mb_substr($message, 0, 5000), 'OUVERT');
    }

    /** L'organisation annonce son paiement : AvecXY le verifie puis active le forfait. */
    public function demanderForfait(Contexte $c, int $planId, string $moyen, string $reference): void
    {
        $plan = $this->organisations->planParId($planId);
        if ($plan === null || (int) $plan['actif'] !== 1 || (float) $plan['prix'] <= 0) {
            throw new ErreurMetier('organisation.forfait_introuvable');
        }
        $org = $c->organisationId();
        if ($this->plateforme->demandeEnAttente($org)) {
            throw new ErreurMetier('abonnement.demande_deja_envoyee');
        }
        $abonnement = $this->organisations->dernierAbonnement($org) ?? throw new ErreurMetier('organisation.forfait_introuvable');
        $id = $this->plateforme->creerPaiement([
            'organisation_id' => $org, 'abonnement_id' => $abonnement['id'], 'plan_id' => $planId, 'montant' => $plan['prix'], 'devise_code' => $plan['devise_code'],
            'mode' => 'MANUEL', 'moyen' => trim($moyen) !== '' ? mb_substr(trim($moyen), 0, 40) : null, 'statut' => 'EN_ATTENTE',
            'note' => trim($reference) !== '' ? mb_substr(trim($reference), 0, 500) : null, 'cree_le' => $this->bd->maintenant(),
        ]);
        $this->audit->noter($org, $c->utilisateurId(), 'abonnement.demander', 'abonnement_paiement', $id, null, ['forfait' => $plan['code']]);
        if ($this->notifications !== null) {
            $d = ['organisation' => (string) $c->organisation['nom'], 'forfait' => (string) $plan['nom'], 'par' => (string) $c->utilisateur['nom']];
            $this->notifications->plateforme($org, 'abonnement_demande', $d, '/plateforme/organisations/' . $org);
            $this->notifications->utilisateurs($org, $this->notifications->comptesAyant($org, ['abonnement.gerer']), 'abonnement_demande_envoyee', $d, '/abonnement');
        }
    }
}

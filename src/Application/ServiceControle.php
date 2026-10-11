<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotCaisse;
use AvecXY\Infrastructure\DepotControle;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Controle, recolteur par recolteur : on coche les saisies a valider ou a rejeter (les autres restent en attente),
 * et on note l'argent depose pour voir tout de suite un manquant ou un excedent.
 */
final class ServiceControle
{
    public const DROITS = ['carnets' => 'carnets.valider', 'cotisations' => 'cotisations.valider', 'paiements' => 'remboursements.valider'];

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotControle $controles,
        private readonly DepotCaisse $caisse,
        private readonly ServiceCarnets $carnets,
        private readonly ServiceCotisations $cotisations,
        private readonly ServiceCredits $credits,
        private readonly Audit $audit,
        private readonly ?ServiceNotifications $notifications = null,
    ) {
    }

    /**
     * @param int[] $ids lignes cochees
     * @param string $depose argent remis par le recolteur pour ce qui est valide (vide = pas de comparaison)
     * @return array{valides:int,rejetes:int,montant:int,ecart:?int,erreurs:string[]}
     */
    public function traiter(Contexte $c, string $type, int $agentId, array $ids, bool $valider, string $depose): array
    {
        $droit = self::DROITS[$type] ?? throw new ErreurMetier('erreur.404.titre');
        if (!$c->peut($droit)) {
            throw new ErreurMetier('erreur.403.texte');
        }
        $org = $c->organisationId();
        $deposeCentimes = $valider && trim($depose) !== '' ? Montant::enCentimes(Montant::lire($depose, $c->decimales())) : null;
        // seules les lignes reellement en attente chez ce recolteur sont traitees
        $attente = array_column($this->controles->lignes($org, $type, $agentId), null, 'id');
        $choisies = array_intersect_key($attente, array_flip(array_map('intval', $ids)));
        if (!$choisies) {
            throw new ErreurMetier('controle.rien_coche');
        }
        $faites = 0;
        $montant = 0;
        $erreurs = [];
        foreach ($choisies as $id => $l) {
            try {
                match (true) {
                    $type === 'carnets' && $valider => $this->carnets->validerVente($c, (int) $id),
                    $type === 'carnets' => $this->carnets->supprimer($c, (int) $id, t('controle.rejete')),
                    $type === 'cotisations' && $valider => $this->cotisations->valider($c, (int) $id),
                    $type === 'cotisations' => $this->cotisations->rejeter($c, (int) $id, ''),
                    $valider => $this->credits->traiterRemboursement($c, (int) $id, true),
                    default => $this->credits->supprimer($c, 'remboursements', (int) $id, t('controle.rejete')),
                };
                $faites++;
                $montant += Montant::enCentimes($l['montant']);
                if (!$valider && $type !== 'cotisations') {
                    $this->notifications?->client($org, (int) $l['client_id'], 'client_' . $type . '_rejete', ['montant' => argent($l['montant']), 'reference' => (string) $l['reference'], 'date' => jour($l['jour'])], '/espace-client');
                }
                if ($valider) {
                    // chaque client n'est prevenu que de sa propre operation
                    $this->notifications?->client($org, (int) $l['client_id'], 'client_' . $type . '_valide',
                        ['montant' => argent($l['montant']), 'reference' => (string) $l['reference'], 'date' => jour($l['jour'])],
                        (int) $l['carnet_id'] > 0 ? '/espace-client/carnets/' . $l['carnet_id'] : '/espace-client');
                }
            } catch (ErreurMetier $e) {
                $erreurs[] = $l['client_code'] . ' : ' . t($e->cle, $e->valeurs);
            }
        }
        $ecart = null;
        $remiseId = null;
        if ($deposeCentimes !== null && $faites > 0) {
            $ecart = $deposeCentimes - $montant;
            $remiseId = $this->caisse->creerRemise([
                'organisation_id' => $org, 'agent_id' => $agentId, 'date_remise' => date('Y-m-d'), 'periode' => date('Y-m'),
                'origine' => 'CONTROLE_COLLECTE', 'montant_attendu' => Montant::depuisCentimes($montant), 'montant_verse' => Montant::depuisCentimes($deposeCentimes),
                'manquant' => Montant::depuisCentimes(max(0, -$ecart)), 'excedent' => Montant::depuisCentimes(max(0, $ecart)),
                'commentaire' => t('controle.remise_commentaire', ['type' => t('menu.controle_' . $type)]),
                'cree_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
        }
        if ($faites > 0) {
            $id = $this->controles->creer([
                'organisation_id' => $org, 'agent_id' => $agentId, 'type' => strtoupper($type),
                'valides' => $valider ? $faites : 0, 'rejetes' => $valider ? 0 : $faites,
                'montant_valide' => Montant::depuisCentimes($valider ? $montant : 0), 'montant_rejete' => Montant::depuisCentimes($valider ? 0 : $montant),
                'montant_depose' => $deposeCentimes === null ? null : Montant::depuisCentimes($deposeCentimes),
                'manquant' => Montant::depuisCentimes($ecart !== null ? max(0, -$ecart) : 0), 'excedent' => Montant::depuisCentimes($ecart !== null ? max(0, $ecart) : 0),
                'remise_id' => $remiseId, 'fait_par' => $c->utilisateurId(), 'cree_le' => $this->bd->maintenant(),
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'controle.' . ($valider ? 'valider' : 'rejeter'), 'controle', $id, null, ['type' => $type, 'recolteur' => $agentId, 'nombre' => $faites, 'montant' => $montant / 100]);
            if ($this->notifications !== null) {
                $this->notifications->utilisateurs($org, array_diff($this->notifications->comptesDuRecolteur($org, $agentId), [$c->utilisateurId()]),
                    'controle_' . $type . ($valider ? '_valide' : '_rejete'),
                    ['nombre' => $faites, 'montant' => argent($montant / 100), 'par' => (string) $c->utilisateur['nom'], 'ecart' => $ecart === null || $ecart === 0 ? '' : t($ecart < 0 ? 'notif.avec_manquant' : 'notif.avec_excedent', ['montant' => argent(abs($ecart) / 100)])],
                    '/controle/historique');
            }
        }
        return ['valides' => $valider ? $faites : 0, 'rejetes' => $valider ? 0 : $faites, 'montant' => $montant, 'ecart' => $ecart, 'erreurs' => $erreurs];
    }
}

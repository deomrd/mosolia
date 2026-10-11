<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\Montant;
use AvecXY\Infrastructure\DepotOperations;
use AvecXY\Infrastructure\DepotParametres;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Travaux automatiques d'une organisation, faits une fois par jour a la premiere page ouverte :
 * retards et penalites de credit, frais de tenue mensuels, interets des comptes bloques. Aucune tache planifiee n'est a installer
 * sur le serveur. (bin/taches.php fait le meme travail pour toutes les organisations, si on prefere un cron.)
 */
final class ServiceTaches
{
    /** Ce qui est dans la corbeille depuis plus longtemps est efface pour de bon. */
    public const JOURS_CORBEILLE = 30;

    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotParametres $parametres,
        private readonly DepotOperations $operations,
        private readonly RecalculCarnet $recalcul,
        private readonly ServiceCredits $credits,
        private readonly ?ServiceInterets $interets = null,
    ) {
    }

    /** @return bool true si les travaux ont tourne */
    public function duJour(int $org, int $decimales): bool
    {
        $jour = date('Y-m-d');
        if ($this->parametres->lire($org, 'taches.dernier_jour') === $jour) {
            return false;
        }
        // on note d'abord le jour : si un travail echoue, la page n'est pas bloquee a chaque clic
        $this->parametres->ecrire($org, 'taches.dernier_jour', $jour);
        $this->credits->suivreRetards($org, $decimales);
        $this->fraisMensuels($org, $decimales);
        $this->interets?->toutVerser($org, $decimales);
        $this->viderCorbeille($org);
        return true;
    }

    /** Preleve les frais de tenue mensuels des carnets ouverts, mois par mois, sans jamais compter deux fois. */
    public function fraisMensuels(int $org, int $decimales): int
    {
        $carnets = $this->bd->tous(
            "SELECT * FROM avecxy_carnets WHERE organisation_id = ? AND statut = 'ACTIF' AND supprime_le IS NULL
             AND frais_tenue_frequence = 'MENSUEL' AND frais_tenue_type IN ('FIXE','POURCENTAGE_SOLDE') AND date_ouverture < ?",
            [$org, date('Y-m-01')]
        );
        $preleves = 0;
        foreach ($carnets as $carnet) {
            $this->bd->transaction(function () use ($org, $carnet, $decimales, &$preleves): void {
                // premier prelevement le mois qui suit l'ouverture, au plus 12 mois de rattrapage
                $mois = new \DateTimeImmutable(substr((string) $carnet['date_ouverture'], 0, 7) . '-01');
                $fin = new \DateTimeImmutable(date('Y-m-01'));
                $debut = max($mois->modify('+1 month'), $fin->modify('-11 months'));
                for ($m = $debut; $m <= $fin; $m = $m->modify('+1 month')) {
                    $periode = $m->format('Y-m');
                    if ($this->operations->fraisDejaPreleves((int) $carnet['id'], $periode, 'MENSUEL')) {
                        continue;
                    }
                    $carnet = $this->recalcul->recalculer($org, (int) $carnet['id']);
                    $solde = Montant::enCentimes($carnet['epargne']);
                    $frais = min($solde, CalculCarnet::frais($carnet, $solde, $decimales));
                    if ($frais <= 0) {
                        continue;
                    }
                    $this->operations->creerFrais([
                        'organisation_id' => $org, 'carnet_id' => $carnet['id'], 'client_id' => $carnet['client_id'], 'agent_id' => $carnet['agent_id'],
                        'date_frais' => $m->format('Y-m-d'), 'periode' => $periode, 'origine' => 'MENSUEL',
                        'base' => Montant::depuisCentimes($solde), 'montant' => Montant::depuisCentimes($frais), 'cree_le' => $this->bd->maintenant(),
                    ]);
                    $this->recalcul->recalculer($org, (int) $carnet['id']);
                    $preleves++;
                }
            });
        }
        return $preleves;
    }

    /**
     * Efface definitivement ce qui est dans la corbeille depuis plus de 30 jours.
     * Les enfants partent avant les parents ; une ligne encore utilisee ailleurs est laissee en place.
     * @return int nombre de lignes effacees
     */
    public function viderCorbeille(int $org): int
    {
        $limite = date('Y-m-d H:i:s', strtotime('-' . self::JOURS_CORBEILLE . ' days'));
        $effacees = 0;
        $essayer = function (string $sql, array $p) use (&$effacees): void {
            try {
                $effacees += $this->bd->executer($sql, $p);
            } catch (\Throwable) {
                // encore referencee : elle reste dans la corbeille
            }
        };
        $perimes = fn (string $table): array => array_map('intval', array_column(
            $this->bd->tous("SELECT id FROM $table WHERE organisation_id = ? AND supprime_le IS NOT NULL AND supprime_le < ?", [$org, $limite]), 'id'
        ));

        // credits et ce qui en depend
        foreach (['avecxy_credit_remboursements', 'avecxy_credit_penalites'] as $table) {
            $essayer("DELETE FROM $table WHERE organisation_id = ? AND supprime_le IS NOT NULL AND supprime_le < ?", [$org, $limite]);
        }
        foreach ($perimes('avecxy_credits') as $id) {
            foreach (['avecxy_credit_remboursements', 'avecxy_credit_penalites', 'avecxy_credit_echeances', 'avecxy_benefices'] as $table) {
                $essayer("DELETE FROM $table WHERE credit_id = ?", [$id]);
            }
            $essayer('UPDATE avecxy_credit_eligibilites SET credit_id = NULL WHERE credit_id = ?', [$id]);
            $essayer('DELETE FROM avecxy_credits WHERE id = ?', [$id]);
        }
        foreach ($perimes('avecxy_credit_eligibilites') as $id) {
            $essayer('DELETE FROM avecxy_credit_eligibilites WHERE id = ?', [$id]);
        }
        foreach ($perimes('avecxy_credit_produits') as $id) {
            $essayer('DELETE FROM avecxy_credit_produits WHERE id = ?', [$id]);
        }
        // operations des carnets, puis carnets, puis clients
        foreach (['avecxy_cotisations', 'avecxy_retraits'] as $table) {
            $essayer("DELETE FROM $table WHERE organisation_id = ? AND supprime_le IS NOT NULL AND supprime_le < ?
                AND carnet_id IN (SELECT id FROM avecxy_carnets WHERE organisation_id = ? AND supprime_le IS NULL)", [$org, $limite, $org]);
        }
        $essayer('DELETE FROM avecxy_frais_tenue WHERE organisation_id = ? AND supprime_le IS NOT NULL AND supprime_le < ?', [$org, $limite]);
        foreach ($perimes('avecxy_carnets') as $id) {
            foreach (['avecxy_cotisations', 'avecxy_retraits', 'avecxy_frais_tenue', 'avecxy_interets_epargne', 'avecxy_benefices'] as $table) {
                $essayer("DELETE FROM $table WHERE carnet_id = ?", [$id]);
            }
            $essayer('DELETE FROM avecxy_carnets WHERE id = ?', [$id]);
        }
        foreach ($perimes('avecxy_clients') as $id) {
            $essayer('DELETE FROM avecxy_client_acces WHERE client_id = ?', [$id]);
            $essayer('DELETE FROM avecxy_clients WHERE id = ?', [$id]);
        }
        // ecritures comptables saisies a la main
        foreach ($perimes('avecxy_ecritures') as $id) {
            $essayer('DELETE FROM avecxy_ecriture_lignes WHERE ecriture_id = ?', [$id]);
            $essayer('DELETE FROM avecxy_ecritures WHERE id = ?', [$id]);
        }
        // caisse
        foreach ($perimes('avecxy_repartitions') as $id) {
            $essayer('DELETE FROM avecxy_repartition_lignes WHERE repartition_id = ?', [$id]);
            $essayer('DELETE FROM avecxy_repartitions WHERE id = ?', [$id]);
        }
        foreach ($perimes('avecxy_emprunts') as $id) {
            $essayer('DELETE FROM avecxy_emprunt_remboursements WHERE emprunt_id = ?', [$id]);
            $essayer('DELETE FROM avecxy_emprunts WHERE id = ?', [$id]);
        }
        foreach (['avecxy_transferts_fonds', 'avecxy_mouvements_fonds', 'avecxy_remises_fonds', 'avecxy_paiements_agents', 'avecxy_manquant_reglements', 'avecxy_emprunt_remboursements'] as $table) {
            $essayer("DELETE FROM $table WHERE organisation_id = ? AND supprime_le IS NOT NULL AND supprime_le < ?", [$org, $limite]);
        }
        // parametrage : seulement si plus rien ne s'en sert
        foreach (['avecxy_carnet_types', 'avecxy_utilisateurs', 'avecxy_agents'] as $table) {
            foreach ($perimes($table) as $id) {
                if ($table === 'avecxy_utilisateurs') {
                    $essayer('DELETE FROM avecxy_utilisateur_permissions WHERE utilisateur_id = ?', [$id]);
                    $essayer('DELETE FROM avecxy_utilisateur_cles WHERE utilisateur_id = ?', [$id]);
                }
                $essayer("DELETE FROM $table WHERE id = ?", [$id]);
            }
        }
        return $effacees;
    }
}

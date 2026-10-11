<?php
namespace AvecXY\Domaine;

/**
 * Controle d'une demande de credit contre les regles fixees par l'organisation.
 * Chaque regle activee donne une ligne : respectee ou non, avec la valeur attendue et la valeur constatee.
 */
final class ControleCredit
{
    /**
     * @param array $produit ligne de avecxy_credit_produits
     * @param array{epargne:int,carnets_fermes:int,carnet_actif:bool,anciennete_jours:int,retard_anterieur:bool,credits_en_cours:int,encours:int,caisse:int} $faits montants en centimes
     * @return array<int,array{regle:string,ok:bool,attendu:string,constate:string}>
     */
    public static function controler(array $produit, array $faits, int $montant): array
    {
        $lignes = [];
        $ajouter = static function (string $regle, bool $ok, string|int $attendu, string|int $constate) use (&$lignes): void {
            $lignes[] = ['regle' => $regle, 'ok' => $ok, 'attendu' => (string) $attendu, 'constate' => (string) $constate];
        };
        $argent = static fn (int $c): string => Montant::depuisCentimes($c);

        if ($produit['montant_minimum'] !== null) {
            $min = Montant::enCentimes($produit['montant_minimum']);
            $ajouter('montant_minimum', $montant >= $min, $argent($min), $argent($montant));
        }
        if ($produit['montant_maximum'] !== null) {
            $max = Montant::enCentimes($produit['montant_maximum']);
            $ajouter('montant_maximum', $montant <= $max, $argent($max), $argent($montant));
        }
        if ($produit['multiple_epargne_max'] !== null) {
            $plafond = Montant::pourcentage($faits['epargne'], (float) $produit['multiple_epargne_max'] * 100);
            $ajouter('multiple_epargne', $montant <= $plafond, $argent($plafond), $argent($montant));
        }
        if ($produit['plafond_encours_pct_caisse'] !== null) {
            // les fonds de l'organisation = argent en main + credits deja dehors
            $plafond = Montant::pourcentage($faits['caisse'] + $faits['encours'], (string) $produit['plafond_encours_pct_caisse']);
            $ajouter('plafond_encours', $faits['encours'] + $montant <= $plafond, $argent($plafond), $argent($faits['encours'] + $montant));
        }
        $simultanes = (int) $produit['credits_simultanes_max'];
        $ajouter('credits_simultanes', $faits['credits_en_cours'] < $simultanes, $simultanes, $faits['credits_en_cours']);
        if ((int) $produit['carnets_fermes_minimum'] > 0) {
            $ajouter('carnets_fermes', $faits['carnets_fermes'] >= (int) $produit['carnets_fermes_minimum'], (int) $produit['carnets_fermes_minimum'], $faits['carnets_fermes']);
        }
        if ((int) $produit['carnet_actif_requis'] === 1) {
            $ajouter('carnet_actif', $faits['carnet_actif'], '1', $faits['carnet_actif'] ? '1' : '0');
        }
        if ((int) $produit['anciennete_jours_minimum'] > 0) {
            $ajouter('anciennete', $faits['anciennete_jours'] >= (int) $produit['anciennete_jours_minimum'], (int) $produit['anciennete_jours_minimum'], $faits['anciennete_jours']);
        }
        if (($produit['garantie_pct'] ?? null) !== null && (string) $produit['garantie_pct'] !== '' && (float) $produit['garantie_pct'] > 0) {
            // garantie : une part du pret doit etre couverte par l'epargne libre (non deja bloquee par un autre credit)
            $garantie = Montant::pourcentage($montant, (string) $produit['garantie_pct']);
            $libre = $faits['epargne'] - ($faits['garanties'] ?? 0);
            $ajouter('garantie', $libre >= $garantie, $argent($garantie), $argent(max(0, $libre)));
        }
        if ((int) $produit['sans_retard_anterieur'] === 1) {
            $ajouter('sans_retard', !$faits['retard_anterieur'], '0', $faits['retard_anterieur'] ? '1' : '0');
        }
        return $lignes;
    }

    /** Toutes les regles sont-elles respectees ? */
    public static function conforme(array $lignes): bool
    {
        foreach ($lignes as $l) {
            if (!$l['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Partage d'un remboursement entre capital, interet et penalites.
     * Le capital est rembourse d'abord : un benefice n'est compte qu'une fois l'argent prete revenu.
     * @return array{interet:int,penalite:int} centimes de benefice produits par ce remboursement
     */
    public static function beneficeDuRemboursement(int $capital, int $interet, int $dejaRembourse, int $montant): array
    {
        $avant = max(0, $dejaRembourse - $capital);
        $apres = max(0, $dejaRembourse + $montant - $capital);
        $partInteret = min($interet, $apres) - min($interet, $avant);
        return ['interet' => $partInteret, 'penalite' => ($apres - $avant) - $partInteret];
    }
}

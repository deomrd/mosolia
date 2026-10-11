<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/** Reglages libres de chaque organisation (table avecxy_parametres). */
final class DepotParametres
{
    /** Valeurs utilisees quand l'organisation n'a rien regle. */
    public const DEFAUTS = [
        'clients.doublon_portee' => 'RECOLTEUR',   // RECOLTEUR | ORGANISATION | AUCUN
        'clients.doublon_action' => 'DEMANDER',    // FUSIONNER | RENOMMER | GARDER | DEMANDER
        'credits.jours_alerte' => '7',
        'credits.demande_client' => '1',           // 1 = le client peut demander un credit depuis son espace
        'emprunts.charge' => 'ORGANISATION',       // interets d'emprunts : ORGANISATION (retires de sa part) | AUCUNE (non retires)
        'emprunts.report' => '0',                  // 1 = la charge non couverte un mois est reportee sur les mois suivants
        'manquants.regle' => 'DEDUIRE',            // DEDUIRE du paiement du recolteur | SUIVRE (rapport seulement)
    ];

    private array $cache = [];

    public function __construct(private readonly BaseDeDonnees $bd)
    {
    }

    public function lire(int $organisationId, string $cle): string
    {
        if (!isset($this->cache[$organisationId])) {
            $this->cache[$organisationId] = array_column(
                $this->bd->tous('SELECT cle, valeur FROM avecxy_parametres WHERE organisation_id = ?', [$organisationId]), 'valeur', 'cle'
            );
        }
        return (string) ($this->cache[$organisationId][$cle] ?? self::DEFAUTS[$cle] ?? '');
    }

    public function ecrire(int $organisationId, string $cle, string $valeur, ?int $par = null): void
    {
        $this->bd->executer('DELETE FROM avecxy_parametres WHERE organisation_id = ? AND cle = ?', [$organisationId, $cle]);
        $this->bd->inserer('avecxy_parametres', [
            'organisation_id' => $organisationId, 'cle' => $cle, 'valeur' => $valeur, 'type' => 'TEXTE',
            'modifie_par' => $par, 'modifie_le' => $this->bd->maintenant(),
        ]);
        unset($this->cache[$organisationId]);
    }
}

<?php
namespace AvecXY\Domaine;

/**
 * Etat de l'abonnement d'une organisation a une date donnee.
 *
 *   ACTIF          tout fonctionne
 *   RAPPEL         tout fonctionne, bandeau pour l'admin (echeance proche)
 *   GRACE          echeance depassee, tout fonctionne encore, bandeau rouge
 *   LECTURE_SEULE  consultation, rapports, abonnement et support uniquement
 *   SUSPENDU       seul l'admin se connecte (abonnement et support)
 */
final class EtatAbonnement
{
    public const ACTIF = 'ACTIF';
    public const RAPPEL = 'RAPPEL';
    public const GRACE = 'GRACE';
    public const LECTURE_SEULE = 'LECTURE_SEULE';
    public const SUSPENDU = 'SUSPENDU';

    public function __construct(
        public readonly string $etat,
        public readonly ?string $fin = null,
        public readonly ?int $joursRestants = null,
        public readonly bool $essai = false,
    ) {
    }

    /**
     * @param ?array $abonnement derniere ligne de avecxy_abonnements (ou null)
     * @param string $statutOrganisation avecxy_organisations.statut
     */
    public static function calculer(?array $abonnement, string $statutOrganisation, string $aujourdhui, int $joursRappel, int $joursGrace): self
    {
        if ($statutOrganisation === 'SUSPENDUE') {
            return new self(self::SUSPENDU);
        }
        if ($abonnement === null || $abonnement['statut'] === 'ANNULE') {
            return new self(self::LECTURE_SEULE);
        }
        $fin = (string) $abonnement['fin'];
        $jours = (int) floor((strtotime($fin) - strtotime($aujourdhui)) / 86400);
        $essai = $abonnement['statut'] === 'ESSAI';

        if ($jours >= 0) {
            return new self($jours <= $joursRappel ? self::RAPPEL : self::ACTIF, $fin, $jours, $essai);
        }
        // L'essai gratuit n'a pas de delai de grace.
        if (!$essai && -$jours <= $joursGrace) {
            return new self(self::GRACE, $fin, $jours, false);
        }
        return new self(self::LECTURE_SEULE, $fin, $jours, $essai);
    }

    public function ecritureBloquee(): bool
    {
        return $this->etat === self::LECTURE_SEULE || $this->etat === self::SUSPENDU;
    }
}

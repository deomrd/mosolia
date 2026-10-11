<?php
namespace AvecXY\Application;

use AvecXY\Noyau\BaseDeDonnees;

/** Journal d'audit : qui a fait quoi, quand, sur quoi. Jamais modifie ni efface. */
final class Audit
{
    public function __construct(private readonly BaseDeDonnees $bd, private readonly string $ip = '')
    {
    }

    public function noter(
        ?int $organisationId,
        ?int $utilisateurId,
        string $action,
        string $objetType,
        ?int $objetId = null,
        ?array $avant = null,
        ?array $apres = null,
        ?string $motif = null,
    ): void {
        $json = static fn (?array $v) => $v === null ? null : json_encode(self::sansSecret($v), JSON_UNESCAPED_UNICODE);
        $this->bd->inserer('avecxy_journal_audit', [
            'organisation_id' => $organisationId,
            'utilisateur_id' => $utilisateurId,
            'action' => $action,
            'objet_type' => $objetType,
            'objet_id' => $objetId,
            'avant' => $json($avant),
            'apres' => $json($apres),
            'motif' => $motif,
            'ip' => $this->ip !== '' ? $this->ip : null,
            'cree_le' => $this->bd->maintenant(),
        ]);
    }

    /** Un mot de passe, meme hache, n'entre jamais dans le journal. */
    private static function sansSecret(array $v): array
    {
        unset($v['mot_de_passe'], $v['code_secret']);
        return $v;
    }
}

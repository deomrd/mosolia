<?php
namespace AvecXY\Application;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\TypeCarnet;
use AvecXY\Infrastructure\DepotTypesCarnet;
use AvecXY\Noyau\BaseDeDonnees;

/**
 * Types de carnet regles par l'organisation. Modifier un type ne change jamais
 * les carnets deja vendus : chaque carnet copie ses regles a l'ouverture.
 */
final class ServiceTypesCarnet
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotTypesCarnet $types,
        private readonly Audit $audit,
    ) {
    }

    /** @param array<string,string> $saisie */
    public function enregistrer(Contexte $c, int $id, array $saisie): int
    {
        $org = $c->organisationId();
        $ligne = TypeCarnet::valider($saisie, (int) $c->organisation['devise_decimales']);
        if ($this->types->nomPris($org, $ligne['nom'], $id)) {
            throw new ErreurMetier('carnets.nom_pris');
        }
        if ($ligne['actif'] === 0) {
            $ligne['par_defaut'] = 0;
        }

        return $this->bd->transaction(function () use ($c, $org, $id, $ligne): int {
            if ($id === 0) {
                // le tout premier type devient le type par defaut
                if (count($this->types->liste($org)) === 0 && $ligne['actif'] === 1) {
                    $ligne['par_defaut'] = 1;
                }
                $id = $this->types->creer($ligne + ['organisation_id' => $org, 'cree_le' => $this->bd->maintenant()]);
                $this->audit->noter($org, $c->utilisateurId(), 'type_carnet.creer', 'type_carnet', $id, null, $ligne);
            } else {
                $avant = $this->types->parId($org, $id) ?? throw new ErreurMetier('carnets.introuvable');
                $this->types->modifier($org, $id, $ligne);
                $this->audit->noter($org, $c->utilisateurId(), 'type_carnet.modifier', 'type_carnet', $id, $avant, $ligne);
            }
            if ($ligne['par_defaut'] === 1) {
                $this->types->retirerParDefaut($org, $id);
            }
            return $id;
        });
    }

    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        $org = $c->organisationId();
        $t = $this->types->parId($org, $id) ?? throw new ErreurMetier('carnets.introuvable');
        $this->bd->transaction(function () use ($c, $org, $id, $t, $motif): void {
            $this->types->modifier($org, $id, [
                'par_defaut' => 0, 'supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'type_carnet.supprimer', 'type_carnet', $id, $t, null, $motif ?: null);
        });
        return (string) $t['nom'];
    }

    public function restaurer(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $t = $this->types->parId($org, $id, true);
        if ($t === null || $t['supprime_le'] === null) {
            throw new ErreurMetier('carnets.introuvable');
        }
        if ($this->types->nomPris($org, (string) $t['nom'], $id)) {
            throw new ErreurMetier('carnets.nom_pris');
        }
        $this->bd->transaction(function () use ($c, $org, $id): void {
            $this->types->modifier($org, $id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            $this->audit->noter($org, $c->utilisateurId(), 'type_carnet.restaurer', 'type_carnet', $id);
        });
        return (string) $t['nom'];
    }
}

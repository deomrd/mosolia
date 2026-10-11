<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CatalogueDroits;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotProfils;
use AvecXY\Noyau\BaseDeDonnees;

/** Creation et reglage des profils par l'administration de l'organisation. */
final class ServiceProfils
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotProfils $profils,
        private readonly Audit $audit,
    ) {
    }

    /** @param string[] $droits codes coches */
    public function enregistrer(Contexte $c, int $id, string $nom, bool $estRecolteur, array $droits): int
    {
        $nom = Texte::nom($nom);
        if ($nom === '') {
            throw new ErreurMetier('profils.nom_obligatoire');
        }
        $droits = array_values(array_intersect(CatalogueDroits::codes(), $droits));
        $org = $c->organisationId();

        return $this->bd->transaction(function () use ($c, $org, $id, $nom, $estRecolteur, $droits): int {
            if ($id === 0) {
                $code = $this->codeLibre($org, Texte::code($nom));
                $id = $this->profils->creer([
                    'organisation_id' => $org, 'code' => $code, 'nom' => $nom,
                    'est_admin' => 0, 'est_agent' => $estRecolteur ? 1 : 0, 'systeme' => 0,
                ]);
                $this->profils->definirDroits($id, $droits);
                $this->audit->noter($org, $c->utilisateurId(), 'profil.creer', 'profil', $id, null, ['nom' => $nom, 'droits' => $droits]);
                return $id;
            }

            $profil = $this->profils->parId($org, $id) ?? throw new ErreurMetier('profils.introuvable');
            $avant = ['nom' => $profil['nom'], 'droits' => $this->profils->droits($id)];
            if ((int) $profil['est_admin'] === 1) {
                // Le profil administrateur garde tous les droits : seul son nom se change.
                $this->profils->modifier($id, ['nom' => $nom]);
                $droits = $avant['droits'];
            } else {
                $this->profils->modifier($id, ['nom' => $nom, 'est_agent' => $estRecolteur ? 1 : 0]);
                $this->profils->definirDroits($id, $droits);
            }
            $this->audit->noter($org, $c->utilisateurId(), 'profil.modifier', 'profil', $id, $avant, ['nom' => $nom, 'droits' => $droits]);
            return $id;
        });
    }

    public function supprimer(Contexte $c, int $id): string
    {
        $profil = $this->profils->parId($c->organisationId(), $id) ?? throw new ErreurMetier('profils.introuvable');
        if ((int) $profil['est_admin'] === 1 || (int) $profil['systeme'] === 1) {
            throw new ErreurMetier('profils.protege');
        }
        if ($this->profils->compterUtilisateurs($id) > 0) {
            throw new ErreurMetier('profils.utilise');
        }
        $this->bd->transaction(function () use ($c, $id, $profil): void {
            $this->profils->supprimer($id);
            $this->audit->noter($c->organisationId(), $c->utilisateurId(), 'profil.supprimer', 'profil', $id, ['nom' => $profil['nom']]);
        });
        return (string) $profil['nom'];
    }

    private function codeLibre(int $organisationId, string $base): string
    {
        $base = $base !== '' ? $base : 'PROFIL';
        $code = $base;
        for ($n = 2; $this->profils->codePris($organisationId, $code); $n++) {
            $code = substr($base, 0, 36) . '_' . $n;
        }
        return $code;
    }
}

<?php
namespace AvecXY\Application;

use AvecXY\Domaine\CodeRecolteur;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Domaine\Texte;
use AvecXY\Infrastructure\DepotRecolteurs;
use AvecXY\Infrastructure\DepotUtilisateurs;
use AvecXY\Noyau\BaseDeDonnees;

/** Fiches des recolteurs. Leur identifiant de 4 caracteres est toujours genere par le systeme. */
final class ServiceRecolteurs
{
    public function __construct(
        private readonly BaseDeDonnees $bd,
        private readonly DepotRecolteurs $recolteurs,
        private readonly DepotUtilisateurs $utilisateurs,
        private readonly Audit $audit,
    ) {
    }

    /** @param array{nom:string,telephone:string,adresse:string,statut:string,utilisateur_id:int} $d */
    public function enregistrer(Contexte $c, int $id, array $d): array
    {
        $org = $c->organisationId();
        $nom = Texte::nom($d['nom']);
        if ($nom === '') {
            throw new ErreurMetier('recolteurs.nom_obligatoire');
        }
        $ligne = [
            'nom' => $nom,
            'telephone' => trim($d['telephone']) !== '' ? Texte::identifiant($d['telephone']) : null,
            'adresse' => trim($d['adresse']) !== '' ? Texte::nom($d['adresse']) : null,
            'statut' => $d['statut'] === 'INACTIF' ? 'INACTIF' : 'ACTIF',
        ];
        if (array_key_exists('agence_id', $d)) {
            $agence = (int) $d['agence_id'];
            if ($agence > 0 && $this->bd->valeur('SELECT COUNT(*) FROM avecxy_agences WHERE organisation_id = ? AND id = ? AND supprime_le IS NULL', [$org, $agence]) == 0) {
                throw new ErreurMetier('agences.introuvable');
            }
            // un responsable d'agence ne rattache qu'a son agence
            $ligne['agence_id'] = $c->agenceRestreinte() ?? ($agence > 0 ? $agence : null);
        } elseif ($c->agenceRestreinte() !== null && $id === 0) {
            $ligne['agence_id'] = $c->agenceRestreinte();
        }
        $utilisateurId = (int) $d['utilisateur_id'];
        if ($utilisateurId > 0) {
            $u = $this->utilisateurs->parId($org, $utilisateurId) ?? throw new ErreurMetier('utilisateurs.introuvable');
            if ($u['agent_id'] !== null && (int) $u['agent_id'] !== $id) {
                throw new ErreurMetier('recolteurs.compte_deja_rattache');
            }
        }

        return $this->bd->transaction(function () use ($c, $org, $id, $ligne, $utilisateurId): array {
            if ($id === 0) {
                $this->verifierLimiteForfait($c);
                $ligne += ['organisation_id' => $org, 'code' => $this->codeLibre(), 'cree_le' => $this->bd->maintenant()];
                $id = $this->recolteurs->creer($ligne);
                $this->recolteurs->rattacher($org, $id, $utilisateurId);
                $this->audit->noter($org, $c->utilisateurId(), 'recolteur.creer', 'recolteur', $id, null, $ligne + ['utilisateur_id' => $utilisateurId]);
                return ['id' => $id, 'code' => $ligne['code'], 'nouveau' => true];
            }
            $avant = $this->recolteurs->parId($org, $id) ?? throw new ErreurMetier('recolteurs.introuvable');
            $this->recolteurs->modifier($org, $id, $ligne);
            $this->recolteurs->rattacher($org, $id, $utilisateurId);
            $this->audit->noter($org, $c->utilisateurId(), 'recolteur.modifier', 'recolteur', $id, $avant, $ligne + ['utilisateur_id' => $utilisateurId]);
            return ['id' => $id, 'code' => $avant['code'], 'nouveau' => false];
        });
    }

    public function supprimer(Contexte $c, int $id, string $motif): string
    {
        $org = $c->organisationId();
        $r = $this->recolteurs->parId($org, $id) ?? throw new ErreurMetier('recolteurs.introuvable');
        if ($this->recolteurs->compterClients($id) > 0) {
            throw new ErreurMetier('recolteurs.a_des_clients');
        }
        $this->bd->transaction(function () use ($c, $org, $id, $r, $motif): void {
            $this->recolteurs->rattacher($org, $id, 0);
            $this->recolteurs->modifier($org, $id, [
                'supprime_le' => $this->bd->maintenant(), 'supprime_par' => $c->utilisateurId(),
                'motif_suppression' => $motif !== '' ? mb_substr($motif, 0, 255) : null,
            ]);
            $this->audit->noter($org, $c->utilisateurId(), 'recolteur.supprimer', 'recolteur', $id, $r, null, $motif ?: null);
        });
        return $r['code'] . ' ' . $r['nom'];
    }

    public function restaurer(Contexte $c, int $id): string
    {
        $org = $c->organisationId();
        $r = $this->recolteurs->parId($org, $id, true);
        if ($r === null || $r['supprime_le'] === null) {
            throw new ErreurMetier('recolteurs.introuvable');
        }
        $this->verifierLimiteForfait($c);
        $this->bd->transaction(function () use ($c, $org, $id): void {
            $this->recolteurs->modifier($org, $id, ['supprime_le' => null, 'supprime_par' => null, 'motif_suppression' => null]);
            $this->audit->noter($org, $c->utilisateurId(), 'recolteur.restaurer', 'recolteur', $id);
        });
        return $r['code'] . ' ' . $r['nom'];
    }

    /** Tire des identifiants au hasard jusqu'a en trouver un qui n'existe nulle part. */
    private function codeLibre(): string
    {
        for ($essai = 0; $essai < 50; $essai++) {
            $code = CodeRecolteur::generer();
            if (!$this->recolteurs->codePris($code)) {
                return $code;
            }
        }
        throw new \RuntimeException('Impossible de generer un identifiant de recolteur libre.');
    }

    private function verifierLimiteForfait(Contexte $c): void
    {
        $max = $c->forfait['max_agents'] ?? null;
        if ($max !== null && $this->recolteurs->compter($c->organisationId()) >= (int) $max) {
            throw new ErreurMetier('recolteurs.limite_forfait', ['n' => (int) $max]);
        }
    }
}

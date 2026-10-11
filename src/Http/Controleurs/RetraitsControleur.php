<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

final class RetraitsControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $f = [
            'recolteur' => (int) ($g['recolteur'] ?? 0), 'q' => mb_strtoupper(trim((string) ($g['q'] ?? ''))),
            'du' => Dates::ou((string) ($g['du'] ?? ''), date('Y-m-01')), 'au' => Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d')),
        ];
        $page = max(1, (int) ($g['page'] ?? 1));
        $r = $this->noyau->operations()->retraits($c->organisationId(), $c->recolteurRestreint(), $f, $page);
        return $this->page('retraits/index', [
            'titre' => t('menu.retraits'), 'retraits' => $r['lignes'], 'total' => $r['total'], 'somme' => $r['somme'], 'page' => $page, 'f' => $f,
            'recolteurs' => $c->recolteurRestreint() === null ? $this->noyau->recolteurs()->liste($c->organisationId()) : [],
        ]);
    }

    public function supprimer(string $id): Reponse
    {
        $r = $this->noyau->operations()->retrait($this->contexte()->organisationId(), (int) $id);
        try {
            $this->noyau->serviceRetraits()->supprimer($this->contexte(), (int) $id, $this->requete->texte('motif'));
            $this->succes('retraits.supprime');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger($r ? '/carnets/' . $r['carnet_id'] : '/retraits');
    }
}

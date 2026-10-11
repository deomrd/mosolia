<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\Dates;
use AvecXY\Domaine\Montant;
use AvecXY\Http\Controleur;
use AvecXY\Infrastructure\DepotRapports;
use AvecXY\Noyau\Reponse;

/** Rapports detailles, jour par jour, avec filtres et export CSV. */
final class RapportsControleur extends Controleur
{
    public function index(): Reponse
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $disponibles = array_keys(array_filter(DepotRapports::RAPPORTS, static fn ($droit) => $droit === null || $c->peut($droit)));
        if ($c->recolteurRestreint() !== null) {
            // un portefeuille restreint ne lit pas le journal de toute l'organisation
            $disponibles = array_values(array_diff($disponibles, ['audit']));
        }
        if (!$c->module('credit')) {
            $disponibles = array_values(array_diff($disponibles, ['credits', 'remboursements']));
        }
        $rapport = in_array($g['rapport'] ?? '', $disponibles, true) ? $g['rapport'] : '';
        $du = Dates::ou((string) ($g['du'] ?? ''), date('Y-m-d'));
        $au = Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $restreint = $c->recolteurRestreint();
        $agent = $restreint ?? ((int) ($g['recolteur'] ?? 0) ?: null);
        $resultat = null;
        $totaux = [];
        if ($rapport !== '') {
            $resultat = $this->noyau->rapports()->executer($rapport, $c->organisationId(), $du, $au, $agent);
            foreach ($resultat['colonnes'] as $col => $type) {
                if ($type === 'argent') {
                    $totaux[$col] = array_sum(array_map(static fn ($l) => Montant::enCentimes($l[$col]), $resultat['lignes']));
                }
            }
            if (($g['export'] ?? '') === 'csv') {
                return $this->csv($rapport, $du, $au, $resultat, $totaux);
            }
        }
        return $this->page('rapports/index', [
            'titre' => t('menu.rapports'), 'disponibles' => $disponibles, 'rapport' => $rapport, 'du' => $du, 'au' => $au, 'agent' => (int) $agent,
            'recolteurs' => $restreint === null ? $this->noyau->recolteurs()->liste($c->organisationId()) : [],
            'resultat' => $resultat, 'totaux' => $totaux, 'plein' => $resultat !== null && count($resultat['lignes']) >= DepotRapports::MAX_LIGNES,
        ]);
    }

    /** Valeur d'une cellule telle qu'on la lit (ecran) ou telle qu'un tableur la calcule (csv). */
    public static function cellule(string $type, mixed $v, bool $csv = false): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($type) {
            'argent' => $csv ? str_replace('.', ',', Montant::pourChamp((string) $v)) : argent($v),
            'date' => jour((string) $v),
            'flux' => t('caisse.type.' . strtolower((string) $v)),
            'source' => t('partage.source.' . strtolower((string) $v)),
            'statut' => t('statut.' . strtolower((string) $v)),
            default => (string) $v,
        };
    }

    private function csv(string $rapport, string $du, string $au, array $resultat, array $totaux): Reponse
    {
        $ligne = static function (array $cellules): string {
            return implode(';', array_map(static function (string $v): string {
                // une cellule qui commence par = + - @ serait executee comme formule par le tableur
                if ($v !== '' && strpbrk($v[0], '=+-@') !== false && !is_numeric(str_replace(',', '.', $v))) {
                    $v = "'" . $v;
                }
                return '"' . str_replace('"', '""', $v) . '"';
            }, $cellules)) . "\r\n";
        };
        $colonnes = $resultat['colonnes'];
        $sortie = "\u{FEFF}" . $ligne(array_map(static fn ($col) => t('rapports.colonne.' . $col), array_keys($colonnes)));
        foreach ($resultat['lignes'] as $l) {
            $sortie .= $ligne(array_map(static fn ($col) => self::cellule($colonnes[$col], $l[$col], true), array_keys($colonnes)));
        }
        if ($totaux) {
            $pied = [];
            foreach (array_keys($colonnes) as $i => $col) {
                $pied[] = isset($totaux[$col]) ? self::cellule('argent', $totaux[$col] / 100, true) : ($i === 0 ? t('rapports.total') : '');
            }
            $sortie .= $ligne($pied);
        }
        return Reponse::fichier($rapport . '_' . $du . '_' . $au . '.csv', $sortie);
    }

    /** Indicateurs de gestion d'une microfinance (portefeuille a risque, encours, epargne, interets). */
    public function gestion(): Reponse
    {
        [$du, $au, $agence, $agences] = $this->filtresGestion();
        $i = $this->noyau->serviceIndicateurs()->calculer($this->contexte()->organisationId(), $du, $au, $agence);
        return $this->page('rapports/gestion', ['titre' => t('menu.rapports_gestion'), 'i' => $i, 'f' => ['du' => $du, 'au' => $au, 'agence' => $agence, 'agences' => $agences]]);
    }

    public function exporterGestion(): Reponse
    {
        [$du, $au, $agence] = $this->filtresGestion();
        $s = $this->noyau->serviceIndicateurs();
        $contenu = $s->classeur($s->calculer($this->contexte()->organisationId(), $du, $au, $agence), (string) $this->contexte()->organisation['nom'], $du, $au);
        return Reponse::fichier('indicateurs_' . $du . '_' . $au . '.xlsx', $contenu, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @return array{0:string,1:string,2:?int,3:array} */
    private function filtresGestion(): array
    {
        $c = $this->contexte();
        $g = $this->requete->get;
        $du = \AvecXY\Domaine\Dates::ou((string) ($g['du'] ?? ''), date('Y-m-01'));
        $au = \AvecXY\Domaine\Dates::ou((string) ($g['au'] ?? ''), date('Y-m-d'));
        if ($au < $du) {
            $au = $du;
        }
        $agences = $c->agenceRestreinte() === null ? $this->noyau->serviceAgences()->liste($c->organisationId()) : [];
        $choix = (string) ($g['agence'] ?? '');
        return [$du, $au, $c->agenceRestreinte() ?? ($choix === '' || !$agences ? null : max(0, (int) $choix)), $agences];
    }
}

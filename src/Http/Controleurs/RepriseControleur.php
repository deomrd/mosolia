<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Domaine\ErreurMetier;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/**
 * Reprise de l'existant : modele Excel a telecharger, envoi du fichier rempli, verification, puis import.
 * Le fichier envoye est garde hors du dossier public le temps de la verification.
 */
final class RepriseControleur extends Controleur
{
    private function dossier(): string
    {
        $d = $this->noyau->racine . '/stockage/reprises';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        return $d;
    }

    /** Fichier en cours de verification pour cette session, ou null. */
    private function enCours(): ?array
    {
        $e = $this->noyau->session->lire('reprise');
        if (!is_array($e) || (int) $e['org'] !== $this->contexte()->organisationId() || !preg_match('/^[a-f0-9]{32}$/', (string) $e['jeton'])) {
            return null;
        }
        $e['chemin'] = $this->dossier() . '/' . $e['org'] . '_' . $e['jeton'] . '.xlsx';
        return is_file($e['chemin']) ? $e : null;
    }

    private function oublier(): void
    {
        $e = $this->enCours();
        if ($e !== null) {
            @unlink($e['chemin']);
        }
        $this->noyau->session->oublier('reprise');
    }

    public function index(): Reponse
    {
        $c = $this->contexte();
        $analyse = null;
        $e = $this->enCours();
        if ($e !== null) {
            try {
                $analyse = $this->noyau->serviceReprise()->analyser($c, $e['chemin'], $e['date'], $e['caisse']);
            } catch (ErreurMetier $ex) {
                $this->erreur($ex);
                $this->oublier();
            }
        }
        return $this->page('parametres/reprise', [
            'titre' => t('menu.reprise'), 'analyse' => $analyse, 'fichier' => $e['nom'] ?? '',
            'reprises' => $this->noyau->serviceReprise()->liste($c->organisationId()),
            'demarrage' => $c->organisation['demarrage'] ?? null,
            'creditDansForfait' => $c->module('credit'),
            'espaceClient' => $this->noyau->servicePortail()->actifPour($c),
        ]);
    }

    public function modele(): Reponse
    {
        $c = $this->contexte();
        $langue = $this->noyau->traducteur->langue;
        return Reponse::fichier(
            ($langue === 'en' ? 'avecxy-opening-data' : 'avecxy-reprise-existant') . '.xlsx',
            $this->noyau->serviceReprise()->modele($c, $langue),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function analyser(): Reponse
    {
        $c = $this->contexte();
        $f = $_FILES['fichier'] ?? null;
        try {
            if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
                throw new ErreurMetier(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'reprise.fichier_trop_gros' : 'reprise.fichier_manquant');
            }
            if ((int) $f['size'] > 8_000_000) {
                throw new ErreurMetier('reprise.fichier_trop_gros');
            }
            if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
                throw new ErreurMetier('reprise.format_xlsx');
            }
            $this->oublier();
            $jeton = bin2hex(random_bytes(16));
            $chemin = $this->dossier() . '/' . $c->organisationId() . '_' . $jeton . '.xlsx';
            if (!move_uploaded_file((string) $f['tmp_name'], $chemin)) {
                throw new ErreurMetier('reprise.fichier_manquant');
            }
            $e = ['org' => $c->organisationId(), 'jeton' => $jeton, 'nom' => mb_substr(basename((string) $f['name']), 0, 120),
                'date' => $this->requete->texte('date', date('Y-m-d')), 'caisse' => $this->requete->texte('caisse')];
            // premiere lecture tout de suite : un fichier illisible n'est pas garde
            try {
                $this->noyau->serviceReprise()->analyser($c, $chemin, $e['date'], $e['caisse']);
            } catch (ErreurMetier $ex) {
                @unlink($chemin);
                throw $ex;
            }
            $this->noyau->session->ecrire('reprise', $e);
        } catch (ErreurMetier $ex) {
            $this->erreur($ex);
        }
        return $this->rediriger('/parametres/reprise');
    }

    public function importer(): Reponse
    {
        $c = $this->contexte();
        $e = $this->enCours();
        if ($e === null) {
            $this->noyau->session->message('erreur', t('reprise.fichier_manquant'));
            return $this->rediriger('/parametres/reprise');
        }
        try {
            // le fichier est relu ici : on n'importe jamais des chiffres renvoyes par le navigateur
            $analyse = $this->noyau->serviceReprise()->analyser($c, $e['chemin'], $e['date'], $e['caisse']);
            $this->noyau->serviceReprise()->importer($c, $analyse);
            $this->oublier();
            $this->succes('reprise.importee', ['clients' => $analyse['totaux']['clients'], 'carnets' => $analyse['totaux']['carnets'], 'credits' => $analyse['totaux']['credits']]);
        } catch (ErreurMetier $ex) {
            $this->erreur($ex);
        }
        return $this->rediriger('/parametres/reprise');
    }

    public function abandonner(): Reponse
    {
        $this->oublier();
        return $this->rediriger('/parametres/reprise');
    }

    public function partirDeVide(): Reponse
    {
        $this->noyau->serviceReprise()->partirDeVide($this->contexte());
        $this->succes('reprise.vide_choisi');
        return $this->rediriger('/');
    }

    public function annuler(string $id): Reponse
    {
        try {
            $this->noyau->serviceReprise()->annuler($this->contexte(), (int) $id);
            $this->succes('reprise.annulee');
        } catch (ErreurMetier $e) {
            $this->erreur($e);
        }
        return $this->rediriger('/parametres/reprise');
    }
}

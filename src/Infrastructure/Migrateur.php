<?php
namespace AvecXY\Infrastructure;

use AvecXY\Noyau\BaseDeDonnees;

/**
 * Applique, dans l'ordre, les fichiers de database/migrations/ qui ne l'ont
 * pas encore ete. Un fichier .sql contient des instructions SQL ; un fichier
 * .php renvoie une fonction qui recoit la base. L'application ne modifie
 * jamais la structure de la base en dehors de ce mecanisme.
 */
final class Migrateur
{
    public function __construct(private readonly BaseDeDonnees $bd, private readonly string $dossier)
    {
    }

    /** @return string[] noms des fichiers appliques pendant cet appel */
    public function migrer(): array
    {
        $this->bd->pdo->exec(
            'CREATE TABLE IF NOT EXISTS avecxy_migrations (
                fichier VARCHAR(190) NOT NULL PRIMARY KEY,
                applique_le DATETIME NOT NULL
            )'
        );
        $faits = array_column($this->bd->tous('SELECT fichier FROM avecxy_migrations'), 'fichier');
        $fichiers = array_merge(glob($this->dossier . '/*.sql') ?: [], glob($this->dossier . '/*.php') ?: []);
        usort($fichiers, static fn ($a, $b) => strcmp(basename($a), basename($b)));

        $appliques = [];
        foreach ($fichiers as $chemin) {
            $nom = basename($chemin);
            if (in_array($nom, $faits, true)) {
                continue;
            }
            if (str_ends_with($nom, '.sql')) {
                foreach (self::instructions((string) file_get_contents($chemin)) as $sql) {
                    $this->bd->pdo->exec($sql);
                }
            } else {
                $fonction = require $chemin;
                $fonction($this->bd);
            }
            $this->bd->inserer('avecxy_migrations', ['fichier' => $nom, 'applique_le' => $this->bd->maintenant()]);
            $appliques[] = $nom;
        }
        return $appliques;
    }

    /** Decoupe un fichier SQL en instructions (commentaires "--" retires). */
    public static function instructions(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/\s--\s.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(array_map('trim', explode(";\n", $sql . "\n")), static fn ($s) => $s !== ''));
    }
}

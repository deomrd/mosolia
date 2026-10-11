<?php
namespace AvecXY\Noyau;

use PDO;
use Throwable;

/**
 * Acces a la base. Toutes les requetes sont preparees ; aucune valeur
 * venant de l'utilisateur n'est jamais collee dans le SQL.
 */
final class BaseDeDonnees
{
    public readonly string $pilote;

    public function __construct(public readonly PDO $pdo)
    {
        $this->pilote = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * A ajouter a la fin d'un SELECT fait dans une transaction pour reserver la ligne :
     * deux operations simultanees sur le meme carnet ou credit passent alors l'une apres l'autre.
     */
    public function verrou(): string
    {
        return $this->pilote === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function depuisConfig(array $c): self
    {
        if (($c['pilote'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . $c['fichier']);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['hote'], $c['port'] ?? 3306, $c['base']);
            $pdo = new PDO($dsn, $c['utilisateur'], $c['mot_de_passe']);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return new self($pdo);
    }

    public function un(string $sql, array $params = []): ?array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s->fetch() ?: null;
    }

    public function tous(string $sql, array $params = []): array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    public function valeur(string $sql, array $params = []): mixed
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        $v = $s->fetchColumn();
        return $v === false ? null : $v;
    }

    public function executer(string $sql, array $params = []): int
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s->rowCount();
    }

    /** Insere une ligne et renvoie son identifiant. */
    public function inserer(string $table, array $donnees): int
    {
        $colonnes = array_keys($donnees);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $colonnes) . ') VALUES (' . implode(',', array_fill(0, count($colonnes), '?')) . ')';
        $this->executer($sql, array_values($donnees));
        return (int) $this->pdo->lastInsertId();
    }

    /** Met a jour les lignes qui correspondent a toutes les conditions. */
    public function modifier(string $table, array $donnees, array $conditions): int
    {
        $set = implode(',', array_map(static fn ($c) => $c . '=?', array_keys($donnees)));
        $ou = implode(' AND ', array_map(static fn ($c) => $c . '=?', array_keys($conditions)));
        return $this->executer("UPDATE $table SET $set WHERE $ou", [...array_values($donnees), ...array_values($conditions)]);
    }

    /** Execute la fonction dans une transaction : tout passe, ou rien. */
    public function transaction(callable $travail): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $travail($this);
        }
        $this->pdo->beginTransaction();
        try {
            $resultat = $travail($this);
            $this->pdo->commit();
            return $resultat;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Date et heure courantes, calculees par PHP (meme fuseau partout). */
    public function maintenant(): string
    {
        return date('Y-m-d H:i:s');
    }
}

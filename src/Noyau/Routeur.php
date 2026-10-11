<?php
namespace AvecXY\Noyau;

/**
 * Table des routes. Une route = methode + chemin + controleur + options.
 * Options :
 *   public   => true : accessible sans connexion
 *   droit    => 'profils.gerer' : droit exige
 *   toujours => true : reste autorise quand l'abonnement est en lecture seule
 */
final class Routeur
{
    /** @var array<int, array{0:string,1:string,2:array,3:array}> */
    private array $routes = [];

    public function ajouter(string $methode, string $chemin, array $action, array $options = []): void
    {
        $this->routes[] = [$methode, $chemin, $action, $options];
    }

    /** @return array{action:array,params:array,options:array}|null */
    public function trouver(string $methode, string $chemin): ?array
    {
        foreach ($this->routes as [$m, $modele, $action, $options]) {
            if ($m !== $methode) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $modele) . '$#';
            if (preg_match($regex, $chemin, $t)) {
                $params = array_filter($t, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['action' => $action, 'params' => $params, 'options' => $options];
            }
        }
        return null;
    }
}

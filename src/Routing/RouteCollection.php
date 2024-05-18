<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Countable;
use ArrayIterator;
use IteratorAggregate;

use Two\Http\Request;
use Two\Http\Response;
use Two\Support\Arr;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;


class RouteCollection implements Countable, IteratorAggregate
{
    /**
     * Un tableau des itinéraires saisis par méthode.
     *
     * @var array
     */
    protected $routes = array(
        'GET'     => array(),
        'POST'    => array(),
        'PUT'     => array(),
        'DELETE'  => array(),
        'PATCH'   => array(),
        'HEAD'    => array(),
        'OPTIONS' => array(),
    );

    /**
     * Un tableau aplati de tous les itinéraires.
     *
     * @var array
     */
    protected $allRoutes = array();

    /**
     * Une table de recherche des itinéraires par leurs noms.
     *
     * @var array
     */
    protected $nameList = array();

    /**
     * Une table de recherche des itinéraires par action du contrôleur.
     *
     * @var array
     */
    protected $actionList = array();

    /**
     * Ajoutez une instance de Route à la collection.
     *
     * @param  \Two\Routing\Route  $route
     * @return \Two\Routing\Route
     */
    public function add(Route $route)
    {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Ajoutez l'itinéraire donné aux tableaux d'itinéraires.
     *
     * @param  \Two\Routing\Route  $route
     * @return void
     */
    protected function addToCollections($route)
    {
        $domainAndUri = $route->domain() .$route->getUri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
        }

        $key = $method .$domainAndUri;

        $this->allRoutes[$key] = $route;
    }

    /**
     * Ajoutez l'itinéraire à toutes les tables de recherche si nécessaire.
     *
     * @param  \Two\Routing\Route  $route
     * @return void
     */
    protected function addLookups($route)
    {
        // Si l'itinéraire a un nom, nous l'ajouterons à la table de recherche de nom afin que nous puissions
        // pourra rapidement trouver n'importe quelle route associée à un nom et ne pas avoir
        // pour parcourir chaque itinéraire à chaque fois que nous devons effectuer une recherche.
        $action = $route->getAction();

        if (isset($action['as'])) {
            $name = $action['as'];

            $this->nameList[$name] = $route;
        }

        // Lorsque la route est acheminée vers un contrôleur, nous stockerons également l'action qui
        // est utilisé par la route. Cela nous permettra d'inverser la route vers les contrôleurs pendant que
        // traite une requête et génère facilement des URL vers les contrôleurs donnés.
        if (isset($action['controller'])) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Ajoutez une route au dictionnaire d'actions du contrôleur.
     *
     * @param  array  $action
     * @param  \Two\Routing\Route  $route
     * @return void
     */
    protected function addToActionList($action, $route)
    {
        $controller = $action['controller'];

        if (! isset($this->actionList[$controller])) {
            $this->actionList[$controller] = $route;
        }
    }

    /**
     * Trouver le premier itinéraire correspondant à une requête donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function match(Request $request)
    {
        $routes = $this->get($request->getMethod());

        // Tout d’abord, nous verrons si nous pouvons trouver un itinéraire correspondant à cette demande actuelle.
        // méthode. Si nous le pouvons, super, nous pouvons simplement le retourner pour qu'il puisse être appelé
        // par le consommateur. Sinon, nous vérifierons les itinéraires avec un autre verbe.

        if (! is_null($route = $this->findRoute($routes, $request))) {
            return $route->bind($request);
        }

        // Si aucun itinéraire n'a été trouvé, nous vérifierons si un itinéraire correspondant est spécifié sur
        // un autre verbe HTTP. Si c'est le cas, nous devrons lancer un MethodNotAllowed et
        // informe l'agent utilisateur du verbe HTTP qu'il doit utiliser pour cette route.

        if (! empty($others = $this->checkForAlternateVerbs($request))) {
            return $this->getOtherMethodsRoute($request, $others);
        }

        throw new NotFoundHttpException;
    }

    /**
     * Trouver le premier itinéraire correspondant à une requête donnée.
     *
     * @param  array  $routes
     * @param  \Two\Http\Request  $request
     * @return \Two\Routing\Route|null
     */
    protected function findRoute($routes, $request)
    {
        if (! is_null($route = $this->fastCheck($routes, $request))) {
            return $route;
        }

        $fallbacks = array();

        foreach ($routes as $key => $route) {
            if (! $route->isFallback()) {
                continue;
            }

            $fallbacks[$key] = $route;

            unset($routes[$key]);
        }

        return $this->check(
            array_merge($routes, $fallbacks), $request
        );
    }

    /**
     * Déterminez si des routes correspondent à un autre verbe HTTP.
     *
     * @param  \Two\Http\Request  $request
     * @return array
     */
    protected function checkForAlternateVerbs($request)
    {
        $methods = array_diff(
            Router::$verbs, (array) $request->getMethod()
        );

        // Ici, nous allons parcourir tous les verbes à l'exception du verbe de requête actuel et
        // vérifie si des routes y répondent. S'ils le font, nous leur retournerons un
        // réponse d'erreur appropriée avec les en-têtes corrects sur la chaîne de réponse.

        return array_filter($methods, function ($method) use ($request)
        {
            $route = $this->check($this->get($method), $request, false);

            return ! is_null($route);
        });
    }

    /**
     * Obtenez un itinéraire (si nécessaire) qui répond lorsque d'autres méthodes disponibles sont présentes.
     *
     * @param  \Two\Http\Request  $request
     * @param  array  $others
     * @return \Two\Routing\Route
     *
     * @throws \Symfony\Component\Routing\Exception\MethodNotAllowedHttpException
     */
    protected function getOtherMethodsRoute($request, array $others)
    {
        if ($request->method() !== 'OPTIONS') {
            throw new MethodNotAllowedHttpException($others);
        }

        $route = new Route('OPTIONS', $request->path(), function () use ($others)
        {
            return new Response('', 200, array('Allow' => implode(',', $others)));
        });

        return $route->bind($request);
    }

    /**
     * Déterminez si une route du tableau correspond à la requête.
     *
     * @param  array  $routes
     * @param  \Two\http\Request  $request
     * @param  bool  $includingMethod
     * @return \Two\Routing\Route|null
     */
    protected function check(array $routes, $request, $includingMethod = true)
    {
        return Arr::first($routes, function ($key, $route) use ($request, $includingMethod)
        {
            return $route->matches($request, $includingMethod);
        });
    }

    /**
     * Déterminez si une route dans le tableau correspond entièrement à la demande – de la manière la plus rapide.
     *
     * @param  array  $routes
     * @param  \Two\http\Request  $request
     * @return \Two\Routing\Route|null
     */
    protected function fastCheck(array $routes, $request)
    {
        if (($path = $request->path()) != '/') {
            $path = '/' . trim($path, '/');
        }

        $keys = array(
            $request->getHost() .$path, $path
        );

        foreach ($keys as $key) {
            if (! is_null($route = Arr::get($routes, $key)) && $route->matches($request)) {
                return $route;
            }
        }
    }

    /**
     * Obtenez tous les itinéraires de la collection.
     *
     * @param  string|null  $method
     * @return array
     */
    protected function get($method = null)
    {
        if (is_null($method)) {
            return $this->getRoutes();
        }

        return Arr::get($this->routes, $method, array());
    }

    /**
     * Déterminez si la collection de routes contient une route nommée donnée.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasNamedRoute($name)
    {
        return ! is_null($this->getByName($name));
    }

    /**
     * Obtenez une instance de route par son nom.
     *
     * @param  string  $name
     * @return \Two\Routing\Route|null
     */
    public function getByName($name)
    {
        if (isset($this->nameList[$name])) {
            return $this->nameList[$name];
        }
    }

    /**
     * Obtenez une instance de route par son action de contrôleur.
     *
     * @param  string  $action
     * @return \Two\Routing\Route|null
     */
    public function getByAction($action)
    {
        if (isset($this->actionList[$action])) {
            return $this->actionList[$action];
        }
    }

    /**
     * Obtenez tous les itinéraires de la collection.
     *
     * @return array
     */
    public function getRoutes()
    {
        return array_values($this->allRoutes);
    }

    /**
     * Obtenez un itérateur pour les éléments.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getRoutes());
    }

    /**
     * Comptez le nombre d'éléments dans la collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->getRoutes());
    }

}

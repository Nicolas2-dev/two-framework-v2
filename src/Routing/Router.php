<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Closure;
use BadMethodCallException;

use Two\Http\RedirectResponse;
use Two\Http\Request;
use Two\Http\Response;
use Two\Events\Dispatcher;
use Two\Container\Container;
use Two\Routing\Pipeline;
use Two\Support\Arr;
use Two\Support\Str;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class Router
{
    /**
     * Instance du répartiteur d’événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * L'instance de routeur utilisée par la route.
     *
     * @var \Two\Routing\Router  $router
     */
    protected $router;

    /**
     * L'instance de collection de routes.
     *
     * @var \Two\Routing\RouteCollection
     */
    protected $routes;

    /**
     * Instance de route actuellement distribuée.
     *
     * @var \Two\Routing\Route
     */
    protected $current;

    /**
     * La demande est en cours d'envoi.
     *
     * @var \Two\Http\Request
     */
    protected $currentRequest;

    /**
     * Toutes les touches raccourcies pour les middlewares.
     *
     * @var array
     */
    protected $middleware = array();

    /**
     * Tous les groupes middleware.
     *
     * @var array
     */
    protected $middlewareGroups = array();

    /**
     * Les classeurs de valeurs d'itinéraire enregistrés.
     *
     * @var array
     */
    protected $binders = array();

    /**
     * Les modèles de paramètres disponibles globalement.
     *
     * @var array
     */
    protected $patterns = array();

    /**
     * La pile d'attributs du groupe de routes.
     *
     * @var array
     */
    protected $groupStack = array();

    /**
     * Tous les verbes pris en charge par le routeur.
     *
     * @var array
     */
    public static $verbs = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');

    /**
     * Instance du registraire de ressources.
     *
     * @var \Two\Routing\ResourceRegistrar
     */
    protected $registrar;

    /**
     * Les macros de chaîne enregistrées.
     *
     * @var array
     */
    protected $macros = array();


    /**
     * Créez une nouvelle instance de routeur.
     *
     * @param  \Two\Events\Dispatcher  $events
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function __construct(Dispatcher $events, Container $container = null)
    {
        $this->events = $events;

        $this->routes = new RouteCollection;

        $this->container = $container ?: new Container;

        $this->bind('_missing', function ($value)
        {
            return explode('/', $value);
        });
    }

    /**
     * Enregistrez une nouvelle route GET avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function get($uri, $action)
    {
        return $this->addRoute(array('GET', 'HEAD'), $uri, $action);
    }

    /**
     * Enregistrez une nouvelle route POST avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function post($uri, $action)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Enregistrez une nouvelle route PUT avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function put($uri, $action)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Enregistrez une nouvelle route PATCH avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function patch($uri, $action)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Enregistrez une nouvelle route DELETE avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function delete($uri, $action)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Enregistrez un nouvel itinéraire OPTIONS avec le routeur.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function options($uri, $action)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Enregistrez un nouvel itinéraire répondant à tous les verbes.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function any($uri, $action)
    {
        $verbs = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE');

        return $this->addRoute($verbs, $uri, $action);
    }

    /**
     * Enregistrez un nouvel itinéraire avec les verbes donnés.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function match($methods, $uri, $action)
    {
        $methods = array_map('strtoupper', (array) $methods);

        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Enregistrez un nouvel itinéraire de secours.
     *
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    public function fallback($action)
    {
        return $this->addRoute(array('GET', 'HEAD'), "{fallback}", $action)
            ->where('fallback', '(.*)')
            ->fallback();
    }

    /**
     * Créez une redirection d'un URI vers un autre.
     *
     * @param  string  $uri
     * @param  string  $destination
     * @param  int  $status
     * @return \Two\Routing\Route
     */
    public function redirect($uri, $destination, $status = 301)
    {
        return $this->any($uri, function () use ($destination, $status)
        {
            return new RedirectResponse($destination, $status);
        });
    }

    /**
     * Enregistrez un tableau de contrôleurs de ressources.
     *
     * @param  array  $resources
     * @return void
     */
    public function resources(array $resources)
    {
        foreach ($resources as $name => $controller) {
            $this->resource($name, $controller);
        }
    }

    /**
     * Acheminer une ressource vers un contrôleur.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return void
     */
    public function resource($name, $controller, array $options = array())
    {
        $registrar = $this->getRegistrar();

        $registrar->register($name, $controller, $options);
    }

    /**
     * Créez un groupe de routes avec des attributs partagés.
     *
     * @param  array     $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
        }

        $this->updateGroupStack($attributes);

        // Une fois que nous aurons mis à jour la pile de groupes, nous exécuterons l'utilisateur Closure et
        // fusionne les attributs des groupes lors de la création de la route. Après avoir
        // exécute le rappel, nous supprimerons les attributs de cette pile de groupe.
        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * Mettez à jour la pile de groupes avec les attributs donnés.
     *
     * @param  array  $attributes
     * @return void
     */
    protected function updateGroupStack(array $attributes)
    {
        if (! empty($this->groupStack)) {
            $old = last($this->groupStack);

            $attributes = static::mergeGroup($attributes, $old);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Fusionnez les attributs de groupe donnés.
     *
     * @param  array  $new
     * @param  array  $old
     * @return array
     */
    public static function mergeGroup($new, $old)
    {
        $new['namespace'] = static::formatUsesPrefix($new, $old);

        $new['prefix'] = static::formatGroupPrefix($new, $old);

        if (isset($new['domain'])) {
            unset($old['domain']);
        }

        $new['where'] = array_merge(
            isset($old['where']) ? $old['where'] : array(),
            isset($new['where']) ? $new['where'] : array()
        );

        if (isset($old['as'])) {
            $new['as'] = $old['as'] .(isset($new['as']) ? $new['as'] : '');
        }

        return array_merge_recursive(
            Arr::except($old, array('namespace', 'prefix', 'where', 'as')), $new
        );
    }

    /**
     * Formatez le préfixe utilise pour les nouveaux attributs de groupe.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string
     */
    protected static function formatUsesPrefix($new, $old)
    {
        if (! isset($new['namespace'])) {
            return isset($old['namespace']) ? $old['namespace'] : null;
        }

        $namespace = trim($new['namespace'], '\\');

        if (isset($old['namespace'])) {
            return trim($old['namespace'], '\\') .'\\' .$namespace;
        }

        return $namespace;
    }

    /**
     * Formatez le préfixe des nouveaux attributs de groupe.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string
     */
    protected static function formatGroupPrefix($new, $old)
    {
        $prefix = isset($old['prefix']) ? $old['prefix'] : null;

        if (isset($new['prefix'])) {
            return trim($prefix ?? '', '/') .'/' .trim($new['prefix'], '/');
        }

        return $prefix;
    }

    /**
     * Obtenez le préfixe du dernier groupe de la pile.
     *
     * @return string
     */
    public function getLastGroupPrefix()
    {
        if (! empty($this->groupStack)) {
            $last = end($this->groupStack);

            return isset($last['prefix']) ? $last['prefix'] : '';
        }

        return '';
    }

    /**
     * Ajoutez une route à la collection de routes sous-jacente.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Two\Routing\Route
     */
    protected function addRoute($methods, $uri, $action)
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * Créez une nouvelle instance de route.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed   $action
     * @return \Two\Routing\Route
     */
    protected function createRoute($methods, $uri, $action)
    {
        if (is_callable($action)) {
            $action = array('uses' => $action);
        }

        // Si la route est acheminée vers un contrôleur, nous analyserons l'action de la route en
        // un format de tableau acceptable avant de l'enregistrer et de créer cette route
        // instance elle-même. Nous devons construire la clôture qui mettra cela en évidence.
        else if ($this->actionReferencesController($action)) {
            $action = $this->convertToControllerAction($action);
        }

        // Si aucune propriété "uses" n'a été définie, nous fouillerons dans le tableau pour trouver un
        // Instance de fermeture dans cette liste. Nous fixerons la première fermeture à laquelle nous viendrons
        // dans la propriété "uses" qui sera déclenchée par cette route.
        else if (! isset($action['uses'])) {
            $action['uses'] = $this->findActionClosure($action);
        }

        if (isset($action['middleware']) && is_string($action['middleware'])) {
            $action['middleware'] = explode('|', $action['middleware']);
        }

        $route = $this->newRoute(
            $methods, $uri = $this->prefix($uri), $action
        );

        // Si nous avons des groupes qui doivent être fusionnés, nous les fusionnerons maintenant après cela
        // la route a déjà été créée et est prête à fonctionner. Après que nous en ayons fini avec
        // la fusion, nous serons prêts à renvoyer la route à l'appelant.
        if (! empty($this->groupStack)) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        $this->addWhereClausesToRoute($route);

        return $route;
    }

    /**
     * Recherchez la fermeture dans un tableau d'actions.
     *
     * @param  array  $action
     * @return \Closure
     */
    protected function findActionClosure(array $action)
    {
        return Arr::first($action, function ($key, $value)
        {
            return is_callable($value) && is_numeric($key);
        });
    }

    /**
     * Créez un nouvel objet Route.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed   $action
     * @return \Two\Routing\Route
     */
    protected function newRoute($methods, $uri, $action)
    {
        return with(new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }

    /**
     * Préfixez l’URI donné avec le dernier préfixe.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prefix($uri)
    {
        return trim(trim($this->getLastGroupPrefix(), '/') .'/' .trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Ajoutez les clauses Where nécessaires à l'itinéraire en fonction de son enregistrement initial.
     *
     * @param  \Two\Routing\Route  $route
     * @return \Two\Routing\Route
     */
    protected function addWhereClausesToRoute($route)
    {
        $action = $route->getAction();

        return $route->where(array_merge(
            $this->patterns, Arr::get($action, 'where', array())
        ));
    }

    /**
     * Fusionnez la pile de groupes avec l'action du contrôleur.
     *
     * @param  \Two\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route)
    {
        $group = last($this->groupStack);

        unset($group['fallback']);

        $action = static::mergeGroup(
            $route->getAction(), $group
        );

        $route->setAction($action);
    }

    /**
     * Déterminez si l’action est acheminée vers un contrôleur.
     *
     * @param  array  $action
     * @return bool
     */
    protected function actionReferencesController($action)
    {
        if ($action instanceof Closure) {
            return false;
        }

        return is_string($action) || (isset($action['uses']) && is_string($action['uses']));
    }

    /**
     * Ajoutez une action de route basée sur un contrôleur au tableau d'actions.
     *
     * @param  array|string  $action
     * @return array
     */
    protected function convertToControllerAction($action)
    {
        if (is_string($action)) {
            $action = array('uses' => $action);
        }

        // Ici, nous obtiendrons une instance de ce répartiteur de contrôleur et la transmettrons à
        // la fermeture donc elle sera utilisée pour résoudre les instances de classe hors de notre
        // Instance de conteneur IoC et appelez les méthodes appropriées sur la classe.
        if (! empty($this->groupStack)) {
            $action['uses'] = $this->prependGroupUses($action['uses']);
        }

        // Ici, nous obtiendrons une instance de ce répartiteur de contrôleur et la transmettrons à
        // la fermeture donc elle sera utilisée pour résoudre les instances de classe hors de notre
        // Instance de conteneur IoC et appelez les méthodes appropriées sur la classe.
        $action['controller'] = $action['uses'];

        return $action;
    }

    /**
     * Ajoutez le dernier groupe use à la clause use.
     *
     * @param  string  $uses
     * @return string
     */
    protected function prependGroupUses($uses)
    {
        $group = last($this->groupStack);

        return isset($group['namespace']) ? $group['namespace'] .'\\' .$uses : $uses;
    }

    /**
     * Envoyez la demande à l’application.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Http\Response
     */
    public function dispatch(Request $request)
    {
        $this->currentRequest = $request;

        $response = $this->dispatchToRoute($request);

        return $this->prepareResponse($request, $response);
    }

    /**
     * Envoyez la demande vers une route et renvoyez la réponse.
     *
     * @param  \Two\Http\Request  $request
     * @return mixed
     */
    public function dispatchToRoute(Request $request)
    {
        // Nous allons d’abord trouver un itinéraire qui correspond à cette demande. Nous fixerons également le
        // résolveur de route sur la requête afin que les middlewares affectés à la route le fassent
        // reçoit l'accès à cette instance de route pour vérifier les paramètres.
        $route = $this->findRoute($request);

        $request->setRouteResolver(function () use ($route)
        {
            return $route;
        });

        $this->events->dispatch('router.matched', array($route, $request));

        $response = $this->runRouteWithinStack($route, $request);

        return $this->prepareResponse($request, $response);
    }

    /**
     * Exécutez la route donnée dans une instance Stack "onion".
     *
     * @param  \Two\Routing\Route  $route
     * @param  \Two\Http\Request  $request
     * @return mixed
     */
    protected function runRouteWithinStack(Route $route, Request $request)
    {
        $skipMiddleware = $this->container->bound('middleware.disable') &&
                          ($this->container->make('middleware.disable') === true);

        // Créez une instance de pipeline.
        $pipeline = new Pipeline(
            $this->container, $skipMiddleware ? array() : $this->gatherRouteMiddleware($route)
        );

        return $pipeline->handle($request, function ($request) use ($route)
        {
            $response = $route->run($request);

            return $this->prepareResponse($request, $response);
        });
    }

    /**
     * Rassemblez le middleware pour l’itinéraire donné.
     *
     * @param  \Two\Routing\Route  $route
     * @return array
     */
    public function gatherRouteMiddleware(Route $route)
    {
        $middleware = array_map(function ($name)
        {
            return $this->resolveMiddleware($name);

        }, $route->gatherMiddleware());

        return Arr::flatten($middleware);
    }

    /**
     * Résolvez le nom du middleware en nom de classe en préservant les paramètres transmis.
     *
     * @param  string $name
     * @return array
     */
    public function resolveMiddleware($name)
    {
        if (isset($this->middlewareGroups[$name])) {
            return $this->parseMiddlewareGroup($name);
        }

        return $this->parseMiddleware($name);
    }

    /**
     * Analysez le middleware et formatez-le pour l'utiliser.
     *
     * @param  string  $name
     * @return array
     */
    protected function parseMiddleware($name)
    {
        list($name, $parameters) = array_pad(explode(':', $name, 2), 2, null);

        //
        $callable = isset($this->middleware[$name]) ? $this->middleware[$name] : $name;

        if (is_null($parameters)) {
            return $callable;
        }

        // Lorsque l'appelable est une chaîne, nous rajouterons les paramètres avant de revenir.
        else if (is_string($callable)) {
            return $callable .':' .$parameters;
        }

        // Un rappel avec des paramètres ; nous devrions créer une fermeture middleware appropriée pour cela.
        $parameters = explode(',', $parameters);

        return function ($passable, $stack) use ($callable, $parameters)
        {
            return call_user_func_array(
                $callable, array_merge(array($passable, $stack), $parameters)
            );
        };
    }

    /**
     * Analysez le groupe de middleware et formatez-le pour l'utiliser.
     *
     * @param  string  $name
     * @return array
     */
    protected function parseMiddlewareGroup($name)
    {
        $results = array();

        foreach ($this->middlewareGroups[$name] as $middleware) {
            if (! isset($this->middlewareGroups[$middleware])) {
                $results[] = $this->parseMiddleware($middleware);

                continue;
            }

            // Le middleware fait référence à un groupe de middleware.
            $results = array_merge(
                $results, $this->parseMiddlewareGroup($middleware)
            );
        }

        return $results;
    }

    /**
     * Trouver l'itinéraire correspondant à une demande donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Routing\Route
     */
    protected function findRoute($request)
    {
        $this->current = $route = $this->routes->match($request);

        return $this->substituteBindings($route);
    }

    /**
     * Remplacez les liaisons d'itinéraire par l'itinéraire.
     *
     * @param  \Two\Routing\Route  $route
     * @return \Two\Routing\Route
     */
    protected function substituteBindings($route)
    {
        foreach ($route->parameters() as $key => $value) {
            if (isset($this->binders[$key])) {
                $route->setParameter($key, $this->performBinding($key, $value, $route));
            }
        }

        return $route;
    }

    /**
     * Appelez le rappel de liaison pour la clé donnée.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  \Two\Routing\Route  $route
     * @return mixed
     */
    protected function performBinding($key, $value, $route)
    {
        $callback = $this->binders[$key];

        return call_user_func($callback, $value, $route);
    }

    /**
     * Enregistrez un écouteur d’événement correspondant à un itinéraire.
     *
     * @param  string|callable  $callback
     * @return void
     */
    public function matched($callback)
    {
        $this->events->listen('router.matched', $callback);
    }

    /**
     * Obtenez tous les noms abrégés du middleware définis.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Enregistrez un nom abrégé pour un middleware.
     *
     * @param  string  $name
     * @param  string|\Closure  $middleware
     * @return $this
     */
    public function middleware($name, $middleware)
    {
        $this->middleware[$name] = $middleware;

        return $this;
    }

    /**
     * Enregistrez un groupe de middleware.
     *
     * @param  string  $name
     * @param  array  $middleware
     * @return $this
     */
    public function middlewareGroup($name, array $middleware)
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Ajoutez un middleware au début d'un groupe de middleware.
     *
     * Si le middleware est déjà dans le groupe, il ne sera pas ajouté à nouveau.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     */
    public function prependMiddlewareToGroup($group, $middleware)
    {
        if (isset($this->middlewareGroups[$group]) && ! in_array($middleware, $this->middlewareGroups[$group])) {
            array_unshift($this->middlewareGroups[$group], $middleware);
        }

        return $this;
    }

    /**
     * Ajoutez un middleware à la fin d'un groupe de middleware.
     *
     * Si le middleware est déjà dans le groupe, il ne sera pas ajouté à nouveau.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     */
    public function pushMiddlewareToGroup($group, $middleware)
    {
        if (! array_key_exists($group, $this->middlewareGroups)) {
            $this->middlewareGroups[$group] = array();
        }

        if (! in_array($middleware, $this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group][] = $middleware;
        }

        return $this;
    }

    /**
     * Ajoutez un nouveau classeur de paramètres d'itinéraire.
     *
     * @param  string  $key
     * @param  string|callable  $binder
     * @return void
     */
    public function bind($key, $binder)
    {
        if (is_string($binder)) {
            $binder = $this->createClassBinding($binder);
        }

        $key = str_replace('-', '_', $key);

        $this->binders[$key] = $binder;
    }

    /**
     * Enregistrez un classeur de modèles pour un caractère générique.
     *
     * @param  string  $key
     * @param  string  $className
     * @param  \Closure  $callback
     * @return void
     *
     * @throws NotFoundHttpException
     */
    public function model($key, $className, Closure $callback = null)
    {
        $this->bind($key, function ($value) use ($className, $callback)
        {
            if (is_null($value)) {
                return;
            }

            // Pour les classeurs de modèles, nous tenterons de récupérer les modèles à l'aide de la fonction find
            // méthode sur l'instance du modèle. Si nous ne pouvons pas récupérer les modèles, nous le ferons
            // lève une exception non trouvée sinon nous renverrons l'instance.
            if (! is_null($model = with(new $className)->find($value))) {
                return $model;
            }

            // Si un rappel a été fourni à la méthode, nous l'appellerons pour déterminer
            // ce que nous devons faire lorsque le modèle n'est pas trouvé. Cela donne juste ces
            // Développeur un peu plus de flexibilité pour décider de ce qui va se passer.
            if ($callback instanceof Closure) {
                return call_user_func($callback, $value);
            }

            throw new NotFoundHttpException;
        });
    }

    /**
     * Obtenez le rappel de liaison pour une liaison donnée.
     *
     * @param  string  $key
     * @return \Closure|null
     */
    public function getBindingCallback($key)
    {
        $key = str_replace('-', '_', $key);

        if (isset($this->binders[$key])) {
            return $this->binders[$key];
        }
    }

    /**
     * Créez une liaison basée sur une classe à l'aide du conteneur IoC.
     *
     * @param  string    $binding
     * @return \Closure
     */
    public function createClassBinding($binding)
    {
        return function ($value, $route) use ($binding)
        {
            // Si la liaison comporte un signe @, nous supposerons qu'elle est utilisée pour délimiter
            // le nom de la classe à partir du nom de la méthode de liaison. Cela permet des liaisons
            // pour exécuter plusieurs méthodes de liaison dans une seule classe pour plus de commodité.
            list($className, $method) = array_pad(explode('@', $binding, 2), 2, 'bind');

            $instance = $this->container->make($className);

            return call_user_func(array($instance, $method), $value, $route);
        };
    }

    /**
     * Définir un modèle Where global sur toutes les routes
     *
     * @param  string  $key
     * @param  string  $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Définir un groupe de modèles Where globaux sur toutes les routes
     *
     * @param  array  $patterns
     * @return void
     */
    public function patterns($patterns)
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern($key, $pattern);
        }
    }

    /**
     * Créez une instance de réponse à partir de la valeur donnée.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  mixed  $response
     * @return \Two\Http\Response
     */
    public function prepareResponse($request, $response)
    {
        if (! $response instanceof SymfonyResponse) {
            $response = new Response($response);
        }

        return $response->prepare($request);
    }

    /**
     * Déterminez si le routeur dispose actuellement d'une pile de groupe.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return ! empty($this->groupStack);
    }

    /**
     * Obtenez la pile de groupes actuelle pour le routeur.
     *
     * @return array
     */
    public function getGroupStack()
    {
        return $this->groupStack;
    }

    /**
     * Obtenez un paramètre d'itinéraire pour l'itinéraire actuel.
     *
     * @param  string  $key
     * @param  string  $default
     * @return mixed
     */
    public function input($key, $default = null)
    {
        return $this->current()->parameter($key, $default);
    }

    /**
     * Obtenez l'instance de route actuellement distribuée.
     *
     * @return \Two\Routing\Route
     */
    public function getCurrentRoute()
    {
        return $this->current();
    }

    /**
     * Obtenez l'instance de route actuellement distribuée.
     *
     * @return \Two\Routing\Route
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Vérifiez si une route portant le nom donné existe.
     *
     * @param  string  $name
     * @return bool
     */
    public function has($name)
    {
        return $this->routes->hasNamedRoute($name);
    }

    /**
     * Obtenez le nom de l'itinéraire actuel.
     *
     * @return string|null
     */
    public function currentRouteName()
    {
        if (! is_null($route = $this->current())) {
            return $route->getName();
        }
    }

    /**
     * Alias ​​pour la méthode "currentRouteNamed".
     *
     * @param  mixed  string
     * @return bool
     */
    public function is()
    {
        $patterns = func_get_args();

        $name = $this->currentRouteName();

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $this->currentRouteName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déterminez si l’itinéraire actuel correspond à un nom donné.
     *
     * @param  string  $name
     * @return bool
     */
    public function currentRouteNamed($name)
    {
        if (! is_null($route = $this->current())) {
            return ($route->getName() == $name);
        }

        return false;
    }

    /**
     * Obtenez l'action d'itinéraire actuelle.
     *
     * @return string|null
     */
    public function currentRouteAction()
    {
        if (! is_null($route = $this->current())) {
            $action = $route->getAction();

            return isset($action['controller']) ? $action['controller'] : null;
        }
    }

    /**
     * Alias ​​pour la méthode "currentRouteUses".
     *
     * @param  mixed  string
     * @return bool
     */
    public function uses()
    {
        $patterns = func_get_args();

        $action = $this->currentRouteAction();

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déterminez si l’action d’itinéraire actuelle correspond à une action donnée.
     *
     * @param  string  $action
     * @return bool
     */
    public function currentRouteUses($action)
    {
        return $this->currentRouteAction() == $action;
    }

    /**
     * Récupérez la demande en cours d'envoi.
     *
     * @return \Two\Http\Request
     */
    public function getCurrentRequest()
    {
        return $this->currentRequest;
    }

    /**
     * Obtenez la collection de routes sous-jacente.
     *
     * @return \Two\Routing\RouteCollection
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Obtenez une instance de Resource Registrar.
     *
     * @return \Two\Routing\ResourceRegistrar
     */
    public function getRegistrar()
    {
        if (isset($this->registrar)) {
            return $this->registrar;
        }

        return $this->registrar = new ResourceRegistrar($this);
    }

    /**
     * Obtenez les modèles globaux « où ».
     *
     * @return array
     */
    public function getPatterns()
    {
        return $this->patterns;
    }

    /**
     * Enregistrez une macro personnalisée.
     *
     * @param  string    $name
     * @param  callable  $callback
     * @return void
     */
    public function macro($name, callable $callback)
    {
        $this->macros[$name] = $callback;
    }

    /**
     * Vérifie si la macro est enregistrée
     *
     * @param  string    $name
     * @return boolean
     */
    public function hasMacro($name)
    {
        return isset($this->macros[$name]);
    }

    /**
     * Gérer dynamiquement les appels à la classe.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (isset($this->macros[$method])) {
            $callback = $this->macros[$method];

            return call_user_func_array($callback, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Closure;
use LogicException;
use ReflectionFunction;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Http\Request;
use Two\Container\Container;
use Two\Routing\RouteCompiler;
use Two\Routing\Matching\UriValidator;
use Two\Routing\Matching\HostValidator;
use Two\Routing\Matching\MethodValidator;
use Two\Routing\Matching\SchemeValidator;
use Two\Http\Exception\HttpResponseException;
use Two\Routing\Controller\ControllerDispatcher;
use Two\Routing\Traits\RouteDependencyResolverTrait;


class Route
{
    use RouteDependencyResolverTrait;

    /**
     * Instance de conteneur utilisée par la route.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Le modèle d'URI auquel la route répond.
     *
     * @var string
     */
    protected $uri;

    /**
     * Méthodes HTTP auxquelles la route répond.
     *
     * @var array
     */
    protected $methods;

    /**
     * Le tableau d’actions d’itinéraire.
     *
     * @var array
     */
    protected $action;

    /**
     * Indique si la route est une route de secours.
     *
     * @var bool
     */
    protected $fallback = false;

    /**
     * Les valeurs par défaut pour l'itinéraire.
     *
     * @var array
     */
    protected $defaults = array();

    /**
     * Les exigences des expressions régulières.
     *
     * @var array
     */
    protected $wheres = array();

    /**
     * Le tableau des paramètres correspondants.
     *
     * @var array
     */
    protected $parameters;

    /**
     * Les noms des paramètres pour l'itinéraire.
     *
     * @var array|null
     */
    protected $parameterNames;

    /**
     * La version compilée de l'itinéraire.
     *
     * @var \Symfony\Component\Routing\CompiledRoute
     */
    protected $compiled;

    /**
     * Le middleware collecté calculé.
     *
     * @var array|null
     */
    protected $computedMiddleware;

    /**
     * L'instance du contrôleur.
     *
     * @var mixed
     */
    protected $controllerInstance;

    /**
     * La méthode Contrôleur.
     *
     * @var mixed
     */
    protected $controllerMethod;

    /**
     * Les validateurs utilisés par les routes.
     *
     * @var array
     */
    protected static $validators;

    /**
     * 
     *
     * @var 
     */
    protected $router;
    
    /**
     * Créez une nouvelle instance de route.
     *
     * @param  array   $methods
     * @param  string  $uri
     * @param  \Closure|array  $action
     * @return void
     */
    public function __construct($methods, $uri, $action)
    {
        $this->uri = $uri;

        $this->methods = (array) $methods;

        $this->action = $action;

        if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        if (! is_null($prefix = Arr::get($this->action, 'prefix'))) {
            $this->prefix($prefix);
        }

        if (! is_null($fallback = Arr::get($this->action, 'fallback'))) {
            $this->fallback($fallback);
        }
    }

    /**
     * Exécutez l’action d’itinéraire et renvoyez la réponse.
     *
     * @return mixed
     */
    public function run()
    {
        if (! isset($this->container)) {
            $this->container = new Container();
        }

        try {
            return $this->runActionCallback();
        }
        catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Exécute l'action d'itinéraire et renvoie la réponse.
     *
     * @return mixed
     */
    protected function runActionCallback()
    {
        if ($this->isControllerAction()) {
            return $this->runControllerAction();
        }

        $callback = Arr::get($this->action, 'uses');

        $parameters = $this->resolveMethodDependencies(
            $this->parametersWithoutNulls(), new ReflectionFunction($callback)
        );

        return call_user_func_array($callback, $parameters);
    }

    /**
     * Exécute l'action d'itinéraire et renvoie la réponse.
     *
     * @return mixed
     */
    protected function runControllerAction()
    {
        $dispatcher = new ControllerDispatcher($this->container);

        return $dispatcher->dispatch(
            $this, $this->getControllerInstance(), $this->getControllerMethod()
        );
    }

    /**
     * Vérifie si l'action de la route est un contrôleur.
     *
     * @return bool
     */
    protected function isControllerAction()
    {
        return is_string($this->action['uses']);
    }

    /**
     * Obtenez l'instance de contrôleur pour l'itinéraire.
     *
     * @return mixed
     */
    public function getControllerInstance()
    {
        if (isset($this->controllerInstance)) {
            return $this->controllerInstance;
        }

        $callback = Arr::get($this->action, 'uses');

        list ($controller, $this->controllerMethod) = Str::parseCallback($callback);

        return $this->controllerInstance = $this->container->make($controller);
    }

    /**
     * Obtenez la méthode du contrôleur utilisée pour l’itinéraire.
     *
     * @return string
     */
    public function getControllerMethod()
    {
        if (! isset($this->controllerMethod)) {
            $callback = Arr::get($this->action, 'uses');

            list (, $this->controllerMethod) = Str::parseCallback($callback);
        }

        return $this->controllerMethod;
    }

    /**
     * Déterminez si l’itinéraire correspond à la demande donnée.
     *
     * @param  \Two\Http\Request  $request
     * @param  bool  $includingMethod
     * @return bool
     */
    public function matches(Request $request, $includingMethod = true)
    {
        $this->compileRoute();

        $validators = array_filter($this->getValidators(), function ($validator) use ($includingMethod)
        {
            return ($validator instanceof MethodValidator) ? $includingMethod : true;
        });

        foreach ($validators as $validator) {
            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compilez la route dans une instance Symfony CompiledRoute.
     *
     * @return void
     */
    protected function compileRoute()
    {
        if (! is_null($this->compiled)) {
            return $this->compiled;
        }

        return $this->compiled = with(new RouteCompiler($this))->compile();
    }

    /**
     * Obtenez tous les middlewares, y compris ceux du contrôleur.
     *
     * @return array
     */
    public function gatherMiddleware()
    {
        if (! is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        $this->computedMiddleware = array();

        return $this->computedMiddleware = array_unique(array_merge(
            $this->middleware(), $this->controllerMiddleware()

        ), SORT_REGULAR);
    }

    /**
     * Obtenez ou définissez les middlewares attachés à la route.
     *
     * @param  array|string|null $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return $this->getMiddleware();
        }

        if (is_string($middleware)) {
            $middleware = func_get_args();
        }

        $this->action['middleware'] = array_merge(
            $this->getMiddleware(), $middleware
        );

        return $this;
    }

    /**
     * Obtenez les middlewares attachés à la route.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return (array) Arr::get($this->action, 'middleware', array());
    }

    /**
     * Obtenez le middleware pour le contrôleur de la route.
     *
     * @return array
     */
    public function controllerMiddleware()
    {
        if (! $this->isControllerAction()) {
            return array();
        }

        return ControllerDispatcher::getMiddleware(
            $this->getControllerInstance(), $this->getControllerMethod()
        );
    }

    /**
     * Obtenez un paramètre donné de la route.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return string
     */
    public function getParameter($name, $default = null)
    {
        return $this->parameter($name, $default);
    }

    /**
     * Obtenez un paramètre donné de la route.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return string
     */
    public function parameter($name, $default = null)
    {
        return Arr::get($this->parameters(), $name, $default);
    }

    /**
     * Définissez un paramètre sur la valeur donnée.
     *
     * @param  string  $name
     * @param  mixed   $value
     * @return void
     */
    public function setParameter($name, $value)
    {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Annulez la définition d'un paramètre sur l'itinéraire s'il est défini.
     *
     * @param  string  $name
     * @return void
     */
    public function forgetParameter($name)
    {
        $this->parameters();

        unset($this->parameters[$name]);
    }

    /**
     * Obtenez la liste clé/valeur des paramètres de l’itinéraire.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function parameters()
    {
        if (! isset($this->parameters)) {
            throw new LogicException("The Route is not bound.");
        }

        return array_map(function ($value)
        {
            return is_string($value) ? rawurldecode($value) : $value;

        }, $this->parameters);
    }

    /**
     * Obtenez la liste clé/valeur des paramètres sans valeurs nulles.
     *
     * @return array
     */
    public function parametersWithoutNulls()
    {
        return array_filter($this->parameters(), function ($parameter)
        {
            return ! is_null($parameter);
        });
    }

    /**
     * Obtenez tous les noms de paramètres de l’itinéraire.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Obtenez les noms des paramètres de l’itinéraire.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->domain() .$this->uri, $matches);

        return array_map(function ($match)
        {
            return trim($match, '?');

        }, $matches[1]);
    }

    /**
     * Liez la route à une demande d’exécution donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return $this
     */
    public function bind(Request $request)
    {
        $this->compileRoute();

        $this->bindParameters($request);

        return $this;
    }

    /**
     * Extrayez la liste des paramètres de la requête.
     *
     * @param  \Two\Http\Request  $request
     * @return array
     */
    public function bindParameters(Request $request)
    {
        // Si la route a une expression régulière pour la partie hôte de l'URI, nous le ferons
        // compilez cela et obtenez les correspondances de paramètres pour ce domaine. Nous allons alors
        // les fusionne dans ce tableau de paramètres afin que ce tableau soit complété.
        $parameters = $this->bindPathParameters($request);

        // Si la route a une expression régulière pour la partie hôte de l'URI, nous le ferons
        // compilez cela et obtenez les correspondances de paramètres pour ce domaine. Nous allons alors
        // les fusionne dans ce tableau de paramètres afin que ce tableau soit complété.
        if (! is_null($this->compiled->getHostRegex())) {
            $parameters = array_merge(
                $this->bindHostParameters($request), $parameters
            );
        }

        return $this->parameters = $this->replaceDefaults($parameters);
    }

    /**
     * Obtenez les correspondances de paramètres pour la partie chemin de l’URI.
     *
     * @param  \Two\Http\Request  $request
     * @return array
     */
    protected function bindPathParameters(Request $request)
    {
        $regex = $this->compiled->getRegex();

        preg_match($regex, '/' .$request->decodedPath(), $matches);

        return $this->matchToKeys($matches);
    }

    /**
     * Extrayez la liste des paramètres de la partie hôte de la requête.
     *
     * @param  \Two\Http\Request  $request
     * @return array
     */
    protected function bindHostParameters(Request $request)
    {
        $regex = $this->compiled->getHostRegex();

        preg_match($regex, $request->getHost(), $matches);

        return $this->matchToKeys($matches);
    }

    /**
     * Combinez un ensemble de correspondances de paramètres avec les clés de l'itinéraire.
     *
     * @param  array  $matches
     * @return array
     */
    protected function matchToKeys(array $matches)
    {
        if (empty($parameterNames = $this->parameterNames())) {
            return array();
        }

        $parameters = array_intersect_key(
            $matches, array_flip($parameterNames)
        );

        return array_filter($parameters, function ($value)
        {
            return is_string($value) && (strlen($value) > 0);
        });
    }

    /**
     * Remplacez les paramètres nuls par leurs valeurs par défaut.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function replaceDefaults(array $parameters)
    {
        foreach ($parameters as $key => &$value) {
            if (! isset($value)) {
                $value = Arr::get($this->defaults, $key);
            }
        }

        return $parameters;
    }

    /**
     * Obtenez les validateurs d'itinéraire pour l'instance.
     *
     * @return array
     */
    public static function getValidators()
    {
        if (isset(static::$validators)) {
            return static::$validators;
        }

        // Pour faire correspondre l'itinéraire, nous utiliserons un modèle de chaîne de responsabilité avec le
        // implémentations du validateur. Nous passerons en revue chacun d'eux pour nous en assurer
        // réussit et nous saurons alors si la route dans son ensemble correspond à la demande.

        return static::$validators = array(
            new UriValidator(),
            new MethodValidator(),
            new SchemeValidator(),
            new HostValidator(),
        );
    }

    /**
     * Définissez une valeur par défaut pour l'itinéraire.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function defaults($key, $value)
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Obtenez les exigences d’expression régulière sur la route.
     *
     * @return array
     */
    public function patterns()
    {
        return $this->wheres;
    }

    /**
     * Définissez une exigence d’expression régulière sur la route.
     *
     * @param  array|string  $name
     * @param  string  $expression
     * @return $this
     */
    public function where($name, $expression = null)
    {
        $wheres = is_array($name) ? $name : array($name => $expression);

        foreach ($wheres as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Renvoie vrai si l’indicateur du mode de secours est défini.
     *
     * @return bool
     */
    public function isFallback()
    {
        return $this->fallback;
    }

    /**
     * Définissez le drapeau du mode de secours sur l'itinéraire.
     *
     * @param  bool  $value
     * @return $this
     */
    public function fallback($value = true)
    {
        $this->fallback = (bool) $value;

        return $this;
    }

    /**
     * Obtenez l'URI associé à l'itinéraire.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->uri();
    }

    /**
     * Obtenez l'URI associé à l'itinéraire.
     *
     * @return string
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Obtenez les verbes HTTP auxquels la route répond.s
     *
     * @return array
     */
    public function getMethods()
    {
        return $this->methods();
    }

    /**
     * Obtenez les verbes HTTP auxquels la route répond.
     *
     * @return array
     */
    public function methods()
    {
        return $this->methods;
    }

    /**
     * Déterminez si la route répond uniquement aux requêtes HTTP.
     *
     * @return bool
     */
    public function httpOnly()
    {
        return in_array('http', $this->action, true);
    }

    /**
     * Déterminez si la route répond uniquement aux requêtes HTTPS.
     *
     * @return bool
     */
    public function httpsOnly()
    {
        return $this->secure();
    }

    /**
     * Déterminez si la route répond uniquement aux requêtes HTTPS.
     *
     * @return bool
     */
    public function secure()
    {
        return in_array('https', $this->action, true);
    }

    /**
     * Obtenez le domaine défini pour l'itinéraire.
     *
     * @return string|null
     */
    public function domain()
    {
        return Arr::get($this->action, 'domain');
    }

    /**
     * Obtenez l'URI auquel la route répond.
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Définissez l'URI auquel la route répond.
     *
     * @param  string  $uri
     * $this
     */
    public function setUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Obtenez le préfixe de l'instance de route.
     *
     * @return string
     */
    public function getPrefix()
    {
        return Arr::get($this->action, 'prefix');
    }

    /**
     * Ajoutez un préfixe à l’URI de la route.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        $this->uri = trim($prefix, '/') .'/' .trim($this->uri, '/');

        return $this;
    }

    /**
     * Obtenez le nom de l'instance de route.
     *
     * @return string
     */
    public function getName()
    {
        return Arr::get($this->action, 'as');
    }

    /**
     * Ajoutez ou modifiez le nom de l'itinéraire.
     *
     * @param  string  $name
     * @return $this
     */
    public function name($name)
    {
        if (! empty($namePrefix = Arr::get($this->action, 'as'))) {
            $name = $namePrefix .$name;
        }

        $this->action['as'] = $name;

        return $this;
    }

    /**
     * Obtenez le nom de l'action pour l'itinéraire.
     *
     * @return string
     */
    public function getActionName()
    {
        return Arr::get($this->action, 'controller', 'Closure');
    }

    /**
     * Obtenez le tableau d’actions pour l’itinéraire.
     *
     * @return array
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Définissez le tableau d'actions pour l'itinéraire.
     *
     * @param  array  $action
     * @return $this
     */
    public function setAction(array $action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Obtenez la version compilée de l'itinéraire.
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    public function getCompiled()
    {
        return $this->compiled;
    }

    /**
     * Définissez l'instance de conteneur sur la route.
     *
     * @param  \Two\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Définissez l'instance de routeur sur la route.
     *
     * @param  \Two\Routing\Router  $router
     * @return $this
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Accédez dynamiquement aux paramètres d’itinéraire.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->parameter($key);
    }
}

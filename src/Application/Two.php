<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application;

use Closure;
use Exception;
use Throwable;
use RuntimeException;

use Two\Http\Request;
use Two\Http\Response;
use Two\Config\FileLoader;
use Two\Container\Container;
use Two\Filesystem\Filesystem;
use Two\Log\LogServiceProvider;
use Two\Support\Facades\Facade;
use Two\Application\Pipeline;
use Two\Events\EventServiceProvider;
use Two\Environment\EnvironmentDetector;
use Two\Exceptions\ExceptionServiceProvider;
use Two\Exceptions\Exception\FatalThrowableError;
use Two\Application\Providers\ServiceProvider;
use Two\Environment\FileEnvironmentVariablesLoader;
use Two\Application\Providers\ProviderRepository;
use Two\Application\Contracts\ResponsePreparerInterface;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class Two extends Container implements ResponsePreparerInterface
{

    /**
     * La version du framework Two.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * La version du PHP.
     *
     * @var string
     */
    const PHPVERSION = '8.2.1';

    /**
     * Indique si l'application a "démarré".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Le tableau des rappels de démarrage.
     *
     * @var array
     */
    protected $bootingCallbacks = array();

    /**
     * Le tableau des rappels démarrés.
     *
     * @var array
     */
    protected $bootedCallbacks = array();

    /**
     * Le tableau des rappels de finition.
     *
     * @var array
     */
    protected $terminatingCallbacks = array();

    /**
     * Tous les middlewares définis par le développeur.
     *
     * @var array
     */
    protected $middlewares = array();

    /**
     * Tous les fournisseurs de services enregistrés.
     *
     * @var array
     */
    protected $serviceProviders = array();

    /**
     * Les noms des fournisseurs de services chargés.
     *
     * @var array
     */
    protected $loadedProviders = array();

    /**
     * Les services différés et leurs prestataires.
     *
     * @var array
     */
    protected $deferredServices = array();

    /**
     * La classe de requête utilisée par l'application.
     *
     * @var string
     */
    protected static $requestClass = 'Two\Http\Request';

    /**
     * L'espace de noms de l'application.
     *
     * @var string
     */
    protected $namespace = null;


    /**
     * Create a new Two application instance.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request = null)
    {
        $this->registerBaseBindings($request ?: $this->createNewRequest());

        $this->registerBaseServiceProviders();
    }

    /**
     * Obtenez le numéro de version de l'application.
     *
     * @return string
     */
    public static function version()
    {
        return static::VERSION;
    }

    /**
     * Créez une nouvelle instance de requête à partir de la classe de requête.
     *
     * @return \Two\Http\Request
     */
    protected function createNewRequest()
    {
        return forward_static_call(array(static::$requestClass, 'createFromGlobals'));
    }

    /**
     * Enregistrez les liaisons de base dans le conteneur.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    protected function registerBaseBindings($request)
    {
        $this->instance('request', $request);

        $this->instance('Two\Container\Container', $this);
    }

    /**
     * Enregistrez tous les fournisseurs de services de base.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $providers = array('Event', 'Log', 'Exception');

        foreach ($providers as $provider) {
            $method = sprintf('register%sProvider', $provider);

            call_user_func(array($this, $method));
        }
    }

    /**
     * Enregistrez le fournisseur de services événementiels.
     *
     * @return void
     */
    protected function registerEventProvider()
    {
        $this->register(new EventServiceProvider($this));
    }

    /**
     * Enregistrez le fournisseur de services de journalisation.
     *
     * @return void
     */
    protected function registerLogProvider()
    {
        $this->register(new LogServiceProvider($this));
    }

    /**
     * Enregistrez le fournisseur de services d'exception.
     *
     * @return void
     */
    protected function registerExceptionProvider()
    {
        $this->register(new ExceptionServiceProvider($this));
    }

    /**
     * Liez les chemins d'installation à l'application.
     *
     * @param  array  $paths
     * @return void
     */
    public function bindInstallPaths(array $paths)
    {
        $this->instance('path', realpath($paths['app']));

        foreach (array_except($paths, array('app')) as $key => $value) {
            $this->instance("path.{$key}", realpath($value));
        }
    }

    /**
     * Démarrez la gestion des exceptions pour la demande.
     *
     * @return void
     */
    public function startExceptionHandling()
    {
        $this['exception']->register($this->environment());

        //
        $debug = $this['config']['app.debug'];

        $this['exception']->setDebug($debug);
    }

    /**
     * Obtenez ou vérifiez l'environnement d'application actuel.
     *
     * @param  mixed
     * @return string
     */
    public function environment()
    {
        if (count(func_get_args()) > 0) {
            return in_array($this['env'], func_get_args());
        }

        return $this['env'];
    }

    /**
     * Déterminez si l'application se trouve dans l'environnement local.
     *
     * @return bool
     */
    public function isLocal()
    {
        return $this['env'] == 'local';
    }

    /**
     * Détecter l'environnement actuel de l'application.
     *
     * @param  array|string  $envs
     * @return string
     */
    public function detectEnvironment($envs)
    {
        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;

        return $this['env'] = (new EnvironmentDetector())->detect($envs, $args);
    }

    /**
     * Déterminez si nous courons dans la console.
     *
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * Déterminez si nous exécutons des tests unitaires.
     *
     * @return bool
     */
    public function runningUnitTests()
    {
        return $this['env'] == 'testing';
    }

    /**
     * Forcer l'enregistrement d'un fournisseur de services avec l'application.
     *
     * @param  \Two\Application\Providers\ServiceProvider|string  $provider
     * @param  array  $options
     * @return \Two\Application\Providers\ServiceProvider
     */
    public function forceRegister($provider, $options = array())
    {
        return $this->register($provider, $options, true);
    }

    /**
     * Enregistrez un fournisseur de services avec l'application.
     *
     * @param  \Two\Application\Providers\ServiceProvider|string  $provider
     * @param  array  $options
     * @param  bool   $force
     * @return \Two\Application\Providers\ServiceProvider
     */
    public function register($provider, $options = array(), $force = false)
    {
        if ($registered = $this->getRegistered($provider) && ! $force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProviderClass($provider);
        }

        $provider->register();

        foreach ($options as $key => $value) {
            $this[$key] = $value;
        }

        $this->markAsRegistered($provider);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Obtenez l'instance du fournisseur de services enregistré si elle existe.
     *
     * @param  \Two\Application\Providers\ServiceProvider|string  $provider
     * @return \Two\Application\Providers\ServiceProvider|null
     */
    public function getRegistered($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        if (array_key_exists($name, $this->loadedProviders)) {
            return array_first($this->serviceProviders, function($key, $value) use ($name)
            {
                return get_class($value) == $name;
            });
        }
    }

    /**
     * Résoudre une instance de fournisseur de services à partir du nom de classe.
     *
     * @param  string  $provider
     * @return \Two\Application\Providers\ServiceProvider
     */
    public function resolveProviderClass($provider)
    {
        return new $provider($this);
    }

    /**
     * Marquez le fournisseur donné comme enregistré.
     *
     * @param  \Two\Application\Providers\ServiceProvider
     * @return void
     */
    protected function markAsRegistered($provider)
    {
        $this['events']->dispatch($class = get_class($provider), array($provider));

        $this->serviceProviders[] = $provider;

        $this->loadedProviders[$class] = true;
    }

    /**
     * Chargez et démarrez tous les fournisseurs différés restants.
     *
     * @return void
     */
    public function loadDeferredProviders()
    {
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferredServices = array();
    }

    /**
     * Charger le fournisseur pour un service différé.
     *
     * @param  string  $service
     * @return void
     */
    protected function loadDeferredProvider($service)
    {
        $provider = $this->deferredServices[$service];

        if (! isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Enregistrez un fournisseur et un service différés.
     *
     * @param  string  $provider
     * @param  string  $service
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null)
    {
        if (! is_null($service)) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if ($this->isBooted()) {
            return;
        }

        $this->booting(function() use ($instance)
        {
            $this->bootProvider($instance);
        });
    }

    /**
     * Résolvez le type donné à partir du conteneur.
     *
     * (Overriding Container::make)
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::make($abstract, $parameters);
    }

    /**
     * Détermine si le type abstrait donné a été lié.
     *
     * (Overriding Container::bound)
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->deferredServices[$abstract]) || parent::bound($abstract);
    }

    /**
     * "Étendre" un type abstrait dans le conteneur.
     *
     * (Overriding Container::extend)
     *
     * @param  string   $abstract
     * @param  \Closure  $closure
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function extend($abstract, Closure $closure)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::extend($abstract, $closure);
    }

    /**
     * Enregistrez une fonction pour déterminer quand utiliser les sessions de baie.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function useArraySessions(Closure $callback)
    {
        $this->bind('session.reject', function() use ($callback)
        {
            return $callback;
        });
    }

    /**
     * Déterminez si l'application a démarré.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Démarrez les fournisseurs de services de l'application.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isBooted()) {
            return;
        }

        array_walk($this->serviceProviders, function($provider)
        {
            $this->bootProvider($provider);
        });

        $this->bootApplication();
    }

    /**
     * Démarrez le fournisseur de services donné.
     *
     * @param  \Two\Application\Providers\ServiceProvider  $provider
     * @return mixed
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (method_exists($provider, 'boot')) {
            return $this->call(array($provider, 'boot'));
        }
    }

    /**
     * Démarrez l'application et déclenchez les rappels d'application.
     *
     * @return void
     */
    protected function bootApplication()
    {
        $this->fireAppCallbacks($this->bootingCallbacks);

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * Enregistrez un nouvel écouteur de démarrage.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Enregistrez un nouvel écouteur "démarré".
     *
     * @param  mixed  $callback
     * @return void
     */
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->fireAppCallbacks(array($callback));
        }
    }

    /**
     * Exécutez l'application et envoyez la réponse.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function run(SymfonyRequest $request = null)
    {
        $request = $request ?: $this['request'];

        // Configurez les middlewares du routeur.
        $middlewareGroups = $this['config']->get('app.middlewareGroups');

        foreach ($middlewareGroups as $key => $middleware) {
            $this['router']->middlewareGroup($key, $middleware);
        }

        $routeMiddleware = $this['config']->get('app.routeMiddleware');

        foreach($routeMiddleware as $name => $middleware) {
            $this['router']->middleware($name, $middleware);
        }

        try {
            $request->enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);
        }
        catch (Exception $e) {
            $response = $this->handleException($request, $e);
        }
        catch (Throwable $e) {
            $response = $this->handleException($request, new FatalThrowableError($e));
        }

        $response->send();

        $this->shutdown($request, $response);
    }

    /**
     * Envoyez la requête donnée via le middleware / routeur.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Http\Response
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->refreshRequest($request = Request::createFromBase($request));

        $this->boot();

        // Créez une instance de pipeline.
        $pipeline = new Pipeline(
            $this, $this->shouldSkipMiddleware() ? array() : $this->middleware
        );

        return $pipeline->handle($request, function ($request)
        {
            $this->instance('request', $request);

            return $this->router->dispatch($request);
        });
    }

    /**
     * Appelez la méthode terminate sur n'importe quel middleware terminable.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Two\Http\Response  $response
     * @return void
     */
    public function shutdown($request, $response)
    {
        $middlewares = $this->shouldSkipMiddleware() ? array() : array_merge(
            $this->gatherRouteMiddleware($request),
            $this->middleware
        );

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            list($name, $parameters) = $this->parseMiddleware($middleware);

            $instance = $this->app->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }

        $this->terminate();
    }

    /**
     * Enregistrez un rappel de terminaison avec l'application.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function terminating(Closure $callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Appelez les rappels "terminants" affectés à l'application.
     *
     * @return void
     */
    public function terminate()
    {
        foreach ($this->terminatingCallbacks as $callback) {
            call_user_func($callback);
        }
    }

    /**
     * Gérer l'exception donnée.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleException($request, Exception $e)
    {
        $this->reportException($e);

        return $this->renderException($request, $e);
    }

    /**
     * Signalez l'exception au gestionnaire d'exceptions.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function reportException(Exception $e)
    {
        $this->getExceptionHandler()->report($e);
    }

    /**
     * Restituez l'exception à une réponse.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderException($request, Exception $e)
    {
        return $this->getExceptionHandler()->render($request, $e);
    }

    /**
     * Obtenez l'instance d'application Two.
     *
     * @return \Two\Exceptions\Contracts\HandlerInterface
     */
    public function getExceptionHandler()
    {
        return $this->make('Two\Exceptions\Contracts\HandlerInterface');
    }

    /**
     * Rassemblez le middleware de route pour la requête donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return array
     */
    protected function gatherRouteMiddleware($request)
    {
        if (! is_null($route = $request->route())) {
            return $this->router->gatherRouteMiddleware($route);
        }

        return array();
    }

    /**
     * Analysez une chaîne middleware pour obtenir le nom et les paramètres.
     *
     * @param  string  $middleware
     * @return array
     */
    protected function parseMiddleware($middleware)
    {
        list($name, $parameters) = array_pad(explode(':', $middleware, 2), 2, array());

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return array($name, $parameters);
    }

    /**
     * Déterminez si le middleware a été désactivé pour l'application.
     *
     * @return bool
     */
    public function shouldSkipMiddleware()
    {
        return $this->bound('middleware.disable') && ($this->make('middleware.disable') === true);
    }

    /**
     * Ajoutez le middleware.
     *
     * @param  array  $middlewares
     * @return Two\Application\Application
     */
    public function middleware(array $middleware)
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * Ajoutez un nouveau middleware au début de la pile s'il n'existe pas déjà.
     *
     * @param  string  $middleware
     * @return Two\Application\Application
     */
    public function prependMiddleware($middleware)
    {
        if (array_search($middleware, $this->middleware) === false) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Ajoutez un nouveau middleware à la fin de la pile s'il n'existe pas déjà.
     *
     * @param  string|\Closure  $middleware
     * @return Two\Application\Application
     */
    public function pushMiddleware($middleware)
    {
        if (array_search($middleware, $this->middleware) === false) {
            array_push($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Déterminez si le noyau a un middleware donné.
     *
     * @param  string  $middleware
     * @return bool
     */
    public function hasMiddleware($middleware)
    {
        return in_array($middleware, $this->middleware);
    }

    /**
     * Actualisez l'instance de demande liée dans le conteneur.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    protected function refreshRequest(Request $request)
    {
        $this->instance('request', $request);

        Facade::clearResolvedInstance('request');
    }

    /**
     * Appelez les rappels de démarrage pour l'application.
     *
     * @param  array  $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Préparez la valeur donnée en tant qu'objet Response.
     *
     * @param  mixed  $value
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function prepareResponse($value)
    {
        if (! $value instanceof SymfonyResponse) {
            $value = new Response($value);
        }

        return $value->prepare($this['request']);
    }

    /**
     * Déterminez si l'application est prête pour les réponses.
     *
     * @return bool
     */
    public function readyForResponses()
    {
        return $this->booted;
    }

    /**
     * Déterminez si l'application est actuellement arrêtée pour maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance()
    {
        return file_exists($this['path.storage'] .DS .'down');
    }

    /**
     * Lancez une HttpException avec les données fournies.
     *
     * @param  int     $code
     * @param  string  $message
     * @param  array   $headers
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function abort($code, $message = '', array $headers = array())
    {
        if ($code == 404) {
            throw new NotFoundHttpException($message);
        }

        throw new HttpException($code, $message, null, $headers);
    }

    /**
     * Obtenez l'instance du chargeur de configuration.
     *
     * @return \Two\Config\Contracts\LoaderInterface
     */
    public function getConfigLoader()
    {
        return new FileLoader(new Filesystem, $this['path'] .DS .'Config');
    }

    /**
     * Obtenez l'instance du chargeur de variables d'environnement.
     *
     * @return \Two\Environment\Contracts\EnvironmentVariablesLoaderInterface
     */
    public function getEnvironmentVariablesLoader()
    {
        return new FileEnvironmentVariablesLoader(new Filesystem, $this['path.base']);
    }

    /**
     * Obtenez l'instance de référentiel du fournisseur de services.
     *
     * @return \Two\Application\Providers\ProviderRepository
     */
    public function getProviderRepository()
    {
        $manifest = $this['config']['app.manifest'];

        return new ProviderRepository(new Filesystem, $manifest);
    }

    /**
     * Obtenez les fournisseurs de services qui ont été chargés.
     *
     * @return array
     */
    public function getLoadedProviders()
    {
        return $this->loadedProviders;
    }

    /**
     * Définissez les services différés de l'application.
     *
     * @param  array  $services
     * @return void
     */
    public function setDeferredServices(array $services)
    {
        $this->deferredServices = $services;
    }

    /**
     * Déterminez si le service donné est un service différé.
     *
     * @param  string  $service
     * @return bool
     */
    public function isDeferredService($service)
    {
        return isset($this->deferredServices[$service]);
    }

    /**
     * Obtenez ou définissez la classe de requête pour l'application.
     *
     * @param  string  $class
     * @return string
     */
    public static function requestClass($class = null)
    {
        if (! is_null($class)) static::$requestClass = $class;

        return static::$requestClass;
    }

    /**
     * Définissez la demande d'application pour l'environnement de la console.
     *
     * @return void
     */
    public function setRequestForConsoleEnvironment()
    {
        $url = $this['config']->get('app.url', 'http://localhost');

        $parameters = array($url, 'GET', array(), array(), array(), $_SERVER);

        $this->refreshRequest(static::onRequest('create', $parameters));
    }

    /**
     * Appelez une méthode sur la classe de requête par défaut.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function onRequest($method, $parameters = array())
    {
        return forward_static_call_array(array(static::requestClass(), $method), $parameters);
    }

    /**
     * Obtenez les paramètres régionaux actuels de l'application.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this['config']->get('app.locale');
    }

    /**
     * Définissez les paramètres régionaux de l'application actuelle.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this['config']->set('app.locale', $locale);

        $this['language']->setLocale($locale);

        $this['events']->dispatch('locale.changed', array($locale));
    }

    /**
     * Enregistrez les alias de la classe principale dans le conteneur.
     *
     * @return void
     */
    public function registerCoreContainerAliases()
    {
        $aliases = array(
            'app'               => 'Two\Application\Two',
            'forge'             => 'Two\Console\TwoConsole',
            'auth'              => 'Two\Auth\AuthManager',
            'cache'             => 'Two\Cache\CacheManager',
            'cache.store'       => 'Two\Cache\Repository',
            'config'            => 'Two\Config\Repository',
            'cookie'            => 'Two\Cookie\CookieJar',
            'encrypter'         => 'Two\Encryption\Encrypter',
            'db'                => 'Two\Database\DatabaseManager',
            'events'            => 'Two\Events\Dispatcher',
            'files'             => 'Two\Filesystem\Filesystem',
            'hash'              => 'Two\Hashing\Contracts\HasherInterface',
            'language'          => 'Two\Language\LanguageManager',
            'log'               => array('Two\Log\Writer', 'Psr\Log\LoggerInterface'),
            'mailer'            => 'Two\Mail\Mailer',
            'redirect'          => 'Two\Routing\Redirector',
            'request'           => 'Two\Http\Request',
            'router'            => 'Two\Routing\Router',
            'session'           => 'Two\Session\SessionManager',
            'session.store'     => 'Two\Session\Store',
            'url'               => 'Two\Routing\UrlGenerator',
            'validator'         => 'Two\Validation\Factory',
            'view'              => 'Two\View\Factory',
            'assets'            => 'Two\Assets\AssetManager',
            'assets.dispatcher' => 'Two\Assets\AssetDispatcher',
        );

        foreach ($aliases as $key => $value) {
            foreach ((array) $value as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Obtenez l'espace de noms de l'application.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getNamespace()
    {
        if (! is_null($this->namespace)) {
            return $this->namespace;
        }

        $filePath = base_path('composer.json');

        $composer = json_decode(file_get_contents($filePath), true);

        //
        $appPath = realpath(app_path());

        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if ($appPath == realpath(base_path() .DS .$pathChoice)) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

}

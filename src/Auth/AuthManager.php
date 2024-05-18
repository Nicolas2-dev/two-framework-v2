<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Closure;
use InvalidArgumentException;

use Two\Auth\TokenGuard;
use Two\Auth\RequestGuard;
use Two\Auth\SessionGuard;
use Two\Auth\DatabaseUserProvider;
use Two\TwoApplication\TwoApplication;


class AuthManager
{
    /**
     * L'instance d'application.
     *
     * @var Two\TwoApplication\TwoApplication
     */
    protected $app;

    /**
     * Les créateurs de pilotes personnalisés enregistrés.
     *
     * @var array
     */
    protected $customCreators = array();

    /**
     * Les créateurs de fournisseurs personnalisés enregistrés.
     *
     * @var array
     */
    protected $customProviderCreators = array();

    /**
     * Le tableau des "pilotes" créés.
     *
     * @var array
     */
    protected $guards = array();

    /**
     * Le résolveur utilisateur partagé par divers services.
     *
     * Determines the default user for Request, and the UserInterface.
     *
     * @var \Closure
     */
    protected $userResolver;


    /**
     * Créez une nouvelle instance de gestionnaire.
     *
     * @param  Two\TwoApplication\TwoApplication  $app
     * @return void
     */
    public function __construct(TwoApplication $app)
    {
        $this->app = $app;

        $this->userResolver = function ($guard = null)
        {
            return $this->guard($guard)->user();
        };
    }

    /**
     * Essayez d'obtenir le garde de la cache locale.
     *
     * @param  string  $name
     * @return \Two\Auth\Guard
     */
    public function guard($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        if (! isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    /**
     * Résolvez le garde donné.
     *
     * @param  string  $name
     * @return \Two\Auth\Guard
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        $method = 'create' .ucfirst($config['driver']) .'Driver';

        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException("Auth guard driver [{$config['driver']}] is not defined.");
        }

        return call_user_func(array($this, $method), $name, $config);
    }

    /**
     * Appelez un créateur de pilotes personnalisés.
     *
     * @param  string  $name
     * @param  array  $config
     * @return mixed
     */
    protected function callCustomCreator($name, array $config)
    {
        $driver = $config['driver'];

        $callback = $this->customCreators[$driver];

        return call_user_func($callback, $this->app, $name, $config);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Two\Auth\Guard
     */
    public function createSessionDriver($name, array $config)
    {
        $provider = $this->createUserProvider($config['provider']);

        $guard = new SessionGuard($name, $provider, $this->app['session.store']);

        // Lorsque vous utilisez la fonctionnalité Se souvenir de moi des services d'authentification, nous
        // il faudra définir l'instance de chiffrement du garde, ce qui permet
        // Valeurs de cookies sécurisées et cryptées à générer pour ces cookies.
        if (method_exists($guard, 'setCookieJar')) {
            $guard->setCookieJar($this->app['cookie']);
        }

        if (method_exists($guard, 'setDispatcher')) {
            $guard->setDispatcher($this->app['events']);
        }

        if (method_exists($guard, 'setRequest')) {
            $guard->setRequest($this->app->refresh('request', $guard, 'setRequest'));
        }

        return $guard;
    }

    /**
     * Créez un garde d'authentification basé sur un jeton.
     *
     * @param  string  $name
     * @param  array  $config
     * @return \Two\Auth\TokenGuard
     */
    public function createTokenDriver($name, $config)
    {
        // Le token guard implémente une implémentation de base de la garde basée sur un jeton API
        // qui prend un champ de jeton API de la requête et le fait correspondre au
        // utilisateur dans la base de données ou dans une autre couche de persistance où se trouvent les utilisateurs.
        $guard = new TokenGuard(
            $this->createUserProvider($config['provider']),
            $this->app['request']
        );

        $this->app->refresh('request', $guard, 'setRequest');

        return $guard;
    }

    /**
     * Créez l’implémentation du fournisseur d’utilisateurs pour le pilote.
     *
     * @param  string  $provider
     * @return \Two\Auth\Contracts\UserProviderInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createUserProvider($provider)
    {
        $config = $this->app['config']["auth.providers.{$provider}"];

        // Récupérez le pilote de la configuration.
        $driver = $config['driver'];

        if (isset($this->customProviderCreators[$driver])) {
            $callback = $this->customProviderCreators[$driver];

            return call_user_func($callback, $this->app, $config);
        }

        switch ($driver) {
            case 'database':
                return $this->createDatabaseProvider($config);

            case 'extended':
                return $this->createExtendedProvider($config);

            default:
                break;
        }

        throw new InvalidArgumentException("Authentication user provider [{$driver}] is not defined.");
    }

    /**
     * Créez une instance du fournisseur d'utilisateurs de base de données.
     *
     * @return \Two\Auth\DatabaseUserProvider
     */
    protected function createDatabaseProvider(array $config)
    {
        $connection = $this->app['db']->connection();

        return new DatabaseUserProvider($connection, $this->app['hash'], $config['table']);
    }

    /**
     * Obtenez la configuration de la garde.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["auth.guards.{$name}"];
    }

    /**
     * Obtenez le nom du pilote d'authentification par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['auth.defaults.guard'];
    }

    /**
     * Définissez le pilote de garde par défaut que l'usine doit servir.
     *
     * @param  string  $name
     * @return void
     */
    public function shouldUse($name)
    {
        $this->setDefaultDriver($name);

        $this->userResolver = function ($name = null)
        {
            return $this->guard($name)->user();
        };
    }

    /**
     * Définissez le nom du pilote d'authentification par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['auth.defaults.guard'] = $name;
    }

    /**
     * Enregistrez un nouveau garde de demande basé sur le rappel.
     *
     * @param  string  $driver
     * @param  callable  $callback
     * @return $this
     */
    public function viaRequest($driver, callable $callback)
    {
        return $this->extend($driver, function () use ($callback)
        {
            $guard = new RequestGuard($callback, $this->app['request']);

            $this->app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    /**
     * Obtenez le rappel du résolveur utilisateur.
     *
     * @return \Closure
     */
    public function userResolver()
    {
        return $this->userResolver;
    }

    /**
     * Définissez le rappel à utiliser pour résoudre les utilisateurs.
     *
     * @param  \Closure  $userResolver
     * @return $this
     */
    public function resolveUsersUsing(Closure $userResolver)
    {
        $this->userResolver = $userResolver;

        return $this;
    }

    /**
     * Enregistrez un créateur de pilote personnalisé Closure.
     *
     * @param  string  $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Enregistrez un créateur de fournisseur personnalisé. Fermeture.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return $this
     */
    public function provider($name, Closure $callback)
    {
        $this->customProviderCreators[$name] = $callback;

        return $this;
    }

    /**
     * Appelez dynamiquement l’instance de pilote par défaut.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->guard(), $method), $parameters);
    }
}

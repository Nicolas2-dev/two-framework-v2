<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;

use Closure;
use InvalidArgumentException;

use Two\Support\Arr;
use Two\Application\Two;
use Two\Broadcasting\PendingBroadcast;
use Two\Broadcasting\Contracts\FactoryInterface;
use Two\Broadcasting\Broadcasters\LogBroadcaster;
use Two\Broadcasting\Broadcasters\NullBroadcaster;
use Two\Broadcasting\Broadcasters\RedisBroadcaster;
use Two\Broadcasting\Broadcasters\PusherBroadcaster;
use Two\Broadcasting\Broadcasters\QuasarBroadcaster;

use Pusher\Pusher;


class BroadcastManager implements FactoryInterface
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Tableau des pilotes de diffusion résolus.
     *
     * @var array
     */
    protected $drivers = array();

    /**
     * Les créateurs de pilotes personnalisés enregistrés.
     *
     * @var array
     */
    protected $customCreators = array();

    /**
     * Créez une nouvelle instance de gestionnaire.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    public function __construct(Two $app)
    {
        $this->app = $app;
    }

    /**
     * Obtenez l'ID de socket pour la requête donnée.
     *
     * @param  \Two\Http\Request|null  $request
     * @return string|null
     */
    public function socket($request = null)
    {
        if (is_null($request) && ! $this->app->bound('request')) {
            return;
        }

        $request = $request ?: $this->app['request'];

        if ($request->hasHeader('X-Socket-ID')) {
            return $request->header('X-Socket-ID');
        }
    }

    /**
     * Commencez à diffuser un événement.
     *
     * @param  mixed|null  $event
     * @return \Two\Broadcasting\PendingBroadcast|void
     */
    public function event($event = null)
    {
        return new PendingBroadcast($this->app->make('events'), $event);
    }

    /**
     * Obtenez une instance de pilote.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function connection($driver = null)
    {
        return $this->driver($driver);
    }

    /**
     * Obtenez une instance de pilote.
     *
     * @param  string  $name
     * @return mixed
     */
    public function driver($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->resolve($name);
    }

    /**
     * Résolvez le magasin donné.
     *
     * @param  string  $name
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Broadcaster [{$name}] is not defined.");
        }

        $driver = $config['driver'];

        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($config);
        }

        $method = 'create' .ucfirst($driver) .'Driver';

        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method), $config);
        }

        throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
    }

    /**
     * Appelez un créateur de pilotes personnalisés.
     *
     * @param  array  $config
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        $driver = $config['driver'];

        return call_user_func($this->customCreators[$driver], $this->app, $config);
    }

    /**
     * Créez une instance du pilote.
     *
     * @param  array  $config
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected function createQuasarDriver(array $config)
    {
        //$container = $this->resolveUser();

        return new QuasarBroadcaster($this->app, $config);
    }

    /**
     * Créez une instance du pilote.
     *
     * @param  array  $config
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected function createPusherDriver(array $config)
    {
        $options = Arr::get($config, 'options', array());

        // Créez une instance Pusher.
        $pusher = new Pusher($config['key'], $config['secret'], $config['app_id'], $options);

        //$container = $this->resolveContainer();

        return new PusherBroadcaster($this->app, $pusher);
    }

    /**
     * Créez une instance du pilote.
     *
     * @param  array  $config
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected function createRedisDriver(array $config)
    {
        $connection = Arr::get($config, 'connection');

        // Créez une instance de base de données Redis.
        $redis = $this->app->make('redis');

        //$container = $this->resolveContainer();

        return new RedisBroadcaster($this->app, $redis, $connection);
    }

    /**
     * Créez une instance du pilote.
     *
     * @param  array  $config
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected function createLogDriver(array $config)
    {
        $logger = $this->app->make('Psr\Log\LoggerInterface');

        //$container = $this->resolveContainer();

        return new LogBroadcaster($this->app, $logger);
    }

    /**
     * Créez une instance du pilote.
     *
     * @param  array  $config
     * @return \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected function createNullDriver(array $config)
    {
        //$container = $this->resolveContainer();

        return new NullBroadcaster($this->app);
    }

    /**
     * Résolvez l'intance du container.
     *
     * @return mixed
     */
    protected function resolveContainer()
    {
        return $this->app['container'];
    }

    /**
     * Obtenez la configuration de la connexion.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["broadcasting.connections.{$name}"];
    }

    /**
     * Obtenez le nom du pilote par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['broadcasting.default'];
    }

    /**
     * Définissez le nom du pilote par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['broadcasting.default'] = $name;
    }

    /**
     * Enregistrez un créateur de pilote personnalisé Closure.
     *
     * @param  string    $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Appelez dynamiquement l’instance de pilote par défaut.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->driver(), $method], $parameters);
    }
}

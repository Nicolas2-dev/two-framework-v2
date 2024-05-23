<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Closure;
use InvalidArgumentException;


class QueueManager
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * 
     *
     * @var array
     */
    protected $connections = array();

    /**
     * Tableau des connexions de file d’attente résolues.
     */
    protected $connectors = array();


    /**
     * Créez une nouvelle instance de gestionnaire de files d'attente.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Enregistrez un écouteur d'événements pour la boucle de file d'attente du démon.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function looping($callback)
    {
        $this->app['events']->listen('Two.queue.looping', $callback);
    }

    /**
     * Enregistrez un écouteur d'événement pour l'événement de tâche de traitement.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function processing($callback)
    {
        $this->app['events']->listen('Two.queue.processing', $callback);
    }

    /**
     * Enregistrez un écouteur d'événement pour l'événement de tâche traité.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function processed($callback)
    {
        $this->app['events']->listen('Two.queue.processed', $callback);
    }

    /**
     * Enregistrez un écouteur d'événement pour l'événement de tâche ayant échoué.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function failing($callback)
    {
        $this->app['events']->listen('Two.queue.failed', $callback);
    }

    /**
     * Enregistrez un écouteur d'événements pour l'arrêt de la file d'attente du démon.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function stopping($callback)
    {
        $this->app['events']->listen('Two.queue.stopping', $callback);
    }

    /**
     * Déterminez si le pilote est connecté.
     *
     * @param  string  $name
     * @return bool
     */
    public function connected($name = null)
    {
        return isset($this->connections[$name ?: $this->getDefaultDriver()]);
    }

    /**
     * Résolvez une instance de connexion de file d’attente.
     *
     * @param  string  $name
     * @return \Two\Queue\Contracts\QueueInterface
     */
    public function connection($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        // Si la connexion n'a pas encore été résolue, nous la résoudrons maintenant comme tout
        // des connexions sont résolues lorsqu'elles sont réellement nécessaires, nous le faisons donc
        // n'établit aucune connexion inutile aux différents points de terminaison de la file d'attente.
        if ( ! isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);

            $this->connections[$name]->setContainer($this->app);

            $this->connections[$name]->setEncrypter($this->app['encrypter']);
        }

        return $this->connections[$name];
    }

    /**
     * Résolvez une connexion de file d’attente.
     *
     * @param  string  $name
     * @return \Two\Queue\Contracts\QueueInterface
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        return $this->getConnector($config['driver'])->connect($config);
    }

    /**
     * Obtenez le connecteur pour un pilote donné.
     *
     * @param  string  $driver
     * @return \Two\Queue\Contracts\ConnectorInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function getConnector($driver)
    {
        if (isset($this->connectors[$driver]))
        {
            return call_user_func($this->connectors[$driver]);
        }

        throw new InvalidArgumentException("No connector for [$driver]");
    }

    /**
     * Ajoutez un résolveur de connexion de file d'attente.
     *
     * @param  string    $driver
     * @param  \Closure  $resolver
     * @return void
     */
    public function extend($driver, Closure $resolver)
    {
        return $this->addConnector($driver, $resolver);
    }

    /**
     * Ajoutez un résolveur de connexion de file d'attente.
     *
     * @param  string    $driver
     * @param  \Closure  $resolver
     * @return void
     */
    public function addConnector($driver, Closure $resolver)
    {
        $this->connectors[$driver] = $resolver;
    }

    /**
     * Obtenez la configuration de la connexion à la file d'attente.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["queue.connections.{$name}"];
    }

    /**
     * Obtenez le nom de la connexion à la file d'attente par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['queue.default'];
    }

    /**
     * Définissez le nom de la connexion à la file d'attente par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['queue.default'] = $name;
    }

    /**
     * Obtenez le nom complet de la connexion donnée.
     *
     * @param  string  $connection
     * @return string
     */
    public function getName($connection = null)
    {
        return $connection ?: $this->getDefaultDriver();
    }

    /**
     * Déterminez si l’application est en mode maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance()
    {
        return $this->app->isDownForMaintenance();
    }

    /**
     * Transmettez dynamiquement les appels vers la connexion par défaut.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = array($this->connection(), $method);

        return call_user_func_array($callable, $parameters);
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use InvalidArgumentException;

use Two\Support\Str;
use Two\Database\ConnectionFactory;
use Two\Database\Contracts\ConnectionResolverInterface;


class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Instance de fabrique de connexions à la base de données.
     *
     * @var \Two\Database\ConnectionFactory
     */
    protected $factory;

    /**
     * Les instances de connexion actives.
     *
     * @var array
     */
    protected $connections = array();

    /**
     * Les résolveurs de connexion personnalisés.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * Créez une nouvelle instance de gestionnaire de base de données.
     *
     * @param  \Two\Application\Two  $app
     * @param  \Two\Database\ConnectionFactory  $factory
     * @return void
     */
    public function __construct($app, ConnectionFactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /**
     * Obtenez une instance de connexion à la base de données.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    public function connection($name = null)
    {
        list($name, $type) = $this->parseConnectionName($name);

        if (! isset($this->connections[$name])) {
            $connection = $this->makeConnection($name);

            $this->setPdoForType($connection, $type);

            $this->connections[$name] = $this->prepare($connection);
        }

        return $this->connections[$name];
    }

    /**
     * Analysez la connexion dans un tableau du nom et du type de lecture/écriture.
     *
     * @param  string  $name
     * @return array
     */
    protected function parseConnectionName($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        return Str::endsWith($name, ['::read', '::write']) ? explode('::', $name, 2) : [$name, null];
    }

    /**
     * Déconnectez-vous de la base de données donnée et supprimez-le du cache local.
     *
     * @param  string  $name
     * @return void
     */
    public function purge($name = null)
    {
        $this->disconnect($name);

        unset($this->connections[$name]);
    }

    /**
     * Déconnectez-vous de la base de données donnée.
     *
     * @param  string  $name
     * @return void
     */
    public function disconnect($name = null)
    {
        if (isset($this->connections[$name = $name ?: $this->getDefaultConnection()])) {
            $this->connections[$name]->disconnect();
        }
    }

    /**
     * Reconnectez-vous à la base de données donnée.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    public function reconnect($name = null)
    {
        $this->disconnect($name = $name ?: $this->getDefaultConnection());

        if (! isset($this->connections[$name])) {
            return $this->connection($name);
        }

        return $this->refreshPdoConnections($name);
    }

    /**
     * Actualisez les connexions PDO sur une connexion donnée.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    protected function refreshPdoConnections($name)
    {
        $fresh = $this->makeConnection($name);

        return $this->connections[$name]
            ->setPdo($fresh->getPdo())
            ->setReadPdo($fresh->getReadPdo());
    }

    /**
     * Créez l'instance de connexion à la base de données.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    protected function makeConnection($name)
    {
        $config = $this->getConfig($name);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $driver = $config['driver'];

        if (isset($this->extensions[$driver])) {
            return call_user_func($this->extensions[$driver], $config, $name);
        }

        return $this->factory->make($config, $name);
    }

    /**
     * Préparez l'instance de connexion à la base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @return \Two\Database\Connection
     */
    protected function prepare(Connection $connection)
    {
        $connection->setFetchMode($this->app['config']['database.fetch']);

        if ($this->app->bound('events')) {
            $connection->setEventDispatcher($this->app['events']);
        }

        $app = $this->app;

        $connection->setCacheManager(function() use ($app)
        {
            return $app['cache'];
        });

        $connection->setReconnector(function($connection)
        {
            $this->reconnect($connection->getName());
        });

        return $connection;
    }

    /**
     * Préparez le mode lecture-écriture pour l’instance de connexion à la base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @param  string  $type
     * @return \Two\Database\Connection
     */
    protected function setPdoForType(Connection $connection, $type = null)
    {
        if ($type == 'read') {
            $connection->setPdo($connection->getReadPdo());
        } else if ($type == 'write') {
            $connection->setReadPdo($connection->getPdo());
        }

        return $connection;
    }

    /**
     * Obtenez la configuration pour une connexion.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getConfig($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        //
        $connections = $this->app['config']['database.connections'];

        if (is_null($config = array_get($connections, $name))) {
            throw new InvalidArgumentException("Database [$name] not configured.");
        }

        return $config;
    }

    /**
     * Obtenez le nom de connexion par défaut.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->app['config']['database.default'];
    }

    /**
     * Définissez le nom de connexion par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->app['config']['database.default'] = $name;
    }

    /**
     * Enregistrez un résolveur de connexion d’extension.
     *
     * @param  string    $name
     * @param  callable  $resolver
     * @return void
     */
    public function extend($name, callable $resolver)
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Renvoie toutes les connexions créées.
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Transmettez dynamiquement les méthodes à la connexion par défaut.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->connection(), $method), $parameters);
    }

}

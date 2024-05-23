<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use PDO;

use Two\Container\Container;
use Two\Database\Connections\MySqlConnection;
use Two\Database\Connections\SQLiteConnection;
use Two\Database\Connections\PostgresConnection;
use Two\Database\Connections\SqlServerConnection;
use Two\Database\Connectors\MySqlConnector;
use Two\Database\Connectors\SQLiteConnector;
use Two\Database\Connectors\PostgresConnector;
use Two\Database\Connectors\SqlServerConnector;


class ConnectionFactory
{
    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Créez une nouvelle instance de fabrique de connexions.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Établissez une connexion PDO basée sur la configuration.
     *
     * @param  array   $config
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    public function make(array $config, $name = null)
    {
        $config = $this->parseConfig($config, $name);

        if (isset($config['read'])) {
            return $this->createReadWriteConnection($config);
        }

        return $this->createSingleConnection($config);
    }

    /**
     * Créez une seule instance de connexion à la base de données.
     *
     * @param  array  $config
     * @return \Two\Database\Connection
     */
    protected function createSingleConnection(array $config)
    {
        $pdo = $this->createConnector($config)->connect($config);

        return $this->createConnection($config['driver'], $pdo, $config['database'], $config['prefix'], $config);
    }

    /**
     * Créez une seule instance de connexion à la base de données.
     *
     * @param  array  $config
     * @return \Two\Database\Connection
     */
    protected function createReadWriteConnection(array $config)
    {
        $connection = $this->createSingleConnection($this->getWriteConfig($config));

        return $connection->setReadPdo($this->createReadPdo($config));
    }

    /**
     * Créez une nouvelle instance PDO pour la lecture.
     *
     * @param  array  $config
     * @return \PDO
     */
    protected function createReadPdo(array $config)
    {
        $readConfig = $this->getReadConfig($config);

        return $this->createConnector($readConfig)->connect($readConfig);
    }

    /**
     * Obtenez la configuration de lecture pour une connexion en lecture/écriture.
     *
     * @param  array  $config
     * @return array
     */
    protected function getReadConfig(array $config)
    {
        $readConfig = $this->getReadWriteConfig($config, 'read');

        return $this->mergeReadWriteConfig($config, $readConfig);
    }

    /**
     * Obtenez la configuration de lecture pour une connexion en lecture/écriture.
     *
     * @param  array  $config
     * @return array
     */
    protected function getWriteConfig(array $config)
    {
        $writeConfig = $this->getReadWriteConfig($config, 'write');

        return $this->mergeReadWriteConfig($config, $writeConfig);
    }

    /**
     * Obtenez une configuration de niveau lecture/écriture.
     *
     * @param  array   $config
     * @param  string  $type
     * @return array
     */
    protected function getReadWriteConfig(array $config, $type)
    {
        if (isset($config[$type][0])) {
            return $config[$type][array_rand($config[$type])];
        }

        return $config[$type];
    }

    /**
     * Fusionner une configuration pour une connexion en lecture/écriture.
     *
     * @param  array  $config
     * @param  array  $merge
     * @return array
     */
    protected function mergeReadWriteConfig(array $config, array $merge)
    {
        return array_except(array_merge($config, $merge), array('read', 'write'));
    }

    /**
     * Analyser et préparer la configuration de la base de données.
     *
     * @param  array   $config
     * @param  string  $name
     * @return array
     */
    protected function parseConfig(array $config, $name)
    {
        return array_add(array_add($config, 'prefix', ''), 'name', $name);
    }

    /**
     * Créez une instance de connecteur basée sur la configuration.
     *
     * @param  array  $config
     * @return \Two\Database\Contracts\ConnectorInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createConnector(array $config)
    {
        if (! isset($config['driver'])) {
            throw new \InvalidArgumentException("A driver must be specified.");
        }

        if ($this->container->bound($key = "db.connector.{$config['driver']}")) {
            return $this->container->make($key);
        }

        switch ($config['driver'])
        {
            case 'mysql':
                return new MySqlConnector;

            case 'pgsql':
                return new PostgresConnector;

            case 'sqlite':
                return new SQLiteConnector;

            case 'sqlsrv':
                return new SqlServerConnector;
        }

        throw new \InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    /**
     * Créez une nouvelle instance de connexion.
     *
     * @param  string   $driver
     * @param  \PDO     $connection
     * @param  string   $database
     * @param  string   $prefix
     * @param  array    $config
     * @return \Two\Database\Connection
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection($driver, PDO $connection, $database, $prefix = '', array $config = array())
    {
        if ($this->container->bound($key = "db.connection.{$driver}")) {
            return $this->container->make($key, array($connection, $database, $prefix, $config));
        }

        switch ($driver)
        {
            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);

            case 'pgsql':
                return new PostgresConnection($connection, $database, $prefix, $config);

            case 'sqlite':
                return new SQLiteConnection($connection, $database, $prefix, $config);

            case 'sqlsrv':
                return new SqlServerConnection($connection, $database, $prefix, $config);
        }

        throw new \InvalidArgumentException("Unsupported driver [$driver]");
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Connectors;

use PDO;

use Two\Database\Connector;
use Two\Database\Contracts\ConnectorInterface;


class PostgresConnector extends Connector implements ConnectorInterface
{
    /**
     * Les options de connexion PDO par défaut.
     *
     * @var array
     */
    protected $options = array(
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    );


    /**
     * Établissez une connexion à la base de données.
     *
     * @param  array  $config
     * @return PDO
     */
    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        $connection = $this->createConnection($dsn, $config, $options);

        //
        $charset = $config['charset'];

        $connection->prepare("set names '$charset'")->execute();

        if (isset($config['schema'])) {
            $schema = $config['schema'];

            $connection->prepare("set search_path to {$schema}")->execute();
        }

        return $connection;
    }

    /**
     * Créez une chaîne DSN à partir d'une configuration.
     *
     * @param  array   $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        extract($config);

        //
        $host = isset($host) ? "host={$host};" : '';

        $dsn = "pgsql:{$host}dbname={$database}";

        if (isset($config['port'])) {
            $dsn .= ";port={$port}";
        }

        if (isset($config['sslmode'])) {
            $dsn .= ";sslmode={$sslmode}";
        }
        
        return $dsn;
    }

}

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


class SqlServerConnector extends Connector implements ConnectorInterface
{
    /**
     * Les options de connexion PDO.
     *
     * @var array
     */
    protected $options = array(
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
    );

    /**
     * Établissez une connexion à la base de données.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        return $this->createConnection($this->getDsn($config), $config, $options);
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

        // Nous allons d'abord créer la configuration de base du DSN ainsi que le port s'il est dans
        // dans les options de configuration. Cela nous donnera le DSN de base que nous allons
        // besoin d'établir les connexions PDO et de les renvoyer pour utilisation.
        if (in_array('dblib', $this->getAvailableDrivers())) {
            $port = isset($config['port']) ? ':'.$port : '';

            return "dblib:host={$hostname}{$port};dbname={$database}";
        }

        $port = isset($config['port']) ? ','.$port : '';

        $dbName = $database != '' ? ";Database={$database}" : '';

        return "sqlsrv:Server={$hostname}{$port}{$dbName}";
    }

    /**
     * Obtenez les pilotes PDO disponibles.
     *
     * @return array
     */
    protected function getAvailableDrivers()
    {
        return PDO::getAvailableDrivers();
    }

}

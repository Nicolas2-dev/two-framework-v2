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


class MySqlConnector extends Connector implements ConnectorInterface
{
    /**
     * Établissez une connexion à la base de données.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        $connection = $this->createConnection($dsn, $config, $options);

        //
        $collation = $config['collation'];

        $charset = $config['charset'];

        $names = "set names '$charset'".
            (! is_null($collation) ? " collate '$collation'" : '');

        $connection->prepare($names)->execute();

        if (isset($config['strict']) && $config['strict']) {
            $connection->prepare("set session sql_mode='STRICT_ALL_TABLES'")->execute();
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

        $dsn = "mysql:host={$hostname};dbname={$database}";

        if (isset($config['port'])) {
            $dsn .= ";port={$port}";
        }

        if (isset($config['unix_socket'])) {
            $dsn .= ";unix_socket={$config['unix_socket']}";
        }

        return $dsn;
    }

}

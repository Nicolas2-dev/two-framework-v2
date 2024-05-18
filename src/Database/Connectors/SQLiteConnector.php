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


class SQLiteConnector extends Connector implements ConnectorInterface
{

    /**
     * Établissez une connexion à la base de données.
     *
     * @param  array  $config
     * @return \PDO
     *
     * @throws \InvalidArgumentException
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        if ($config['database'] == ':memory:') {
            return $this->createConnection('sqlite::memory:', $config, $options);
        }

        $path = realpath($config['database']);

        if ($path === false) {
            throw new \InvalidArgumentException("Database does not exist.");
        }

        return $this->createConnection("sqlite:{$path}", $config, $options);
    }

}

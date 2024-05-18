<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Connectors;

use Two\Support\Arr;
use Two\Queue\Queues\DatabaseQueue;
use Two\Queue\Contracts\Connectors\ConnectorInterface;
use Two\Database\Contracts\ConnectionResolverInterface;


class DatabaseConnector implements ConnectorInterface
{
    /**
     * Connexions à la base de données.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $connections;

    /**
     * Créez une nouvelle instance de connecteur.
     *
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $connections
     * @return void
     */
    public function __construct(ConnectionResolverInterface $connections)
    {
        $this->connections = $connections;
    }

    /**
     * Établissez une connexion à la file d'attente.
     *
     * @param  array  $config
     * @return \Two\Queue\Queue
     */
    public function connect(array $config)
    {
        $connection = Arr::get($config, 'connection');

        return new DatabaseQueue(
            $this->connections->connection($connection),

            $config['table'],
            $config['queue'],

            Arr::get($config, 'expire', 60)
        );
    }
}

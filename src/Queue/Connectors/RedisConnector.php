<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Connectors;

use Two\Queue\Contracts\Connectors\ConnectorInterface;
use Two\Queue\Queues\RedisQueue;
use Two\Redis\Database;


class RedisConnector implements ConnectorInterface
{

    /**
     * L'instance de base de données Redis.
     *
     * @var \Two\Redis\Database
     */
    protected $redis;

    /**
     * Le nom de la connexion.
     *
     * @var string
     */
    protected $connection;

    /**
     * Créez une nouvelle instance de connecteur de file d'attente Redis.
     *
     * @param  \Two\Redis\Database  $redis
     * @param  string|null  $connection
     * @return void
     */
    public function __construct(Database $redis, $connection = null)
    {
        $this->redis = $redis;
        $this->connection = $connection;
    }

    /**
     * Établissez une connexion à la file d'attente.
     *
     * @param  array  $config
     * @return \Two\Queue\Contracts\QueueInterface
     */
    public function connect(array $config)
    {
        $queue = new RedisQueue(
            $this->redis, $config['queue'], array_get($config, 'connection', $this->connection)
        );

        $queue->setExpire(array_get($config, 'expire', 60));

        return $queue;
    }

}

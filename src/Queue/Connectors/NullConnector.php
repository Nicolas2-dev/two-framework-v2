<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Connectors;

use Two\Queue\Contracts\Connectors\ConnectorInterface;
use Two\Queue\Queues\NullQueue;


class NullConnector implements ConnectorInterface
{
    /**
     * Établissez une connexion à la file d'attente.
     *
     * @param  array  $config
     * @return \Two\Queue\Queue
     */
    public function connect(array $config)
    {
        return new NullQueue;
    }
}

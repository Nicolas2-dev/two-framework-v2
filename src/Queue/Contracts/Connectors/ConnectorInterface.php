<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Contracts\Connectors;


interface ConnectorInterface
{

    /**
     * Établissez une connexion à la file d'attente.
     *
     * @param  array  $config
     * @return \Two\Queue\Contracts\QueueInterface
     */
    public function connect(array $config);

}

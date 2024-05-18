<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus\Contracts;

use Two\Bus\Contracts\DispatcherInterface;


interface QueueingDispatcherInterface extends DispatcherInterface
{
    /**
     * Envoyez une commande à son gestionnaire approprié derrière une file d'attente.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue($command);
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Contracts;


interface QueueableEntityInterface
{
    /**
     * Obtenez l'identité pouvant être mise en file d'attente pour l'entité.
     *
     * @return mixed
     */
    public function getQueueableId();
}

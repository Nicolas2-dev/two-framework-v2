<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Contracts;


interface MessageProviderInterface
{
    
    /**
     * Obtenez les messages pour l'instance.
     *
     * @return \Two\Support\MessageBag
     */
    public function getMessageBag();
}

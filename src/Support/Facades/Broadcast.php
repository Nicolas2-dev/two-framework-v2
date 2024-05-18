<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Facades;

use Two\Broadcasting\Contracts\FactoryInterface as BroadcastingFactory;


/**
 * @see \Two\Broadcasting\Contracts\FactoryInterface
 */
class Broadcast extends Facade
{
    /**
     * Obtenez le nom enregistré du composant.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BroadcastingFactory::class;
    }
}

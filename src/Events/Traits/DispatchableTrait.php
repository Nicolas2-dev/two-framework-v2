<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Events\Traits;


trait DispatchableTrait
{

    /**
     * Distribuez l'événement avec les arguments donnés.
     *
     * @return void
     */
    public static function dispatch()
    {
        return event(new static(...func_get_args()));
    }
    
    /**
     * Diffusez l'événement avec les arguments donnés.
     *
     * @return \Two\Broadcasting\PendingBroadcast
     */
    public static function broadcast()
    {
        return broadcast(new static(...func_get_args()));
    }
}

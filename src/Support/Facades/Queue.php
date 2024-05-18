<?php 
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Facades;


/**
 * @see \Two\Queue\QueueManager
 * @see \Two\Queue\Queue
 */
class Queue extends Facade
{

    /**
    * Obtenez le nom enregistré du composant.
    *
    * @return string
    */
    protected static function getFacadeAccessor() { return 'queue'; }

}

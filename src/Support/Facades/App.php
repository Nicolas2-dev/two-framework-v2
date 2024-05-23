<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Facades;

use Two\Support\Facades\Facade;


/**
 * @see \Two\Application\Two
 */
class App extends Facade
{
    
    /**
     * Renvoie l'instance Application.
     *
     * @return \Two\Application\Two
     */
    public static function instance()
    {
        $accessor = static::getFacadeAccessor();

        return static::resolveFacadeInstance($accessor);
    }

    /**
     * Obtenez le nom enregistré du composant.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'app'; }
}

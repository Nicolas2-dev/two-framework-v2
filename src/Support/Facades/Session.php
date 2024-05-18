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
 * @see \Two\Session\SessionManager
 * @see \Two\Session\Store
 */
class Session extends Facade
{
    /**
     * Renvoie l’instance d’application.
     *
     * @return \Two\Pagination\Factory
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
    protected static function getFacadeAccessor() { return 'session'; }

}

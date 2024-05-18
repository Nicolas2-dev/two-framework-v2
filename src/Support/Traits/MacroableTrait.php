<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Traits;

use BadMethodCallException;


trait MacroableTrait
{

    /**
     * Les macros de chaîne enregistrées.
     *
     * @var array
     */
    protected static $macros = array();


    /**
     * Enregistrez une macro personnalisée.
     *
     * @param  string    $name
     * @param  callable  $macro
     * @return void
     */
    public static function macro($name, callable $macro)
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Vérifie si la macro est enregistrée
     *
     * @param  string    $name
     * @return boolean
     */
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Gérer dynamiquement les appels à la classe.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic($method, $parameters)
    {
        if (static::hasMacro($method)) {
            $callback = static::$macros[$method];

            return call_user_func_array($callback, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * Gérer dynamiquement les appels à la classe.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        return static::__callStatic($method, $parameters);
    }

}

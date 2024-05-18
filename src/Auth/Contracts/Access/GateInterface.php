<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Contracts\Access;


interface GateInterface
{
    /**
     * Déterminez si une capacité donnée a été définie.
     *
     * @param  string  $ability
     * @return bool
     */
    public function has($ability);

    /**
     * Définir une nouvelle capacité.
     *
     * @param  string  $ability
     * @param  callable|string  $callback
     * @return $this
     */
    public function define($ability, $callback);

    /**
     * Définissez une classe de stratégie pour un type de classe donné.
     *
     * @param  string  $class
     * @param  string  $policy
     * @return $this
     */
    public function policy($class, $policy);

    /**
     * Déterminez si la capacité donnée doit être accordée.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function check($ability, $arguments = []);

    /**
     * Déterminez si la capacité donnée doit être accordée à l’utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return \Two\Auth\Access\Response
     *
     * @throws \Two\Auth\Execption\AuthorizationException
     */
    public function authorize($ability, $arguments = array());    
}

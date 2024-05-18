<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Countable;

use Two\Support\MessageBag;


class ViewErrorBag implements Countable
{
    /**
     * Le tableau des sacs d’erreurs de vue.
     *
     * @var array
     */
    protected $bags = array();


    /**
     * Vérifie si un MessageBag nommé existe dans les sacs.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasBag($key = 'default')
    {
        return isset($this->bags[$key]);
    }

    /**
     * Obtenez une instance MessageBag à partir des sacs.
     *
     * @param  string  $key
     * @return \Two\Support\MessageBag
     */
    public function getBag($key)
    {
        return array_get($this->bags, $key, new MessageBag);
    }

    /**
     * Récupérez tous les sacs.
     *
     * @return array
     */
    public function getBags()
    {
        return $this->bags;
    }

    /**
     * Ajoutez une nouvelle instance MessageBag aux sacs.
     *
     * @param  string  $key
     * @param  \Two\Support\MessageBag  $bag
     * @return $this
     */
    public function put($key, MessageBag $bag)
    {
        $this->bags[$key] = $bag;

        return $this;
    }

    /**
     * Obtenez le nombre de messages dans le sac par défaut.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->default->count();
    }

    /**
     * Appelez dynamiquement des méthodes sur le sac par défaut.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->default, $method), $parameters);
    }

    /**
     * Accédez dynamiquement à un sac d’erreurs de vue.
     *
     * @param  string  $key
     * @return \Two\Support\MessageBag
     */
    public function __get($key)
    {
        return array_get($this->bags, $key, new MessageBag);
    }

    /**
     * Définir dynamiquement un sac d'erreurs de vue.
     *
     * @param  string  $key
     * @param  \Two\Support\MessageBag  $value
     * @return void
     */
    public function __set($key, $value)
    {
        array_set($this->bags, $key, $value);
    }

}

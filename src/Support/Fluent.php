<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support;

use ArrayAccess;

use Two\Application\Contracts\JsonableInterface;
use Two\Application\Contracts\ArrayableInterface;


class Fluent implements ArrayAccess, ArrayableInterface, JsonableInterface {

    /**
     * Tous les attributs définis sur le conteneur.
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * Créez une nouvelle instance de conteneur fluide.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct($attributes = array())
    {
        foreach ($attributes as $key => $value)
        {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Obtenez un attribut du conteneur.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->attributes))
        {
            return $this->attributes[$key];
        }

        return value($default);
    }

    /**
     * Récupérez les attributs du conteneur.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Convertissez l'instance Fluent en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Convertissez l'instance Fluent en JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Déterminez si le décalage donné existe.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->{$offset});
    }

    /**
     * Obtenez la valeur pour un décalage donné.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed 
    {
        return $this->{$offset};
    }

    /**
     * Définissez la valeur au décalage donné.
     *
     * @param  string  $offset
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($offset, $value): void 
    {
        $this->{$offset} = $value;
    }

    /**
     * Supprimez la valeur au décalage donné.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset($offset): void 
    {
        unset($this->{$offset});
    }

    /**
     * Gérez les appels dynamiques au conteneur pour définir les attributs.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return \Two\Support\Fluent
     */
    public function __call($method, $parameters)
    {
        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

        return $this;
    }

    /**
     * Récupérer dynamiquement la valeur d'un attribut.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Définissez dynamiquement la valeur d'un attribut.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Vérifiez dynamiquement si un attribut est défini.
     *
     * @param  string  $key
     * @return void
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Désactivez dynamiquement un attribut.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Two\Auth\Contracts\UserInterface;
use Two\TwoApplication\Contracts\ArrayableInterface;


class GenericUser implements UserInterface, ArrayableInterface
{
    /**
     * Tous les attributs de l'utilisateur.
     *
     * @var array
     */
    protected $attributes;


    /**
     * Créez un nouvel objet utilisateur générique.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Convertissez l'instance de modèle en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Obtenez l'identifiant unique de l'utilisateur.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->attributes['id'];
    }

    /**
     * Obtenez le mot de passe de l'utilisateur.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->attributes['password'];
    }

    /**
     * Obtenez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @return string
     */
    public function getRememberToken()
    {
        $key = $this->getRememberTokenName();

        return $this->attributes[$key];
    }

    /**
     * Définissez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        $key = $this->getRememberTokenName();

        $this->attributes[$key] = $value;
    }

    /**
     * Obtenez le nom de colonne pour le jeton « se souvenir de moi ».
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    /**
     * Accédez dynamiquement aux attributs de l'utilisateur.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
    }

    /**
     * Définissez dynamiquement un attribut sur l'utilisateur.
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
     * Vérifiez dynamiquement si une valeur est définie sur l'utilisateur.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Désactivez dynamiquement une valeur sur l'utilisateur.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

}

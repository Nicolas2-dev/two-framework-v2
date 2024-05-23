<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Auth;


use JsonSerializable;

use Two\Auth\Contracts\UserInterface;
use Two\Application\TwoContracts\ArrayableInterface;


class Guest implements UserInterface, ArrayableInterface, JsonSerializable
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $username = 'guest';


    /**
     * Créez un nouvel objet Utilisateur invité.
     *
     * @param  string  $id
     * @param  string  $remoteIp
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Convertissez l'instance d'objet en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id'       => $this->id,
            'username' => $this->username,
        );
    }

    /**
     * Convertissez l'objet en quelque chose de sérialisable JSON.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Obtenez l'identifiant unique de l'utilisateur.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->id;
    }

    /**
     * Obtenez le mot de passe de l'utilisateur.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        // Les utilisateurs invités n'ont pas de mot de passe.
    }

    /**
     * Obtenez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @return string
     */
    public function getRememberToken()
    {
        //
    }

    /**
     * Définissez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        //
    }

    /**
     * Obtenez le nom de la colonne pour le jeton « souvenez-vous de moi ».
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}

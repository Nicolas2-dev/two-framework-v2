<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Contracts;


interface UserInterface
{
    /**
     * Obtenez l'identifiant unique de l'utilisateur.
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Obtenez le mot de passe de l'utilisateur.
     *
     * @return string
     */
    public function getAuthPassword();

    /**
     * Obtenez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @return string
     */
    public function getRememberToken();

    /**
     * Définissez la valeur du jeton pour la session « Se souvenir de moi ».
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value);

    /**
     * Obtenez le nom de colonne pour le jeton « se souvenir de moi ».
     *
     * @return string
     */
    public function getRememberTokenName();

}

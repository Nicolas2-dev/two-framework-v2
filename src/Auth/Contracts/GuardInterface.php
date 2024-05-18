<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Contracts;


interface GuardInterface
{
    /**
     * Déterminez si l'utilisateur actuel est authentifié.
     *
     * @return bool
     */
    public function check();

    /**
     * Déterminez si l'utilisateur actuel est un invité.
     *
     * @return bool
     */
    public function guest();

    /**
     * Obtenez l'utilisateur actuellement authentifié.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function user();

    /**
     * Obtenez l'ID de l'utilisateur actuellement authentifié.
     *
     * @return int|null
     */
    public function id();

    /**
     * Validez les informations d'identification d'un utilisateur.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array());

    /**
     * Définissez l'utilisateur actuel.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    public function setUser(UserInterface $user);
}

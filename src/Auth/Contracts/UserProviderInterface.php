<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Contracts;


interface UserProviderInterface
{
    /**
     * Récupérez un utilisateur par son identifiant unique.
     *
     * @param  mixed  $identifier
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveById($identifier);

    /**
     * Récupérez un utilisateur grâce à son identifiant unique et son jeton « se souvenir de moi ».
     *
     * @param  mixed   $identifier
     * @param  string  $token
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveByToken($identifier, $token);

    /**
     * Mettez à jour le jeton « Se souvenir de moi » pour l'utilisateur donné dans le stockage.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserInterface $user, $token);

    /**
     * Récupérez un utilisateur à l'aide des informations d'identification fournies.
     *
     * @param  array  $credentials
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveByCredentials(array $credentials);

    /**
     * Validez un utilisateur par rapport aux informations d'identification fournies.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserInterface $user, array $credentials);

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Traits;

use Two\Auth\Contracts\UserInterface;
use Two\Auth\Exception\AuthenticationException;


/**
 * Ces méthodes sont généralement les mêmes pour tous les gardes.
 */
trait GuardHelpersTrait
{
    /**
     * L'utilisateur actuellement authentifié.
     *
     * @var \Two\Auth\Contracts\UserInterface
     */
    protected $user;

    /**
     * L’implémentation du fournisseur d’utilisateurs.
     *
     * @var \Two\Auth\Contracts\UserProviderInterface
     */
    protected $provider;


    /**
     * Déterminez si l'utilisateur actuel est authentifié.
     *
     * @return \Two\Auth\Contracts\UserInterface
     *
     * @throws \Two\Auth\Exception\AuthenticationException
     */
    public function authenticate()
    {
        if (! is_null($user = $this->user())) {
            return $user;
        }

        throw new AuthenticationException;
    }

    /**
     * Déterminez si l'utilisateur actuel est authentifié.
     *
     * @return bool
     */
    public function check()
    {
        return ! is_null($this->user());
    }

    /**
     * Déterminez si l'utilisateur actuel est un invité.
     *
     * @return bool
     */
    public function guest()
    {
        return ! $this->check();
    }

    /**
     * Obtenez l'ID de l'utilisateur actuellement authentifié.
     *
     * @return int|null
     */
    public function id()
    {
        if (! is_null($user = $this->user())) {
            return $user->getAuthIdentifier();
        }
    }

    /**
     * Définissez l'utilisateur actuel.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return $this
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }
}

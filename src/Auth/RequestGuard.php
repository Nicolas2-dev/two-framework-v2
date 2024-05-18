<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Two\Http\Request;
use Two\Auth\Contracts\GuardInterface;
use Two\Auth\Traits\GuardHelpersTrait;


class RequestGuard implements GuardInterface
{
    use GuardHelpersTrait;

    /**
     * Le garde rappelle.
     *
     * @var callable
     */
    protected $callback;

    /**
     * L’instance de requête.
     *
     * @var \Two\Http\Request
     */
    protected $request;


    /**
     * Créez un nouveau garde d'authentification.
     *
     * @param  callable  $callback
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function __construct(callable $callback, Request $request)
    {
        $this->request  = $request;
        $this->callback = $callback;
    }

    /**
     * Obtenez l'utilisateur actuellement authentifié.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function user()
    {
        // Si nous avons déjà récupéré l'utilisateur pour la requête en cours, nous pouvons simplement
        // le renvoie immédiatement. Nous ne voulons pas récupérer les données utilisateur sur
        // chaque appel à cette méthode car cela serait extrêmement lent.
        if (! is_null($this->user)) {
            return $this->user;
        }

        return $this->user = call_user_func($this->callback, $this->request);
    }

    /**
     * Validez les informations d'identification d'un utilisateur.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        $guard = new static($this->callback, $credentials['request']);

        return ! is_null($guard->user());
    }

    /**
     * Définissez l’instance de requête actuelle.
     *
     * @param  \Two\Http\Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }
}

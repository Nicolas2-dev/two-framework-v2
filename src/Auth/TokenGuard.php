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
use Two\Auth\Contracts\UserProviderInterface;


class TokenGuard implements GuardInterface
{
    use GuardHelpersTrait;

    /**
     * L’instance de requête.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * Le nom du champ sur la requête contenant le jeton API.
     *
     * @var string
     */
    protected $inputKey;

    /**
     * Le nom de la « colonne » du jeton dans le stockage persistant.
     *
     * @var string
     */
    protected $storageKey;


    /**
     * Créez un nouveau garde d'authentification.
     *
     * @param  \Two\Auth\Contracts\UserProviderInterface  $provider
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function __construct(UserProviderInterface $provider, Request $request)
    {
        $this->request  = $request;
        $this->provider = $provider;

        $this->inputKey   = 'api_token';
        $this->storageKey = 'api_token';
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

        $user = null;

        $token = $this->getTokenForRequest();

        if (! empty($token)) {
            $user = $this->provider->retrieveByCredentials(
                array($this->storageKey => $token)
            );
        }

        return $this->user = $user;
    }

    /**
     * Obtenez le jeton pour la demande en cours.
     *
     * @return string
     */
    protected function getTokenForRequest()
    {
        $token = $this->request->input($this->inputKey);

        if (empty($token)) {
            $token = $this->request->bearerToken();
        }

        if (empty($token)) {
            $token = $this->request->getPassword();
        }

        return $token;
    }

    /**
     * Validez les informations d'identification d'un utilisateur.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        $credentials = array($this->storageKey => $credentials[$this->inputKey]);

        if ($this->provider->retrieveByCredentials($credentials)) {
            return true;
        }

        return false;
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

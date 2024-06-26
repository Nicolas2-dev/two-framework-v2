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
use Two\Auth\Contracts\UserProviderInterface;
use Two\Auth\Traits\GuardHelpersTrait;


class TokenGuard implements GuardInterface
{
    use GuardHelpersTrait;

    /**
     * The request instance.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * The name of the field on the request containing the API token.
     *
     * @var string
     */
    protected $inputKey;

    /**
     * The name of the token "column" in persistent storage.
     *
     * @var string
     */
    protected $storageKey;


    /**
     * Create a new authentication guard.
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
     * Get the currently authenticated user.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function user()
    {
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
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
     * Get the token for the current request.
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
     * Validate a user's credentials.
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
     * Set the current request instance.
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

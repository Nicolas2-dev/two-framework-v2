<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Two\Cookie\CookieJar;
use Two\Events\Dispatcher;
use Two\Auth\Contracts\UserInterface;
use Two\Auth\Contracts\GuardInterface;
use Two\Auth\Traits\GuardHelpersTrait;
use Two\Session\Store as SessionStore;

use Symfony\Component\HttpFoundation\Request;
use Two\Auth\Contracts\UserProviderInterface;
use Symfony\Component\HttpFoundation\Response;


class SessionGuard implements GuardInterface
{
    use GuardHelpersTrait;

    /**
     * The name of the Guard. Typically "session".
     *
     * Corresponds to driver name in authentication configuration.
     *
     * @var string
     */
    protected $name;

    /**
     * The user we last attempted to retrieve.
     *
     * @var \Two\Auth\Contracts\UserInterface
     */
    protected $lastAttempted;

    /**
     * Indicates if the user was authenticated via a recaller cookie.
     *
     * @var bool
     */
    protected $viaRemember = false;

    /**
     * The session store used by the guard.
     *
     * @var \Two\Session\Store
     */
    protected $session;

    /**
     * The Two cookie creator service.
     *
     * @var \Two\Cookie\CookieJar
     */
    protected $cookie;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The event dispatcher instance.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * Indicates if the logout method has been called.
     *
     * @var bool
     */
    protected $loggedOut = false;

    /**
     * Indicates if a token user retrieval has been attempted.
     *
     * @var bool
     */
    protected $tokenRetrievalAttempted = false;


    /**
     * Create a new authentication guard.
     *
     * @param  \Two\Auth\Contracts\UserProviderInterface  $provider
     * @param  \Two\Session\Store  $session
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function __construct($name,
                                UserProviderInterface $provider,
                                SessionStore $session,
                                Request $request = null)
    {
        $this->name = $name;

        $this->session  = $session;
        $this->request  = $request;
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function user()
    {
        if ($this->loggedOut) {
            return;
        }

        // If we have already retrieved the user for the current request we can just
        // return it back immediately. We do not want to pull the user data every
        // request into the method because that would tremendously slow an app.
        if (! is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        // First we will try to load the user using the identifier in the session if
        // one exists. Otherwise we will check for a "remember me" cookie in this
        // request, and if one exists, attempt to retrieve the user using that.
        $user = null;

        if (! is_null($id)) {
            $user = $this->provider->retrieveByID($id);
        }

        // If the user is null, but we decrypt a "recaller" cookie we can attempt to
        // pull the user data on that cookie which serves as a remember cookie on
        // the application. Once we have a user we can return it to the caller.
        $recaller = $this->getRecaller();

        if (is_null($user) && ! is_null($recaller)) {
            $user = $this->getUserByRecaller($recaller);
        }

        return $this->user = $user;
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        if ($this->loggedOut) {
            return;
        }

        $id = $this->session->get($this->getName(), $this->getRecallerId());

        if (is_null($id) && ! is_null($user = $this->user())) {
            $id = $user->getAuthIdentifier();
        }

        return $id;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        return $this->attempt($credentials, false, false);
    }

    /**
     * Pull a user from the repository by its recaller ID.
     *
     * @param  string  $recaller
     * @return mixed
     */
    protected function getUserByRecaller($recaller)
    {
        if ($this->validRecaller($recaller) && ! $this->tokenRetrievalAttempted) {
            $this->tokenRetrievalAttempted = true;

            list($id, $token) = explode('|', $recaller, 2);

            $this->viaRemember = ! is_null($user = $this->provider->retrieveByToken($id, $token));

            return $user;
        }
    }

    /**
     * Get the decrypted recaller cookie for the request.
     *
     * @return string|null
     */
    protected function getRecaller()
    {
        $name = $this->getRecallerName();

        return $this->request->cookies->get($name);
    }

    /**
     * Get the user ID from the recaller cookie.
     *
     * @return string
     */
    protected function getRecallerId()
    {
        if ($this->validRecaller($recaller = $this->getRecaller())) {
            $segments = explode('|', $recaller);

            return head($segments);
        }
    }

    /**
     * Determine if the recaller cookie is in a valid format.
     *
     * @param  string  $recaller
     * @return bool
     */
    protected function validRecaller($recaller)
    {
        if (! is_string($recaller) || ! str_contains($recaller, '|')) {
            return false;
        }

        $segments = explode('|', $recaller);

        return (count($segments) == 2) && (trim($segments[0]) !== '') && (trim($segments[1]) !== '');
    }

    /**
     * Log a user into the application without sessions or cookies.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function once(array $credentials = array())
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    /**
     * Attempt to authenticate using HTTP Basic Auth.
     *
     * @param  string  $field
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public function basic($field = 'email', Request $request = null)
    {
        if ($this->check()) {
            return;
        }

        $request = $request ?: $this->getRequest();

        // If a username is set on the HTTP basic request, we will return out without
        // interrupting the request lifecycle. Otherwise, we'll need to generate a
        // request indicating that the given credentials were invalid for login.
        if ($this->attemptBasic($request, $field)) {
            return;
        }

        return $this->getBasicResponse();
    }

    /**
     * Perform a stateless HTTP Basic login attempt.
     *
     * @param  string  $field
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public function onceBasic($field = 'email', Request $request = null)
    {
        $request = $request ?: $this->getRequest();

        if (! $this->once($this->getBasicCredentials($request, $field))) {
            return $this->getBasicResponse();
        }
    }

    /**
     * Attempt to authenticate using basic authentication.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  string  $field
     * @return bool
     */
    protected function attemptBasic(Request $request, $field)
    {
        if (! $request->getUser()) {
            return false;
        }

        return $this->attempt($this->getBasicCredentials($request, $field));
    }

    /**
     * Get the credential array for a HTTP Basic request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  string  $field
     * @return array
     */
    protected function getBasicCredentials(Request $request, $field)
    {
        return array($field => $request->getUser(), 'password' => $request->getPassword());
    }

    /**
     * Get the response for basic authentication.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getBasicResponse()
    {
        $headers = array('WWW-Authenticate' => 'Basic');

        return new Response('Invalid credentials.', 401, $headers);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param  array  $credentials
     * @param  bool   $remember
     * @param  bool   $login
     * @return bool
     */
    public function attempt(array $credentials = array(), $remember = false, $login = true)
    {
        $this->fireAttemptEvent($credentials, $remember, $login);

        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        // If an implementation of UserInterface was returned, we'll ask the provider
        // to validate the user against the given credentials, and if they are in
        // fact valid we'll log the users into the application and return true.
        if (! $this->hasValidCredentials($user, $credentials)) {
            return false;
        }

        if ($login) {
            $this->login($user, $remember);
        }

        return true;
    }

    /**
     * Determine if the user matches the credentials.
     *
     * @param  mixed  $user
     * @param  array  $credentials
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        return ! is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Fire the attempt event with the arguments.
     *
     * @param  array  $credentials
     * @param  bool   $remember
     * @param  bool   $login
     * @return void
     */
    protected function fireAttemptEvent(array $credentials, $remember, $login)
    {
        if ($this->events) {
            $payload = array($credentials, $remember, $login);

            $this->events->dispatch('auth.attempt', $payload);
        }
    }

    /**
     * Register an authentication attempt event listener.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function attempting($callback)
    {
        if ($this->events) {
            $this->events->listen('auth.attempt', $callback);
        }
    }

    /**
     * Log a user into the application.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  bool  $remember
     * @return void
     */
    public function login(UserInterface $user, $remember = false)
    {
        $this->updateSession($user->getAuthIdentifier());

        // If the user should be permanently "remembered" by the application we will
        // queue a permanent cookie that contains the encrypted copy of the user
        // identifier. We will then decrypt this later to retrieve the users.
        if ($remember) {
            $this->createRememberTokenIfDoesntExist($user);

            $this->queueRecallerCookie($user);
        }

        // If we have an event dispatcher instance set we will fire an event so that
        // any listeners will hook into the authentication events and run actions
        // based on the login and logout events fired from the guard instances.
        if (isset($this->events)) {
            $this->events->dispatch('auth.login', array($user, $remember));
        }

        $this->setUser($user);
    }

    /**
     * Update the session with the given ID.
     *
     * @param  string  $id
     * @return void
     */
    protected function updateSession($id)
    {
        $this->session->put($this->getName(), $id);

        $this->session->migrate(true);
    }

    /**
     * Log the given user ID into the application.
     *
     * @param  mixed  $id
     * @param  bool   $remember
     * @return \Two\Auth\Contracts\UserInterface
     */
    public function loginUsingId($id, $remember = false)
    {
        $this->session->put($this->getName(), $id);

        $user = $this->provider->retrieveById($id);

        $this->login($user, $remember);

        return $user;
    }

    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function onceUsingId($id)
    {
        $user = $this->provider->retrieveById($id);

        $this->setUser($user);

        return ($user instanceof UserInterface);
    }

    /**
     * Queue the recaller cookie into the cookie jar.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function queueRecallerCookie(UserInterface $user)
    {
        $value = $user->getAuthIdentifier() .'|' .$user->getRememberToken();

        $cookie = $this->createRecaller($value);

        $this->getCookieJar()->queue($cookie);
    }

    /**
     * Create a remember me cookie for a given ID.
     *
     * @param  string  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function createRecaller($value)
    {
        $cookies = $this->getCookieJar();

        return $cookies->forever($this->getRecallerName(), $value);
    }

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout()
    {
        $user = $this->user();

        // If we have an event dispatcher instance, we can fire off the logout event
        // so any further processing can be done. This allows the developer to be
        // listening for anytime a user signs out of this application manually.
        $this->clearUserDataFromStorage();

        if (! is_null($this->user)) {
            $this->refreshRememberToken($user);
        }

        if (isset($this->events)) {
            $this->events->dispatch('auth.logout', array($user));
        }

        // Once we have fired the logout event we will clear the users out of memory
        // so they are no longer available as the user is no longer considered as
        // being signed into this application and should not be available here.
        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Remove the user data from the session and cookies.
     *
     * @return void
     */
    protected function clearUserDataFromStorage()
    {
        $this->session->forget($this->getName());

        $recaller = $this->getRecallerName();

        $this->getCookieJar()->queue($this->getCookieJar()->forget($recaller));
    }

    /**
     * Refresh the remember token for the user.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function refreshRememberToken(UserInterface $user)
    {
        $user->setRememberToken($token = str_random(60));

        $this->provider->updateRememberToken($user, $token);
    }

    /**
     * Create a new remember token for the user if one doesn't already exist.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function createRememberTokenIfDoesntExist(UserInterface $user)
    {
        $rememberToken = $user->getRememberToken();

        if (empty($rememberToken)) {
            $this->refreshRememberToken($user);
        }
    }

    /**
     * Get the cookie creator instance used by the guard.
     *
     * @return \Two\Cookie\CookieJar
     *
     * @throws \RuntimeException
     */
    public function getCookieJar()
    {
        if (! isset($this->cookie)) {
            throw new \RuntimeException("Cookie jar has not been set.");
        }

        return $this->cookie;
    }

    /**
     * Set the cookie creator instance used by the guard.
     *
     * @param  \Two\Cookie\CookieJar  $cookie
     * @return void
     */
    public function setCookieJar(CookieJar $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Two\Events\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Two\Events\Dispatcher
     * @return void
     */
    public function setDispatcher(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Get the session store used by the guard.
     *
     * @return \Two\Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Get the user provider used by the guard.
     *
     * @return \Two\Auth\Contracts\UserProviderInterface
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard.
     *
     * @param  \Two\Auth\Contracts\UserProviderInterface  $provider
     * @return void
     */
    public function setProvider(UserProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Return the currently cached user of the application.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the current user of the application.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        $this->loggedOut = false;

        return $this;
    }

    /**
     * Get the current request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request ?: Request::createFromGlobals();
    }

    /**
     * Set the current request instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the last user we attempted to authenticate.
     *
     * @return \Two\Auth\Contracts\UserInterface
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }

    /**
     * Get a unique identifier for the auth session value.
     *
     * @return string
     */
    public function getName()
    {
        return 'login_' .$this->name .'_' .md5(get_class($this));
    }

    /**
     * Get the name of the cookie used to store the "recaller".
     *
     * @return string
     */
    public function getRecallerName()
    {
        return PREFIX .'remember_' .$this->name .'_' .md5(get_class($this));
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * @return bool
     */
    public function viaRemember()
    {
        return $this->viaRemember;
    }

}

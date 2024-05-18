<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Traits;

use Two\Http\Request;
use Two\Support\Facades\App;
use Two\Support\Facades\Redirect;


trait ThrottlesLoginsTrait
{
    /**
     * Déterminez si l'utilisateur a trop de tentatives de connexion infructueuses.
     *
     * @param  \Two\Http\Request  $request
     * @return bool
     */
    protected function hasTooManyLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Two\Cache\RateLimiter');

        return $rateLimiter->tooManyAttempts(
            $this->getThrottleKey($request),
            $this->maxLoginAttempts(),
            $this->lockoutTime() / 60
        );
    }

    /**
     * Incrémentez les tentatives de connexion de l'utilisateur.
     *
     * @param  \Two\Http\Request  $request
     * @return int
     */
    protected function incrementLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Two\Cache\RateLimiter');

        $rateLimiter->hit($this->getThrottleKey($request));
    }

    /**
     * Déterminez le nombre de tentatives restantes pour l'utilisateur.
     *
     * @param  \Two\Http\Request  $request
     * @return int
     */
    protected function retriesLeft(Request $request)
    {
        $rateLimiter = App::make('Two\Cache\RateLimiter');

        $attempts = $rateLimiter->attempts($this->getThrottleKey($request));

        return $this->maxLoginAttempts() - $attempts + 1;
    }

    /**
     * Redirigez l’utilisateur après avoir déterminé qu’il est verrouillé.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Http\RedirectResponse
     */
    protected function sendLockoutResponse(Request $request)
    {
        $rateLimiter = App::make('Two\Cache\RateLimiter');

        $seconds = $rateLimiter->availableIn($this->getThrottleKey($request));

        return Redirect::back()
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors(array(
                $this->loginUsername() => $this->getLockoutErrorMessage($seconds),
            ));
    }

    /**
     * Obtenez le message d’erreur de verrouillage de connexion.
     *
     * @param  int  $seconds
     * @return string
     */
    protected function getLockoutErrorMessage($seconds)
    {
        return __d('Two', 'Too many login attempts. Please try again in {0} seconds.', $seconds);
    }

    /**
     * Supprimez les verrous de connexion pour les informations d'identification de l'utilisateur fournies.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    protected function clearLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Two\Cache\RateLimiter');

        $rateLimiter->clear($this->getThrottleKey($request));
    }

    /**
     * Obtenez la clé d’accélérateur pour la demande donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return string
     */
    protected function getThrottleKey(Request $request)
    {
        return mb_strtolower($request->input($this->loginUsername())) .'|' .$request->ip();
    }

    /**
     * Obtenez le nombre maximum de tentatives de connexion pour retarder les tentatives supplémentaires.
     *
     * @return int
     */
    protected function maxLoginAttempts()
    {
        return property_exists($this, 'maxLoginAttempts') ? $this->maxLoginAttempts : 5;
    }

    /**
     * Le nombre de secondes pour retarder les tentatives de connexion ultérieures.
     *
     * @return int
     */
    protected function lockoutTime()
    {
        return property_exists($this, 'lockoutTime') ? $this->lockoutTime : 60;
    }

    /**
     * Obtenez le nom d’utilisateur de connexion à utiliser par le contrôleur.
     *
     * @return string
     */
    public function loginUsername()
    {
        return property_exists($this, 'username') ? $this->username : 'email';
    }
}

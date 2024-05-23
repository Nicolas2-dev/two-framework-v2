<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Auth;

use Closure;

use Two\Auth\AuthManager as Auth;
use Two\Auth\Exception\AuthenticationException;


class Authenticate
{
    /**
     * L'instance de fabrique d'authentification.
     *
     * @var \Two\Auth\AuthManager
     */
    protected $auth;


    /**
     * Créez une nouvelle instance de middleware.
     *
     * @param  \Two\Auth\AuthManager  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $guards = array_slice(func_get_args(), 2);

        $this->authenticate($guards);

        return $next($request);
    }

    /**
     * Déterminez si l’utilisateur est connecté à l’un des gardes donnés.
     *
     * @param  array  $guards
     * @return void
     *
     * @throws \Two\Auth\Exception\AuthenticationException
     */
    protected function authenticate(array $guards)
    {
        if (empty($guards)) {
            return $this->auth->authenticate();
        }

        foreach ($guards as $guard) {
            $auth = $this->auth->guard($guard);

            if ($auth->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        throw new AuthenticationException('Unauthenticated.', $guards);
    }
}

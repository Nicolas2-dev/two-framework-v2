<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Auth;

use Closure;

use Two\Support\Facades\Auth;


class AuthenticateWithBasicAuth
{
    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        return Auth::guard($guard)->basic() ?: $next($request);
    }
}

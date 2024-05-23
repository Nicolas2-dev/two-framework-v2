<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Http;

use Closure;


class FrameGuard
{
    /**
     * Traitez la demande donnée et obtenez la réponse.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return \Two\Http\Response
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        return $response;
    }
}

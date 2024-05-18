<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\TwoApplication\Middleware\Cookie;

use Closure;

use Two\TwoApplication\TwoApplication;


class AddQueuedCookiesToResponse
{
    /**
     * L'instance du pot à cookies.
     *
     * @var \Two\Cookie\CookieJar
     */
    protected $cookies;

    /**
     * Créez une nouvelle instance de CookieQueue.
     *
     * @param  Two\TwoApplication\TwoApplication  $app
     * @return void
     */
    public function __construct(TwoApplication $app)
    {
        $this->cookies = $app['cookie'];
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
        $response = $next($request);

        foreach ($this->cookies->getQueuedCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}

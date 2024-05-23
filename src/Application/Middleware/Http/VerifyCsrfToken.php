<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Http;

use Closure;

use Two\Support\Str;
use Two\Application\Two;
use Two\Encryption\Encrypter;

use Symfony\Component\HttpFoundation\Cookie;
use Two\Session\Exception\TokenMismatchException;


class VerifyCsrfToken
{
    /**
     * La mise en œuvre de l'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * L’implémentation du chiffreur.
     *
     * @var \Two\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * Les URI qui doivent être exclus de la vérification CSRF.
     *
     * @var array
     */
    protected $except = array();


    /**
     * Créez une nouvelle instance de middleware.
     *
     * @param  \Two\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct(Two $app, Encrypter $encrypter)
    {
        $this->app = $app;

        $this->encrypter = $encrypter;
    }

    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Two\Session\Exception\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        if ($this->isReading($request) || $this->shouldPassThrough($request) || $this->tokensMatch($request)) {
            $response = $next($request);

            return $this->addCookieToResponse($request, $response);
        }

        throw new TokenMismatchException;
    }

    /**
     * Déterminez si la demande a un URI qui doit passer par la vérification CSRF.
     *
     * @param  \Two\Http\Request  $request
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déterminez si la session et les jetons CSRF d’entrée correspondent.
     *
     * @param  \Two\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $sessionToken = $request->session()->token();

        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (is_null($token) && ! is_null($header = $request->header('X-XSRF-TOKEN'))) {
            $token = $this->encrypter->decrypt($header);
        }

        if (! is_string($sessionToken) || ! is_string($token)) {
            return false;
        }

        return Str::equals($sessionToken, $token);
    }

    /**
     * Ajoutez le jeton CSRF aux cookies de réponse.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Two\Http\Response  $response
     * @return \Two\Http\Response
     */
    protected function addCookieToResponse($request, $response)
    {
        $config = $this->app['config']['session'];

        $cookie = new Cookie(
            'XSRF-TOKEN',
            $request->session()->token(),
            time() + 60 * 120,
            $config['path'],
            $config['domain'],
            $config['secure'], false
        );

        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Déterminez si la requête HTTP utilise un verbe « lire ».
     *
     * @param  \Two\Http\Request  $request
     * @return bool
     */
    protected function isReading($request)
    {
        return in_array($request->method(), array('HEAD', 'GET', 'OPTIONS'));
    }
}

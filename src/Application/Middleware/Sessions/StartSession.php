<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Sessions;

use Closure;

use Carbon\Carbon;
use Two\Support\Arr;
use Two\Session\Contracts\SessionInterface;
//use Two\Session\SessionInterface;



use Two\Http\Request;
use Two\Session\SessionManager;
use Two\Session\CookieSessionHandler;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;


class StartSession
{
    /**
     * Le gestionnaire de séance.
     *
     * @var \Two\Session\SessionManager
     */
    protected $manager;

    /**
     * Indique si la session a été gérée pour la demande en cours.
     *
     * @var bool
     */
    protected $sessionHandled = false;


    /**
     * Créez un nouveau middleware de session.
     *
     * @param  \Two\Session\SessionManager  $manager
     * @return void
     */
    public function __construct(SessionManager $manager)
    {
        $this->manager = $manager;
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
        $this->sessionHandled = true;

        //
        $sessionConfigured = $this->sessionConfigured();

        if ($sessionConfigured) {
            $session = $this->startSession($request);

            $request->setSession($session);
        }

        $response = $next($request);

        if ($sessionConfigured) {
            $this->storeCurrentUrl($request, $session);

            $this->collectGarbage($session);

            $this->addCookieToResponse($response, $session);
        }

        return $response;
    }

    /**
     * Effectuez toutes les actions finales pour le cycle de vie de la demande.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $sessionConfigured = $this->sessionConfigured();

        if ($this->sessionHandled && $sessionConfigured && ! $this->usingCookieSessions()) {
            $this->manager->driver()->save();
        }
    }

    /**
     * Démarrez la session pour la demande donnée.
     *
     * @param  \Two\Http\Request  $request
     * @return \Two\Session\Contracts\SessionInterface
     */
    protected function startSession(Request $request)
    {
        $session = $this->manager->driver();

        $session->setId(
            $this->getSessionId($request, $session)
        );

        $session->setRequestOnHandler($request);

        $session->start();

        return $session;
    }

    /**
     * Obtenez l'ID de session à partir de la demande.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Two\Session\Contracts\SessionInterface $session
     * @return string|null
     */
    protected function getSessionId(Request $request, SessionInterface $session)
    {
        $name = $session->getName();

        return $request->cookies->get($name);
    }

    /**
     * Stockez l'URL actuelle de la demande si nécessaire.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Two\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function storeCurrentUrl(Request $request, SessionInterface $session)
    {
        if (($request->method() === 'GET') && $request->route() && ! $request->ajax()) {
            $session->setPreviousUrl($request->fullUrl());
        }
    }

    /**
     * Retirez les déchets de la session si nécessaire.
     *
     * @param  \Two\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function collectGarbage(SessionInterface $session)
    {
        $config = $this->getSessionConfig();

        if ($this->configHitsLottery($config)) {
            $lifetime = Arr::get($config, 'lifetime', 180);

            $session->getHandler()->gc($lifetime * 60);
        }
    }

    /**
     * Déterminez si les chances de configuration atteignent la loterie.
     *
     * @param  array  $config
     * @return bool
     */
    protected function configHitsLottery(array $config)
    {
        list ($trigger, $max) = $config['lottery'];

        $value = mt_rand(1, $max);

        return ($value <= $trigger);
    }

    /**
     * Ajoutez le cookie de session à la réponse de l'application.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Two\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function addCookieToResponse(Response $response, SessionInterface $session)
    {
        if ($this->usingCookieSessions()) {
            $this->manager->driver()->save();
        }

        $config = $this->getSessionConfig();

        if ($this->sessionIsPersistent($config)) {
            $cookie = $this->createCookie($config, $session);

            $response->headers->setCookie($cookie);
        }
    }

    /**
     * Créez une instance de Cookie pour la session et la configuration spécifiées.
     *
     * @param  array  $config
     * @param  \Two\Session\Contracts\SessionInterface  $session
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function createCookie(array $config, SessionInterface $session)
    {
        $expireOnClose = Arr::get($config, 'expireOnClose', false);

        if ($expireOnClose !== false) {
            $lifetime = Arr::get($config, 'lifetime', 180);

            $expire = Carbon::now()->addMinutes($lifetime);
        } else {
            $expire = 0;
        }

        $secure = Arr::get($config, 'secure', false);

        return new Cookie(
            $session->getName(),
            $session->getId(),
            $expire,
            $config['path'],
            $config['domain'],
            $secure
        );
    }

    /**
     * Déterminez si un pilote de session a été configuré.
     *
     * @return bool
     */
    protected function sessionConfigured()
    {
        $config = $this->getSessionConfig();

        return Arr::has($config, 'driver');
    }

    /**
     * Déterminez si le pilote de session configuré est persistant.
     *
     * @param  array|null  $config
     * @return bool
     */
    protected function sessionIsPersistent(array $config = null)
    {
        if (is_null($config)) {
            $config = $this->getSessionConfig();
        }

        return ! in_array($config['driver'], array(null, 'array'));
    }

    /**
     * Déterminez si la session utilise des sessions de cookies.
     *
     * @return bool
     */
    protected function usingCookieSessions()
    {
        if (! $this->sessionConfigured()) {
            return false;
        }

        $session = $this->manager->driver();

        //
        $handler = $session->getHandler();

        return ($handler instanceof CookieSessionHandler);
    }


    /**
     * Renvoie la configuration de session du gestionnaire.
     *
     * @return array
     */
    protected function getSessionConfig()
    {
        return $this->manager->getSessionConfig();
    }
}

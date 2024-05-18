<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use SessionHandlerInterface;

use Two\Cookie\CookieJar;

use Symfony\Component\HttpFoundation\Request;


class CookieSessionHandler implements SessionHandlerInterface
{
    /**
     * L'instance du pot à cookies.
     *
     * @var \Two\Cookie\CookieJar
     */
    protected $cookies;

    /**
     * L’instance de requête.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /** 
     * Créez une nouvelle instance de gestionnaire pilotée par les cookies.
     */
    protected $minutes;

    /**
     * 
     *
     * @param  \Two\Cookie\CookieJar  $cookie
     * @param  int  $minutes
     * @return void
     */
    public function __construct(CookieJar $cookies, $minutes)
    {
        $this->cookies = $cookies;
        $this->minutes = $minutes;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId): bool
    {
        $cookie = $this->getSessionCookie($sessionId);

        return $this->request->cookies->get($cookie) ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data): bool
    {
        $cookie = $this->getSessionCookie($sessionId);

        $this->cookies->queue($cookie, $data, $this->minutes);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId): bool
    {
        $cookie = $this->getSessionCookie($sessionId);

        $this->cookies->queue($this->cookies->forget($cookie));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime): bool
    {
        return true;
    }

    /**
     * Définissez l'instance de requête.
     *
     * @param  string  $sessionId
     * @return string
     */
    protected function getSessionCookie($sessionId)
    {
        return PREFIX .'session_' .$sessionId;
    }

    /**
     * Définissez l'instance de requête.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

}

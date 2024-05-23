<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Cookie;

use Closure;

use Two\Encryption\Exception\DecryptException;
use Two\Encryption\Encrypter;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class EncryptCookies
{
    /**
     * L'instance du chiffreur.
     *
     * @var \Two\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * Les noms des cookies qui ne doivent pas être cryptés.
     *
     * @var array
     */
    protected $except = array();


    /**
     * Créez une nouvelle instance CookieGuard.
     *
     * @param  \Two\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    /**
     * Désactivez le cryptage pour le(s) nom(s) de cookie donné(s).
     *
     * @param string|array $cookieName
     * @return void
     */
    public function disableFor($cookieName)
    {
        $this->except = array_merge($this->except, (array) $cookieName);
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
        $response = $next($this->decrypt($request));

        return $this->encrypt($response);
    }

    /**
     * Décryptez les cookies sur demande.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function decrypt(Request $request)
    {
        foreach ($request->cookies as $key => $c) {
            if ($this->isDisabled($key)) {
                continue;
            }

            try {
                $request->cookies->set($key, $this->decryptCookie($c));
            } catch (DecryptException $e) {
                $request->cookies->set($key, null);
            }
        }

        return $request;
    }

    /**
     * Décryptez le cookie donné et renvoyez la valeur.
     *
     * @param  string|array  $cookie
     * @return string|array
     */
    protected function decryptCookie($cookie)
    {
        if (is_array($cookie)) {
            return $this->decryptArray($cookie);
        }

        return $this->encrypter->decrypt($cookie);
    }

    /**
     * Décryptez un cookie basé sur un tableau.
     *
     * @param  array  $cookie
     * @return array
     */
    protected function decryptArray(array $cookie)
    {
        $decrypted = array();

        foreach ($cookie as $key => $value) {
            if (is_string($value)) {
                $decrypted[$key] = $this->encrypter->decrypt($value);
            }
        }

        return $decrypted;
    }

    /**
     * Chiffrez les cookies sur une réponse sortante.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function encrypt(Response $response)
    {
        $cookies = $response->headers->getCookies();

        foreach ($cookies as $cookie) {
            if ($this->isDisabled($cookie->getName())) {
                continue;
            }

            $value = $cookie->getValue();

            $response->headers->setCookie($this->duplicate(
                $cookie, $this->encrypter->encrypt($value)
            ));
        }

        return $response;
    }

    /**
     * Dupliquez un cookie avec une nouvelle valeur.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie  $c
     * @param  mixed  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function duplicate(Cookie $cookie, $value)
    {
        return new Cookie(
            $cookie->getName(),
            $value,
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly()
        );
    }

    /**
     * Déterminez si le cryptage a été désactivé pour le cookie donné.
     *
     * @param  string $name
     * @return bool
     */
    public function isDisabled($name)
    {
        return in_array($name, $this->except);
    }
}

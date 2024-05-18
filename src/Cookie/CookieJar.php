<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cookie;

use Symfony\Component\HttpFoundation\Cookie;


class CookieJar
{
    /**
     * Le chemin par défaut (si spécifié).
     *
     * @var string
     */
    protected $path = '/';

    /**
     * Le domaine par défaut (si spécifié).
     *
     * @var string
     */
    protected $domain = null;

    /**
     * Tous les cookies en file d'attente pour l'envoi.
     *
     * @var array
     */
    protected $queued = array();

    /**
     * Créez une nouvelle instance de Cookie.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int     $minutes
     * @param  string  $path
     * @param  string  $domain
     * @param  bool    $secure
     * @param  bool    $httpOnly
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function make($name, $value, $minutes = 0, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        list($path, $domain) = $this->getPathAndDomain($path, $domain);

        $time = ($minutes == 0) ? 0 : time() + ($minutes * 60);

        return new Cookie($name, $value, $time, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Créez un cookie qui dure « pour toujours » (cinq ans).
     *
     * @param  string  $name
     * @param  string  $value
     * @param  string  $path
     * @param  string  $domain
     * @param  bool    $secure
     * @param  bool    $httpOnly
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forever($name, $value, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        return $this->make($name, $value, 2628000, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Faire expirer le cookie donné.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string  $domain
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forget($name, $path = null, $domain = null)
    {
        return $this->make($name, null, -2628000, $path, $domain);
    }

    /**
     * Déterminez si un cookie a été mis en file d'attente.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasQueued($key)
    {
        return ! is_null($this->queued($key));
    }

    /**
     * Obtenez une instance de Cookie en file d'attente.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function queued($key, $default = null)
    {
        return array_get($this->queued, $key, $default);
    }

    /**
     * Mettez en file d'attente un cookie à envoyer avec la prochaine réponse.
     *
     * @param  dynamic
     * @return void
     */
    public function queue()
    {
        if (head(func_get_args()) instanceof Cookie) {
            $cookie = head(func_get_args());
        } else {
            $cookie = call_user_func_array(array($this, 'make'), func_get_args());
        }

        $name = $cookie->getName();

        $this->queued[$name] = $cookie;
    }

    /**
     * Supprimez un cookie de la file d'attente.
     *
     * @param $cookieName
     */
    public function unqueue($name)
    {
        unset($this->queued[$name]);
    }

    /**
     * Obtenez le chemin et le domaine, ou les valeurs par défaut.
     *
     * @param  string  $path
     * @param  string  $domain
     * @return array
     */
    protected function getPathAndDomain($path, $domain)
    {
        return array($path ?: $this->path, $domain ?: $this->domain);
    }

    /**
     * Définissez le chemin et le domaine par défaut du fichier jar.
     *
     * @param  string  $path
     * @param  string  $domain
     * @return self
     */
    public function setDefaultPathAndDomain($path, $domain)
    {
        list($this->path, $this->domain) = array($path, $domain);

        return $this;
    }

    /**
     * Récupère les cookies qui ont été mis en file d'attente pour la prochaine requête
     *
     * @return array
     */
    public function getQueuedCookies()
    {
        return $this->queued;
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Two\Http\RedirectResponse;
use Two\Session\Store as SessionStore;


class Redirector
{
    /**
     * L'instance du générateur d'URL.
     *
     * @var \Two\Routing\UrlGenerator
     */
    protected $generator;

    /**
     * Instance de magasin de sessions.
     *
     * @var \Two\Session\Store
     */
    protected $session;

    /**
     * Créez une nouvelle instance de redirecteur.
     *
     * @param  \Two\Routing\UrlGenerator  $generator
     * @return void
     */
    public function __construct(UrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Créez une nouvelle réponse de redirection vers la route « home ».
     *
     * @param  int  $status
     * @return \Two\Http\RedirectResponse
     */
    public function home($status = 302)
    {
        return $this->to($this->generator->route('home'), $status);
    }

    /**
     * Créez une nouvelle réponse de redirection vers l'emplacement précédent.
     *
     * @param  int    $status
     * @param  array  $headers
     * @return \Two\Http\RedirectResponse
     */
    public function back($status = 302, $headers = array())
    {
        return $this->createRedirect($this->generator->previous(), $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection vers l'URI actuel.
     *
     * @param  int    $status
     * @param  array  $headers
     * @return \Two\Http\RedirectResponse
     */
    public function refresh($status = 302, $headers = array())
    {
        // Collection path
        return $this->to($this->generator->getRequest()->path(), $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection, tout en mettant l'URL actuelle dans la session.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \Two\Http\RedirectResponse
     */
    public function guest($path, $status = 302, $headers = array(), $secure = null)
    {
        $this->session->put('url.intended', $this->generator->full());

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Créez une nouvelle réponse de redirection vers l'emplacement précédemment prévu.
     *
     * @param  string  $default
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \Two\Http\RedirectResponse
     */
    public function intended($default = '/', $status = 302, $headers = array(), $secure = null)
    {
        $path = $this->session->pull('url.intended', $default);

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Créez une nouvelle réponse de redirection à partir du chemin et des arguments donnés.
     *
     * @return \Two\Http\RedirectResponse
     */
    public function url()
    {
        if (empty($parameters = func_get_args())) {
            return $this->to('/');
        }

        $path = array_shift($parameters);

        $result = preg_replace_callback('#\{(\d+)\}#', function ($matches) use ($parameters)
        {
            list ($value, $key) = $matches;

            return isset($parameters[$key]) ? $parameters[$key] : $value;

        }, $path);

        return $this->to($path);
    }

    /**
     * Créez une nouvelle réponse de redirection vers le chemin donné.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \Two\Http\RedirectResponse
     */
    public function to($path, $status = 302, $headers = array(), $secure = null)
    {
        $path = $this->generator->to($path, array(), $secure);

        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection vers une URL externe (pas de validation).
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \Two\Http\RedirectResponse
     */
    public function away($path, $status = 302, $headers = array())
    {
        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection vers le chemin HTTPS donné.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \Two\Http\RedirectResponse
     */
    public function secure($path, $status = 302, $headers = array())
    {
        return $this->to($path, $status, $headers, true);
    }

    /**
     * Créez une nouvelle réponse de redirection vers une route nommée.
     *
     * @param  string  $route
     * @param  array   $parameters
     * @param  int     $status
     * @param  array   $headers
     * @return \Two\Http\RedirectResponse
     */
    public function route($route, $parameters = array(), $status = 302, $headers = array())
    {
        $path = $this->generator->route($route, $parameters);

        return $this->to($path, $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection à une action du contrôleur.
     *
     * @param  string  $action
     * @param  array   $parameters
     * @param  int     $status
     * @param  array   $headers
     * @return \Two\Http\RedirectResponse
     */
    public function action($action, $parameters = array(), $status = 302, $headers = array())
    {
        $path = $this->generator->action($action, $parameters);

        return $this->to($path, $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de redirection.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \Two\Http\RedirectResponse
     */
    protected function createRedirect($path, $status, $headers)
    {
        $redirect = new RedirectResponse($path, $status, $headers);

        if (isset($this->session))
        {
            $redirect->setSession($this->session);
        }

        $redirect->setRequest($this->generator->getRequest());

        return $redirect;
    }

    /**
     * Obtenez l'instance du générateur d'URL.
     *
     * @return  \Two\Routing\UrlGenerator
     */
    public function getUrlGenerator()
    {
        return $this->generator;
    }

    /**
     * Définissez le magasin de sessions actif.
     *
     * @param  \Two\Session\Store  $session
     * @return void
     */
    public function setSession(SessionStore $session)
    {
        $this->session = $session;
    }

}

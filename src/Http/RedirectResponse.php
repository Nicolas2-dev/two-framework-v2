<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use BadMethodCallException;

use Two\Support\Str;
use Two\Support\MessageBag;
use Two\View\ViewErrorBag;
use Two\Session\Store as SessionStore;
use Two\Application\Contracts\MessageProviderInterface;

use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;


class RedirectResponse extends SymfonyRedirectResponse
{
    /**
     * L’instance de requête.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * L’implémentation du magasin de sessions.
     *
     * @var \Two\Session\Store
     */
    protected $session;

    /**
     * Définissez un en-tête sur la réponse.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  bool  $replace
     * @return $this
     */
    public function header($key, $value, $replace = true)
    {
        $this->headers->set($key, $value, $replace);

        return $this;
    }

    /**
     * Flashez une donnée dans la session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return \Two\Http\RedirectResponse
     */
    public function with($key, $value = null)
    {
        $key = is_array($key) ? $key : [$key => $value];

        foreach ($key as $k => $v) {
            $this->session->flash($k, $v);
        }

        return $this;
    }

    /**
     * Ajoutez un cookie à la réponse.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie  $cookie
     * @return $this
     */
    public function withCookie(SymfonyCookie $cookie)
    {
        $this->headers->setCookie($cookie);

        return $this;
    }

    /**
     * Ajoutez plusieurs cookies à la réponse.
     *
     * @param  array  $cookie
     * @return $this
     */
    public function withCookies(array $cookies)
    {
        foreach ($cookies as $cookie)
        {
            $this->headers->setCookie($cookie);
        }

        return $this;
    }

    /**
     * Flashez un tableau d’entrées dans la session.
     *
     * @param  array  $input
     * @return $this
     */
    public function withInput(array $input = null)
    {
        $input = $input ?: $this->request->input();

        $this->session->flashInput(array_filter($input, function ($value)
        {
            return ! $value instanceof SymfonyUploadedFile;
        }));

        return $this;
    }

    /**
     * Flashez un tableau d’entrées dans la session.
     *
     * @param  mixed  string
     * @return $this
     */
    public function onlyInput()
    {
        return $this->withInput($this->request->only(func_get_args()));
    }

    /**
     * Flashez un tableau d’entrées dans la session.
     *
     * @param  mixed  string
     * @return \Two\Http\RedirectResponse
     */
    public function exceptInput()
    {
        return $this->withInput($this->request->except(func_get_args()));
    }

    /**
     * Flashez un conteneur d’erreurs dans la session.
     *
     * @param  \Two\Application\Contracts\MessageProviderInterface|array  $provider
     * @param  string  $key
     * @return $this
     */
    public function withErrors($provider, $key = 'default')
    {
        $value = $this->parseErrors($provider);

        $this->session->flash(
            'errors', $this->session->get('errors', new ViewErrorBag)->put($key, $value)
        );

        return $this;
    }

    /**
     * Analysez les erreurs données en une valeur appropriée.
     *
     * @param  \Two\Application\Contracts\MessageProviderInterface|array  $provider
     * @return \Two\Support\MessageBag
     */
    protected function parseErrors($provider)
    {
        if ($provider instanceof MessageBag) {
            return $provider;
        } else if ($provider instanceof MessageProviderInterface) {
            return $provider->getMessageBag();
        }

        return new MessageBag((array) $provider);
    }

    /**
     * Obtenez l’instance de requête.
     *
     * @return  \Two\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Définissez l'instance de requête.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Obtenez l’implémentation du magasin de sessions.
     *
     * @return \Two\Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Définissez l’implémentation du magasin de sessions.
     *
     * @param  \Two\Session\Store  $session
     * @return void
     */
    public function setSession(SessionStore $session)
    {
        $this->session = $session;
    }

    /**
     * Liez dynamiquement les données Flash dans la session.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'with')) {
            return $this->with(Str::snake(substr($method, 4)), $parameters[0]);
        }

        throw new BadMethodCallException("Method [$method] does not exist on Redirect.");
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;


trait ResponseTrait
{
    /**
     * Définissez un en-tête sur la réponse.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  bool    $replace
     * @return $this
     */
    public function header($key, $value, $replace = true)
    {
        $this->headers->set($key, $value, $replace);

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

}

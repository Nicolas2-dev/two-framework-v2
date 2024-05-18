<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http\Exception;

use RuntimeException;

use Symfony\Component\HttpFoundation\Response;


class HttpResponseException extends RuntimeException
{
    /**
     * L’instance de réponse sous-jacente.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * Créez une nouvelle instance d'exception de réponse HTTP.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Obtenez l’instance de réponse sous-jacente.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}

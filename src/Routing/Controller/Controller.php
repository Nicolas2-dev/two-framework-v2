<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Controller;

use BadMethodCallException;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


abstract class Controller
{
    /**
     * Le middleware enregistré sur le contrôleur.
     *
     * @var array
     */
    protected $middleware = array();


    /**
     * Enregistrez le middleware sur le contrôleur.
     *
     * @param  string  $middleware
     * @param  array   $options
     * @return void
     */
    public function middleware($middleware, array $options = array())
    {
        $this->middleware[$middleware] = $options;
    }

    /**
     * Obtenez le middleware attribué au contrôleur.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Gérez les appels aux méthodes manquantes sur le contrôleur.
     *
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function missingMethod($parameters = array())
    {
        throw new NotFoundHttpException('Controller method not found.');
    }

    /**
     * Gérez les appels aux méthodes manquantes sur le contrôleur.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        throw new BadMethodCallException("Method [$method] does not exist.");
    }
}

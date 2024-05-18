<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Controller;

use Two\Routing\Route;
use Two\Container\Container;
use Two\Routing\Traits\RouteDependencyResolverTrait;


class ControllerDispatcher
{
    use RouteDependencyResolverTrait;

    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;


    /**
     * Créez une nouvelle instance de répartiteur de contrôleur.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Envoyez une requête à un contrôleur et une méthode donnés.
     *
     * @param  \Two\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveClassMethodDependencies(
            $route->parametersWithoutNulls(), $controller, $method
        );

        if (! method_exists($controller, $callerMethod = 'callAction')) {
            return $this->run($controller, $method, $parameters);
        }

        return $this->run($controller, $callerMethod, $this->resolveClassMethodDependencies(
            array($method, $parameters), $controller, $callerMethod
        ));
    }

    /**
     * Exécute la méthode du contrôleur et renvoie la réponse.
     *
     * @param  mixed  $controller
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    protected function run($controller, $method, $parameters)
    {
        return call_user_func_array(array($controller, $method), $parameters);
    }

    /**
     * Obtenez le middleware pour l'instance de contrôleur.
     *
     * @param  mixed  $controller
     * @param  string  $method
     * @return array
     */
    public static function getMiddleware($controller, $method)
    {
        if (! method_exists($controller, 'getMiddleware')) {
            return array();
        }

        $middleware = $controller->getMiddleware();

        return array_keys(array_filter($middleware, function ($options) use ($method)
        {
            return ! static::methodExcludedByOptions($method, $options);
        }));
    }

    /**
     * Déterminez si les options données excluent une méthode particulière.
     *
     * @param  string  $method
     * @param  array  $options
     * @return bool
     */
    protected static function methodExcludedByOptions($method, array $options)
    {
        if (isset($options['only']) && ! in_array($method, (array) $options['only'])) {
            return true;
        }

        return isset($options['except']) && in_array($method, (array) $options['except']);
    }
}

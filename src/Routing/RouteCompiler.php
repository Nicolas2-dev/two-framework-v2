<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Two\Routing\Route;

use Symfony\Component\Routing\Route as SymfonyRoute;


class RouteCompiler
{
    /**
     * L'instance d'itinéraire.
     *
     * @var \Two\Routing\Route
     */
    protected $route;

    /**
     * Créez une nouvelle instance du compilateur Route.
     *
     * @param  \Two\Routing\Route  $route
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Compilez l'itinéraire.
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    public function compile()
    {
        $route = $this->getRoute();

        if (empty($domain = $route->domain())) {
            $domain = '';
        }

        $optionals = $this->extractOptionalParameters($uri = $route->uri());

        $path = preg_replace('/\{(\w+?)\?\}/', '{$1}', $uri);

        return with(
            new SymfonyRoute($path, $optionals, $route->patterns(), array(), $domain)

        )->compile();
    }

    /**
     * Obtenez les paramètres facultatifs pour l’itinéraire.
     *
     * @param string $uri
     *
     * @return array
     */
    protected function extractOptionalParameters($uri)
    {
        preg_match_all('/\{(\w+?)\?\}/', $uri, $matches);

        return isset($matches[1]) ? array_fill_keys($matches[1], null) : array();
    }

    /**
     * Obtenez l’instance de route interne.
     *
     * @return \Two\Routing\Route
     */
    public function getRoute()
    {
        return $this->route;
    }
}

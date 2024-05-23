<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages\Providers;

use Two\Application\Providers\RouteServiceProvider as ServiceProvider;
use Two\Routing\Router;


class RouteServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Définissez les itinéraires du module.
     *
     * @param  \Two\Routing\Router $router
     * @return void
     */
    public function map(Router $router)
    {
        $router->group(array('namespace' => $this->namespace), function ($router)
        {
            $basePath = $this->guessPackageRoutesPath();

            if (is_readable($path = $basePath .DS .'Api.php')) {
                $router->group(array('prefix' => 'api', 'middleware' => 'api'), function ($router) use ($path)
                {
                    require $path;
                });
            }

            if (is_readable($path = $basePath .DS .'Web.php')) {
                $router->group(array('middleware' => 'web'), function ($router) use ($path)
                {
                    require $path;
                });
            }
        });
    }

    /**
     * Devinez le chemin du package pour le fournisseur.
     *
     * @return string
     */
    public function guessPackageRoutesPath()
    {
        $path = $this->guessPackagePath();

        return $path .DS .'Routes';
    }

    /**
     * Ajoutez un middleware au routeur.
     *
     * @param array $routeMiddleware
     */
    protected function addRouteMiddleware($routeMiddleware)
    {
        if (is_array($routeMiddleware) && (count($routeMiddleware) > 0)) {
            foreach ($routeMiddleware as $key => $middleware) {
                $this->middleware($key, $middleware);
            }
        }
    }

    /**
     * Ajoutez des groupes de middleware au routeur.
     *
     * @param array $middlewareGroups
     */
    protected function addMiddlewareGroups($middlewareGroups)
    {
        if (is_array($middlewareGroups) && (count($middlewareGroups) > 0)) {
            foreach ($middlewareGroups as $key => $groupMiddleware) {
                foreach ($groupMiddleware as $middleware) {
                    $this->pushMiddlewareToGroup($key, $middleware);
                }
            }
        }
    }
}

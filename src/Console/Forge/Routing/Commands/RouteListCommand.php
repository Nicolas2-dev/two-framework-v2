<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Routing\Commands;

use Closure;

use Two\Routing\Route;
use Two\Routing\Router;
use Two\Console\Commands\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;


class RouteListCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'routes:list';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'List all registered routes';

    /**
     * L'instance du routeur.
     *
     * @var \Two\Routing\Router
     */
    protected $router;

    /**
     * Un tableau de tous les itinéraires enregistrés.
     *
     * @var \Two\Routing\RouteCollection
     */
    protected $routes;

    /**
     * L'ensemble d'aide à la table.
     *
     * @var \Symfony\Component\Console\Helper\TableHelper
     */
    protected $table;

    /**
     * Les en-têtes de tableau pour la commande.
     *
     * @var array
     */
    protected $headers = array(
        'Domain', 'Method', 'URI', 'Name', 'Action', 'Middleware'
    );

    /**
     * Créez une nouvelle instance de commande de route.
     *
     * @param  \Two\Routing\Router  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        parent::__construct();

        $this->router = $router;

        $this->routes = $router->getRoutes();
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->table = new Table($this->output);

        if (count($this->routes) == 0) {
            return $this->error("Your application doesn't have any routes.");
        }

        $this->displayRoutes($this->getRoutes());
    }

    /**
     * Compilez les itinéraires dans un format affichable.
     *
     * @return array
     */
    protected function getRoutes()
    {
        $routes = $this->routes->getRoutes();

        //
        $fallbacks = array();

        foreach ($routes as $key => $route) {
            if (! $route->isFallback()) {
                continue;
            }

            $fallbacks[$key] = $route;

            unset($routes[$key]);
        }

        return array_map(function ($route)
        {
            return $this->getRouteInformation($route);

        }, array_merge($routes, $fallbacks));
    }

    /**
     * Obtenez les informations d'itinéraire pour un itinéraire donné.
     *
     * @param  string  $name
     * @param  \Two\Routing\Route  $route
     * @return array
     */
    protected function getRouteInformation(Route $route)
    {
        $methods = implode('|', $route->methods());

        $action = str_replace('\\Controllers\\', '\\...\\', $route->getActionName());

        $middleware = implode(', ', $this->getMiddleware($route));

        return $this->filterRoute(array(
            'host'       => $route->domain(),
            'method'     => $methods,
            'uri'        => $route->uri(),
            'name'       => $route->getName(),
            'action'     => $action,
            'middleware' => $middleware
        ));
    }

    /**
     * Affichez les informations d'itinéraire sur la console.
     *
     * @param  array  $routes
     * @return void
     */
    protected function displayRoutes(array $routes)
    {
        $this->table->setHeaders($this->headers)->setRows($routes);

        $this->table->render($this->getOutput());
    }

    /**
     * Obtenez avant les filtres.
     *
     * @param  \Two\Routing\Route  $route
     * @return string
     */
    protected function getMiddleware($route)
    {
        return array_map(function ($middleware)
        {
            if ($middleware instanceof Closure) {
                return 'Closure';
            }

            return str_replace('\\Middleware\\', '\\...\\', $middleware);

        }, $route->middleware());
    }

    /**
     * Filtrez l'itinéraire par URI et/ou nom.
     *
     * @param  array  $route
     * @return array|null
     */
    protected function filterRoute(array $route)
    {
        if (($this->option('name') && ! str_contains($route['name'], $this->option('name'))) ||
            $this->option('path') && ! str_contains($route['uri'], $this->option('path'))) {
            return null;
        }

        return $route;
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('name', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by name.'),
            array('path', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by path.'),
        );
    }

}

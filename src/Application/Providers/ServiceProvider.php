<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Providers;

use ReflectionClass;
use BadMethodCallException;

use Two\Support\Str;
use Two\Application\Two;


abstract class ServiceProvider
{

    /**
     * L'exemple d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Indique si le chargement du provider est différé.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Les chemins qui doivent être publiés.
     *
     * @var array
     */
    protected static $publishes = array();


    /**
     * Créer une nouvelle instance de fournisseur de services.
     *
     * @param  \Two\Application\Two     $app
     * @return void
     */
    public function __construct(Two $app)
    {
        $this->app = $app;
    }

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    abstract public function register();

    /**
     * Enregistrez les espaces de noms des composants du package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @param  string  $path
     * @return void
     */
    public function package($package, $namespace = null, $path = null)
    {
        $namespace = $this->getPackageNamespace($package, $namespace);

        //
        $files = $this->app['files'];

        // Dans cette méthode, nous enregistrerons le package de configuration pour le package
        // pour que les options de configuration se cascadent proprement dans l'application
        // dossier pour faciliter la vie des développeurs dans leur maintenance.
        $path = $path ?: $this->guessPackagePath();

        // Enregistrez le chemin de configuration du package.
        $config = $path .DS .'Config';

        if ($files->isDirectory($config)) {
            $this->app['config']->package($package, $config, $namespace);
        }

        // Enregistre le chemin d'accès à la langue du package.
        $language = $path .DS .'Language';

        if ($files->isDirectory($language)) {
            $this->app['language']->package($package, $language, $namespace);
        }

        // Enregistrez le chemin des vues de package.
        $views = $this->app['view'];

        $appView = $this->getAppViewPath($package);

        if ($files->isDirectory($appView)) {
            $views->addNamespace($package, $appView);
        }

        $viewPath = $path .DS .'Views';

        if ($files->isDirectory($viewPath)) {
            $views->addNamespace($package, $viewPath);
        }

        // Enfin, enregistrez le chemin Package Assets.
        $this->registerPackageAssets($package, $namespace, $path);
    }

    /**
     * Enregistrez les actifs du package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @param  string  $path
     * @return void
     */
    protected function registerPackageAssets($package, $namespace, $path)
    {
        $assets = dirname($path) .DS .'assets';

        if ($this->app['files']->isDirectory($assets)) {
            list ($vendor) = explode('/', $package);

            $namespace = Str::snake($vendor, '-') .'/' .str_replace('_', '-', $namespace);

            $this->app['assets.dispatcher']->package($package, $assets, $namespace);
        }
    }

    /**
     * Devinez le chemin du package pour le fournisseur.
     *
     * @return string
     */
    public function guessPackagePath()
    {
        $reflection = new ReflectionClass($this);

        $path = $reflection->getFileName();

        return realpath(dirname($path) .'/../');
    }

    /**
     * Déterminer l'espace de noms d'un package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @return string
     */
    protected function getPackageNamespace($package, $namespace)
    {
        if (is_null($namespace)) {
            list ($vendor, $namespace) = explode('/', $package);

            return Str::snake($namespace);
        }

        return $namespace;
    }

    /**
     * Enregistrez les chemins à publier par la commande de publication.
     *
     * @param  array  $paths
     * @param  string  $group
     * @return void
     */
    protected function publishes(array $paths, $group)
    {
        if (! array_key_exists($group, static::$publishes)) {
            static::$publishes[$group] = array();
        }

        static::$publishes[$group] = array_merge(static::$publishes[$group], $paths);
    }

    /**
     * Obtenez les chemins à publier.
     *
     * @param  string|null  $group
     * @return array
     */
    public static function pathsToPublish($group = null)
    {
        if (is_null($group)) {
            $paths = array();

            foreach (static::$publishes as $class => $publish) {
                $paths = array_merge($paths, $publish);
            }

            return array_unique($paths);
        } else if (array_key_exists($group, static::$publishes)) {
            return static::$publishes[$group];
        }

        return array();
    }

    /**
     * Enregistrez les commandes Forge personnalisées du package.
     *
     * @param  array  $commands
     * @return void
     */
    public function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        // Pour enregistrer les commandes avec Forge, nous allons saisir chacun des arguments
        // passé dans la méthode et écoute l'événement Forge "start" qui
        // nous donne l'instance de la console Forge à laquelle nous donnerons des commandes.
        $events = $this->app['events'];

        $events->listen('forge.start', function($forge) use ($commands)
        {
            $forge->resolveCommands($commands);
        });
    }

    /**
     * Obtenez le chemin d'accès à la vue du package d'application.
     *
     * @param  string  $package
     * @return string
     */
    protected function getAppViewPath($package)
    {
        return $this->app['path'] .str_replace('/', DS, "/Views/Packages/{$package}");
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    /**
     * Obtenez les événements qui déclenchent l'inscription de ce fournisseur de services.
     *
     * @return array
     */
    public function when()
    {
        return array();
    }

    /**
     * Déterminez si le fournisseur est différé.
     *
     * @return bool
     */
    public function isDeferred()
    {
        return $this->defer;
    }

    /**
     * Gérer dynamiquement les appels de méthode manquants.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'boot') {
            return;
        }

        throw new BadMethodCallException("Call to undefined method [{$method}]");
    }
}

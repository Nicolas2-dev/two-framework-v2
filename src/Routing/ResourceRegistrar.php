<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Two\Support\Str;


class ResourceRegistrar
{
    /**
     * L'instance du routeur.
     *
     * @var \Two\Routing\Router
     */
    protected $router;

    /**
     * Les actions par défaut pour un contrôleur ingénieux.
     *
     * @var array
     */
    protected $resourceDefaults = array('index', 'create', 'store', 'show', 'edit', 'update', 'destroy');


    /**
     * Créez une nouvelle instance de registraire de ressources.
     *
     * @param  \Two\Routing\Router  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Acheminer une ressource vers un contrôleur.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return void
     */
    public function register($name, $controller, array $options = array())
    {
        // Si le nom de la ressource contient une barre oblique, nous supposerons que le développeur souhaite
        // enregistre ces routes de ressources avec un préfixe afin que nous puissions les configurer hors de
        // la boîte pour qu'ils n'aient pas à s'en occuper. Sinon, nous continuerons.
        if (Str::contains($name, '/')) {
            $this->prefixedResource($name, $controller, $options);

            return;
        }

        // Nous devons extraire la ressource de base du nom de la ressource. Ressources imbriquées
        // sont pris en charge dans le framework, mais nous devons savoir quel nom utiliser pour un
        // espace réservé sur les caractères génériques de route, qui devraient être les ressources de base.
        $segments = explode('.', $name);

        $base = $this->getResourceWildcard(
            last($segments)
        );

        $methods = $this->getResourceMethods($this->resourceDefaults, $options);

        foreach ($methods as $method) {
            $method = 'addResource' .ucfirst($method);

            call_user_func(array($this, $method), $name, $base, $controller, $options);
        }
    }

    /**
     * Créez un ensemble d’itinéraires de ressources préfixés.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return void
     */
    protected function prefixedResource($name, $controller, array $options)
    {
        list ($name, $prefix) = $this->getResourcePrefix($name);

        // Nous devons extraire la ressource de base du nom de la ressource. Ressources imbriquées
        // sont pris en charge dans le framework, mais nous devons savoir quel nom utiliser pour un
        // espace réservé sur les caractères génériques de route, qui devraient être les ressources de base.

        return $this->router->group(compact('prefix'), function ($router) use ($name, $controller, $options)
        {
            $router->resource($name, $controller, $options);
        });
    }

    /**
     * Extrayez la ressource et le préfixe d'un nom de ressource.
     *
     * @param  string  $name
     * @return array
     */
    protected function getResourcePrefix($name)
    {
        $segments = explode('/', $name);

        // Pour obtenir le préfixe, nous prendrons tous les segments de nom et les imploserons
        // une barre oblique. Cela générera un préfixe URI approprié pour nous. Ensuite, nous prenons ceci
        // dernier segment, qui sera considéré comme le nom final des ressources que nous utiliserons.
        $prefix = implode('/', array_slice($segments, 0, -1));

        return array(
            end($segments), $prefix
        );
    }

    /**
     * Obtenez les méthodes de ressources applicables.
     *
     * @param  array  $defaults
     * @param  array  $options
     * @return array
     */
    protected function getResourceMethods($defaults, $options)
    {
        if (isset($options['only'])) {
            return array_intersect($defaults, (array) $options['only']);
        } else if (isset($options['except'])) {
            return array_diff($defaults, (array) $options['except']);
        }

        return $defaults;
    }

    /**
     * Obtenez l’URI de la ressource de base pour une ressource donnée.
     *
     * @param  string  $resource
     * @return string
     */
    public function getResourceUri($resource)
    {
        if (! Str::contains($resource, '.')) {
            return $resource;
        }

        // Une fois que nous aurons construit l'URI de base, nous supprimerons le détenteur du caractère générique pour cela.
        // nom de la ressource de base afin que les additionneurs de routes individuels puissent les suffixer
        // chemins comme ils le souhaitent, car certains n'ont aucun caractère générique.
        $segments = explode('.', $resource);

        $uri = $this->getNestedResourceUri($segments);

        $name = $this->getResourceWildcard(
            end($segments)
        );

        return str_replace('/{' .$name .'}', '', $uri);
    }

    /**
     * Obtenez l’URI d’un tableau de segments de ressources imbriqués.
     *
     * @param  array   $segments
     * @return string
     */
    protected function getNestedResourceUri(array $segments)
    {
        // Nous allons parcourir les segments et créer un espace réservé pour chacun des segments.
        // segments de ressources, ainsi que la ressource elle-même. Alors nous devrions obtenir un
        // chaîne entière pour l'URI de la ressource qui contient toutes les ressources imbriquées.
        return implode('/', array_map(function ($segment)
        {
            $name = $this->getResourceWildcard($segment);

            return $segment .'/{' .$name .'}';

        }, $segments));
    }

    /**
     * Obtenez le tableau d’actions pour un itinéraire de ressources.
     *
     * @param  string  $resource
     * @param  string  $controller
     * @param  string  $method
     * @param  array   $options
     * @return array
     */
    protected function getResourceAction($resource, $controller, $method, $options)
    {
        $name = $this->getResourceName($resource, $method, $options);

        return array('as' => $name, 'uses' => $controller .'@' .$method);
    }

    /**
     * Obtenez le nom d'une ressource donnée.
     *
     * @param  string  $resource
     * @param  string  $method
     * @param  array   $options
     * @return string
     */
    protected function getResourceName($resource, $method, $options)
    {
        if (isset($options['names'][$method])) {
            return $options['names'][$method];
        }

        // Si un préfixe global a été attribué à tous les noms de cette ressource, nous le ferons
        // récupère-le pour pouvoir l'ajouter au nom lorsque nous créons ce nom pour
        // l'action de la ressource. Sinon, nous utiliserons simplement une chaîne vide ici.
        $prefix = isset($options['as']) ? $options['as'] .'.' : '';

        if (! $this->router->hasGroupStack()) {
            return $prefix .$resource .'.' .$method;
        }

        return $this->getGroupResourceName($prefix, $resource, $method);
    }

    /**
     * Obtenez le nom de la ressource pour une ressource groupée.
     *
     * @param  string  $prefix
     * @param  string  $resource
     * @param  string  $method
     * @return string
     */
    protected function getGroupResourceName($prefix, $resource, $method)
    {
        $group = trim(
            str_replace('/', '.', $this->router->getLastGroupPrefix()), '.'
        );

        if (empty($group)) {
            return trim("{$prefix}{$resource}.{$method}", '.');
        }

        return trim("{$prefix}{$group}.{$resource}.{$method}", '.');
    }

    /**
     * Formatez un caractère générique de ressource à utiliser.
     *
     * @param  string  $value
     * @return string
     */
    public function getResourceWildcard($value)
    {
        return str_replace('-', '_', $value);
    }

    /**
     * Ajoutez la méthode index pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceIndex($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name);

        $action = $this->getResourceAction($name, $controller, 'index', $options);

        return $this->router->get($uri, $action);
    }

    /**
     * Ajoutez la méthode create pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceCreate($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name) .'/create';

        $action = $this->getResourceAction($name, $controller, 'create', $options);

        return $this->router->get($uri, $action);
    }

    /**
     * Ajoutez la méthode store pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceStore($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name);

        $action = $this->getResourceAction($name, $controller, 'store', $options);

        return $this->router->post($uri, $action);
    }

    /**
     * Ajoutez la méthode show pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceShow($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name) .'/{' .$base .'}';

        $action = $this->getResourceAction($name, $controller, 'show', $options);

        return $this->router->get($uri, $action);
    }

    /**
     * Ajoutez la méthode d'édition pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceEdit($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name) .'/{' .$base .'}/edit';

        $action = $this->getResourceAction($name, $controller, 'edit', $options);

        return $this->router->get($uri, $action);
    }

    /**
     * Ajoutez la méthode de mise à jour pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return void
     */
    protected function addResourceUpdate($name, $base, $controller, $options)
    {
        $this->addPutResourceUpdate($name, $base, $controller, $options);

        return $this->addPatchResourceUpdate($name, $base, $controller);
    }

    /**
     * Ajoutez la méthode de mise à jour pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addPutResourceUpdate($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name) .'/{' .$base .'}';

        $action = $this->getResourceAction($name, $controller, 'update', $options);

        return $this->router->put($uri, $action);
    }

    /**
     * Ajoutez la méthode de mise à jour pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @return void
     */
    protected function addPatchResourceUpdate($name, $base, $controller)
    {
        $uri = $this->getResourceUri($name) .'/{' .$base .'}';

        $this->router->patch($uri, $controller.'@update');
    }

    /**
     * Ajoutez la méthode destroy pour un itinéraire ingénieux.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return \Two\Routing\Route
     */
    protected function addResourceDestroy($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name) .'/{' .$base .'}';

        $action = $this->getResourceAction($name, $controller, 'destroy', $options);

        return $this->router->delete($uri, $action);
    }
}

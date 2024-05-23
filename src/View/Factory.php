<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Closure;
use Two\View\View;
use Two\Events\Dispatcher;

use BadMethodCallException;
use Two\Container\Container;
use InvalidArgumentException;
use Two\View\Engines\EngineResolver;
use Two\View\Contracts\ViewFinderInterface;
use Two\Application\Contracts\ArrayableInterface as Arrayable;


class Factory
{
    /**
     * L’instance du résolveur de moteurs.
     *
     * @var \Two\View\Engines\EngineResolver
     */
    protected $engines;

    /**
     * L’implémentation du viseur.
     *
     * @var \Two\View\Contracts\ViewFinderInterface
     */
    protected $finder;

    /**
     * Instance du répartiteur d’événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Tableau de données partagées
     * 
     * @var array 
     */
    protected $shared = array();

    /**
     * Tableau d’alias de nom de vue enregistrés.
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * Tous les noms de vues enregistrés.
     *
     * @var array
     */
    protected $names = array();

    /**
     * L'extension des liaisons Engine.
     *
     * @var array
     */
    protected $extensions = array(
        'tpl' => 'template',
        'php' => 'php',
        'css' => 'file',
        'js'  => 'file',
        'md'  => 'markdown',
    );

    /**
     * Les événements du compositeur de vue.
     *
     * @var array
     */
    protected $composers = array();

    /**
     * Toutes les sections terminées et capturées.
     *
     * @var array
     */
    protected $sections = array();

    /**
     * La pile des sections en cours.
     *
     * @var array
     */
    protected $sectionStack = array();

    /**
     * Le nombre d'opérations de rendu actives.
     *
     * @var int
     */
    protected $renderCount = 0;

    /**
     * Informations mises en cache sur les modules.
     *
     * @var array
     */
    protected $modules = array();

    /**
     * Informations mises en cache sur les plugins.
     *
     * @var array
     */
    protected $plugins = array();

    /**
     * Informations mises en cache sur le thème par défaut.
     *
     * @var string|null
     */
    protected $defaultTheme;

    /**
     * Informations mises en cache sur la langue actuelle.
     *
     * @var \Two\Language\Language
     */
    protected $language;


    /**
     * Créez une nouvelle instance de View Factory.
     *
     * @param  \Two\View\Engines\EngineResolver  $engines
     * @param  \Two\View\Contracts\ViewFinderInterface  $finder
     * @param  \Two\Events\Dispatcher  $events
     * @return void
     */
    function __construct(EngineResolver $engines, ViewFinderInterface $finder, Dispatcher $events)
    {
        $this->finder  = $finder;
        $this->events  = $events;
        $this->engines = $engines;

        //
        $this->share('__env', $this);
    }

    /**
     * Créer une instance de vue
     *
     * @param string $path
     * @param mixed $data
     * @param array $mergeData
     *
     * @return \Two\View\View
     */
    public function make($view, $data = array(), $mergeData = array())
    {
        if (isset($this->aliases[$view])) $view = $this->aliases[$view];

        $path = $this->finder->find($view);

        if (is_null($path) || ! is_readable($path)) {
            throw new BadMethodCallException("File path [$path] does not exist");
        }

        $data = array_except(
            array_merge($mergeData, $this->parseData($data)), array('__data', '__path')
        );

        $this->callCreator(
            $view = new View($this, $this->getEngineFromPath($path), $view, $path, $data)
        );

        return $view;
    }

    /**
     * Créez une instance View et renvoyez son contenu rendu.
     *
     * @return string
     */
    public function fetch($view, $data = array(), Closure $callback = null)
    {
        unset($data['__path'], $data['__path']);

        return $this->make($view, $data)->render($callback);
    }

    /**
     * Analysez les données données dans un tableau brut.
     *
     * @param  mixed  $data
     * @return array
     */
    protected function parseData($data)
    {
        return ($data instanceof Arrayable) ? $data->toArray() : $data;
    }

    /**
     * Obtenez le contenu de la vue évaluée pour une vue nommée.
     *
     * @param  string  $view
     * @param  mixed   $data
     * @return \Two\View\View
     */
    public function of($view, $data = array())
    {
        return $this->make($this->names[$view], $data);
    }

    /**
     * Enregistrez une vue nommée.
     *
     * @param  string  $view
     * @param  string  $name
     * @return void
     */
    public function name($view, $name)
    {
        $this->names[$name] = $view;
    }

    /**
     * Ajoutez un alias pour une vue.
     *
     * @param  string  $view
     * @param  string  $alias
     * @return void
     */
    public function alias($view, $alias)
    {
        $this->aliases[$alias] = $view;
    }

    /**
     * Vérifiez si le fichier de vue existe.
     *
     * @param    string     $view
     * @return    bool
     */
    public function exists($view)
    {
        try {
            $this->finder->find($view);
        }
        catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * Obtenez le contenu rendu d'un partiel à partir d'une boucle.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  string  $iterator
     * @param  string  $empty
     * @param  string|null  $module
     * @return string
     */
    public function renderEach($view, $data, $iterator, $empty = null)
    {
        $empty = ! empty($empty) ? $empty : 'raw|';

        //
        $result = '';

        // S'il y a réellement des données dans le tableau, nous allons parcourir les données et ajouter
        // une instance de la vue partielle du résultat final HTML passant dans le
        // valeur itérée de ce tableau de données, permettant aux vues d'y accéder.
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $data = array('key' => $key, $iterator => $value);

                $result .= $this->make($view, $data)->render();
            }
        }

        // S'il n'y a pas de données dans le tableau, nous rendrons le contenu du tableau vide
        // voir. Alternativement, la « vue vide » pourrait être une chaîne brute commençant par
        // avec "brut|" pour plus de commodité et pour faire savoir qu'il s'agit d'une chaîne.
        else if (! starts_with($empty, 'raw|')) {
            $result = $this->make($empty)->render();
        } else {
            $result = substr($empty, 4);
        }

        return $result;
    }

    /**
     * Obtenez le View Engine approprié pour le chemin donné.
     *
     * @param  string  $path
     * @return \Two\View\Contracts\Engines\EngineInterface
     */
    public function getEngineFromPath($path)
    {
        $extension = $this->getExtension($path);

        $engine = $this->extensions[$extension];

        return $this->engines->resolve($engine);
    }

    /**
     * Obtenez l'extension utilisée par le fichier de vue.
     *
     * @param  string  $path
     * @return string
     */
    protected function getExtension($path)
    {
        $extensions = array_keys($this->extensions);

        return array_first($extensions, function($key, $value) use ($path)
        {
            return ends_with($path, $value);
        });
    }

    /**
     * Ajoutez un élément de données partagées à la Factory.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function share($key, $value = null)
    {
        if (! is_array($key)) return $this->shared[$key] = $value;

        foreach ($key as $innerKey => $innerValue) {
            $this->share($innerKey, $innerValue);
        }
    }

    /**
     * Enregistrez un événement de création de vue.
     *
     * @param  array|string     $views
     * @param  \Closure|string  $callback
     * @return array
     */
    public function creator($views, $callback)
    {
        $creators = array();

        foreach ((array) $views as $view) {
            $creators[] = $this->addViewEvent($view, $callback, 'creating: ');
        }

        return $creators;
    }

    /**
     * Enregistrez plusieurs compositeurs de vues via un tableau.
     *
     * @param  array  $composers
     * @return array
     */
    public function composers(array $composers)
    {
        $registered = array();

        foreach ($composers as $callback => $views) {
            $registered = array_merge($registered, $this->composer($views, $callback));
        }

        return $registered;
    }

    /**
     * Enregistrez un événement View Composer.
     *
     * @param  array|string  $views
     * @param  \Closure|string  $callback
     * @param  int|null  $priority
     * @return array
     */
    public function composer($views, $callback, $priority = null)
    {
        $composers = array();

        foreach ((array) $views as $view) {
            $composers[] = $this->addViewEvent($view, $callback, 'composing: ', $priority);
        }

        return $composers;
    }

    /**
     * Ajoutez un événement pour une vue donnée.
     *
     * @param  string  $view
     * @param  \Closure|string  $callback
     * @param  string  $prefix
     * @param  int|null  $priority
     * @return \Closure
     */
    protected function addViewEvent($view, $callback, $prefix = 'composing: ', $priority = null)
    {
        if ($callback instanceof Closure) {
            $this->addEventListener($prefix.$view, $callback, $priority);

            return $callback;
        } else if (is_string($callback)) {
            return $this->addClassEvent($view, $callback, $prefix, $priority);
        }
    }

    /**
     * Enregistrez un compositeur de vues basé sur une classe.
     *
     * @param  string    $view
     * @param  string    $class
     * @param  string    $prefix
     * @param  int|null  $priority
     * @return \Closure
     */
    protected function addClassEvent($view, $class, $prefix, $priority = null)
    {
        $name = $prefix.$view;

        // Lors de l'enregistrement d'une vue basée sur la classe "composer", nous résoudrons simplement le
        // les classes du conteneur IoC de l'application puis appellent la méthode compose
        // sur l'instance. Cela permet des compositeurs de vues pratiques et testables.
        $callback = $this->buildClassEventCallback($class, $prefix);

        $this->addEventListener($name, $callback, $priority);

        return $callback;
    }

    /**
     * Ajoutez un écouteur au répartiteur d'événements.
     *
     * @param  string   $name
     * @param  \Closure $callback
     * @param  int      $priority
     * @return void
     */
    protected function addEventListener($name, $callback, $priority = null)
    {
        if (is_null($priority)) {
            $this->events->listen($name, $callback);
        } else {
            $this->events->listen($name, $callback, $priority);
        }
    }

    /**
     * Créez une fermeture de rappel de conteneur basée sur une classe.
     *
     * @param  string  $class
     * @param  string  $prefix
     * @return \Closure
     */
    protected function buildClassEventCallback($class, $prefix)
    {
        $container = $this->container;

        list($class, $method) = $this->parseClassEvent($class, $prefix);

        // Une fois que nous avons le nom de la classe et de la méthode, nous pouvons créer la fermeture pour résoudre
        // l'instance hors du conteneur IoC et appelle la méthode dessus avec le
        // étant donné les arguments qui sont transmis à la Closure en tant que données du compositeur.
        return function() use ($class, $method, $container)
        {
            $callable = array($container->make($class), $method);

            return call_user_func_array($callable, func_get_args());
        };
    }

    /**
     * Analyser un nom de compositeur basé sur une classe.
     *
     * @param  string  $class
     * @param  string  $prefix
     * @return array
     */
    protected function parseClassEvent($class, $prefix)
    {
        if (str_contains($class, '@')) {
            return explode('@', $class);
        }

        $method = str_contains($prefix, 'composing') ? 'compose' : 'create';

        return array($class, $method);
    }

    /**
     * Appelez le compositeur pour une vue donnée.
     *
     * @param  \Two\View\View  $view
     * @return void
     */
    public function callComposer(View $view)
    {
        $this->events->dispatch('composing: ' .$view->getName(), array($view));
    }

    /**
     * Appelez le créateur pour une vue donnée.
     *
     * @param  \Two\View\View  $view
     * @return void
     */
    public function callCreator(View $view)
    {
        $this->events->dispatch('creating: ' .$view->getName(), array($view));
    }

    /**
     * Commencez à injecter du contenu dans une section.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    public function startSection($section, $content = '')
    {
        if (! empty($content)) {
            $this->extendSection($section, $content);
        } else if (ob_start()) {
            $this->sectionStack[] = $section;
        }
    }

    /**
     * Injectez du contenu en ligne dans une section.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    public function inject($section, $content)
    {
        return $this->startSection($section, $content);
    }

    /**
     * Arrêtez d'injecter du contenu dans une section et renvoyez son contenu.
     *
     * @return string
     */
    public function yieldSection()
    {
        return $this->yieldContent($this->stopSection());
    }

    /**
     * Arrêtez d’injecter du contenu dans une section.
     *
     * @param  bool  $overwrite
     * @return string
     */
    public function stopSection($overwrite = false)
    {
        $last = array_pop($this->sectionStack);

        if ($overwrite) {
            $this->sections[$last] = ob_get_clean();
        } else {
            $this->extendSection($last, ob_get_clean());
        }

        return $last;
    }

    /**
     * Arrêtez d’injecter du contenu dans une section et ajoutez-le.
     *
     * @return string
     */
    public function appendSection()
    {
        $last = array_pop($this->sectionStack);

        if (isset($this->sections[$last]))  {
            $this->sections[$last] .= ob_get_clean();
        } else {
            $this->sections[$last] = ob_get_clean();
        }

        return $last;
    }

    /**
     * Ajouter du contenu à une section donnée.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    protected function extendSection($section, $content)
    {
        if (isset($this->sections[$section])) {
            $content = str_replace('@parent', $content, $this->sections[$section]);
        }

        $this->sections[$section] = $content;
    }

    /**
     * Obtenez le contenu de la chaîne d'une section.
     *
     * @param  string  $section
     * @param  string  $default
     * @return string
     */
    public function yieldContent($section, $default = '')
    {
        $sectionContent = $default;

        if (isset($this->sections[$section])) {
            $sectionContent = $this->sections[$section];
        }

        return str_replace('@parent', '', $sectionContent);
    }

    /**
     * Videz tout le contenu de la section.
     *
     * @return void
     */
    public function flushSections()
    {
        $this->renderCount = 0;

        $this->sections = array();

        $this->sectionStack = array();
    }

    /**
     * Videz tout le contenu de la section si le rendu est terminé.
     *
     * @return void
     */
    public function flushSectionsIfDoneRendering()
    {
        if ($this->doneRendering()) {
            $this->flushSections();
        }
    }

    /**
     * Incrémentez le compteur de rendu.
     *
     * @return void
     */
    public function incrementRender()
    {
        $this->renderCount++;
    }

    /**
     * Décrémentez le compteur de rendu.
     *
     * @return void
     */
    public function decrementRender()
    {
        $this->renderCount--;
    }

    /**
     * Vérifiez s'il n'y a pas d'opérations de rendu actives.
     *
     * @return bool
     */
    public function doneRendering()
    {
        return ($this->renderCount == 0);
    }

    /**
     * Ajoutez un emplacement au tableau d’emplacements de vue.
     *
     * @param  string  $location
     * @return void
     */
    public function addLocation($location)
    {
        $this->finder->addLocation($location);
    }

    /**
     * Ajoutez un nouvel espace de noms au chargeur.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return void
     */
    public function addNamespace($namespace, $hints)
    {
        $this->finder->addNamespace($namespace, $hints);
    }

    /**
     * Enregistrez une extension de vue valide et son moteur.
     *
     * @param  string   $extension
     * @param  string   $engine
     * @param  Closure  $resolver
     * @return void
     */
    public function addExtension($extension, $engine, $resolver = null)
    {
        $this->finder->addExtension($extension);

        if (isset($resolver)) {
            $this->engines->register($engine, $resolver);
        }

        unset($this->extensions[$extension]);

        $this->extensions = array_merge(array($extension => $engine), $this->extensions);
    }

    /**
     * Configurez les chemins pour le remplacement des vues.
     *
     * @param  string  $namespace
     * @return void
     */
    public function overridesFrom($namespace)
    {
        $this->finder->overridesFrom($namespace);
    }

    /**
     * Obtenez l'extension des liaisons du moteur.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Obtenez l’instance du résolveur de moteur.
     *
     * @return \Two\View\Engines\EngineResolver
     */
    public function getEngineResolver()
    {
        return $this->engines;
    }

    /**
     * Obtenez l’instance View Finder.
     *
     * @return \Two\View\Contracts\ViewFinderInterface
     */
    public function getFinder()
    {
        return $this->finder;
    }

    /**
     * Définissez l’instance du View Finder.
     *
     * @return void
     */
    public function setFinder(ViewFinderInterface $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Obtenez l’instance du répartiteur d’événements.
     *
     * @return \Two\Events\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->events;
    }

    /**
     * Définissez l’instance du répartiteur d’événements.
     *
     * @param  \Two\Events\Dispatcher
     * @return void
     */
    public function setDispatcher(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Obtenez l'instance de conteneur IoC.
     *
     * @return \Two\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Définissez l'instance de conteneur IoC.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Obtenez un élément à partir des données partagées.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function shared($key, $default = null)
    {
        return array_get($this->shared, $key, $default);
    }

    /**
     * Obtenez toutes les données partagées pour l'usine.
     *
     * @return array
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * Obtenez toute la gamme de sections.
     *
     * @return array
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * Obtenez toutes les vues nommées enregistrées dans l’environnement.
     *
     * @return array
     */
    public function getNames()
    {
        return $this->names;
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Config;

use Closure;
use ArrayAccess;

use Two\Container\NamespacedItemResolver;
use Two\Config\Contracts\LoaderInterface;


class Repository extends NamespacedItemResolver implements ArrayAccess
{

    /**
     * L'implémentation du chargeur.
     *
     * @var \Two\Config\Contracts\LoaderInterface
     */
    protected $loader;

    /**
     * L'environnement actuel.
     *
     * @var string
     */
    protected $environment;

    /**
     * Tous les éléments de configuration.
     *
     * @var array
     */
    protected $items = array();

    /**
     * Tous les forfaits enregistrés.
     *
     * @var array
     */
    protected $packages = array();

    /**
     * Les rappels après chargement pour les espaces de noms.
     *
     * @var array
     */
    protected $afterLoad = array();


    /**
     * Créer un nouveau référentiel de configuration.
     *
     * @param  \Two\Config\Contracts\LoaderInterface  $loader
     * @param  string  $environment
     * @return void
     */
    public function __construct(LoaderInterface $loader, $environment)
    {
        $this->loader = $loader;

        $this->environment = $environment;
    }

    /**
     * Déterminez si la valeur de configuration donnée existe.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        $default = microtime(true);

        return $this->get($key, $default) !== $default;
    }

    /**
     * Déterminez si un groupe de configuration existe.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGroup($key)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        return $this->loader->exists($group, $namespace);
    }

    /**
     * Obtenez la valeur de configuration spécifiée.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        // Les éléments de configuration sont en fait indexés par "collection", qui est simplement un
        // combinaison de chaque espace de noms et groupes, ce qui permet une manière unique de
        // identifie les tableaux d'éléments de configuration pour les fichiers particuliers.
        $collection = $this->getCollection($group, $namespace);

        $this->load($group, $namespace, $collection);

        return array_get($this->items[$collection], $item, $default);
    }

    /**
     * Définir une valeur de configuration donnée.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function set($key, $value)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        $collection = $this->getCollection($group, $namespace);

        // Nous devrons continuer et charger paresseux chaque groupe de configuration même lorsque
        // nous définissons simplement un élément de configuration afin que l'élément défini ne
        // est écrasé si un élément différent du groupe est demandé ultérieurement.
        $this->load($group, $namespace, $collection);

        if (is_null($item)) {
            $this->items[$collection] = $value;
        } else {
            array_set($this->items[$collection], $item, $value);
        }
    }

    /**
     * Charger le groupe de configuration pour la clé.
     *
     * @param  string  $group
     * @param  string  $namespace
     * @param  string  $collection
     * @return void
     */
    protected function load($group, $namespace, $collection)
    {
        $env = $this->environment;

        // Si nous avons déjà chargé cette collection, nous allons juste renflouer puisque nous 
        // le faisons ne veut pas le charger à nouveau. Une fois les éléments chargés une première 
        // fois, ils seront reste conservé en mémoire dans cette classe et n'est plus chargé à 
        // partir du disque.
        if (isset($this->items[$collection])) {
            return;
        }

        $items = $this->loader->load($env, $group, $namespace);

        // Si nous avons déjà chargé cette collection, nous allons juste renflouer puisque nous 
        // le faisons ne veut pas le charger à nouveau. Une fois les éléments chargés une première
        // fois, ils seront reste conservé en mémoire dans cette classe et n'est plus chargé à 
        // partir du disque.
        if (isset($this->afterLoad[$namespace])) {
            $items = $this->callAfterLoad($namespace, $group, $items);
        }

        $this->items[$collection] = $items;
    }

    /**
     * Appelez le rappel après chargement pour un espace de noms.
     *
     * @param  string  $namespace
     * @param  string  $group
     * @param  array   $items
     * @return array
     */
    protected function callAfterLoad($namespace, $group, $items)
    {
        $callback = $this->afterLoad[$namespace];

        return call_user_func($callback, $this, $group, $items);
    }

    /**
     * Analyser un tableau de segments d'espace de noms.
     *
     * @param  string  $key
     * @return array
     */
    protected function parseNamespacedSegments($key)
    {
        list($namespace, $item) = explode('::', $key);

        // Si l'espace de noms est enregistré en tant que package, nous assumerons simplement le groupe
        // est égal à l'espace de noms puisque tous les packages se cascadent de cette façon en ayant
        // un seul fichier par paquet, sinon nous les parserons comme d'habitude.
        if (array_key_exists($namespace, $this->packages)) {
            return $this->parsePackageSegments($key, $namespace, $item);
        }

        return parent::parseNamespacedSegments($key);
    }

    /**
     * Analyser les segments d'un espace de noms de package.
     *
     * @param  string  $key
     * @param  string  $namespace
     * @param  string  $item
     * @return array
     */
    protected function parsePackageSegments($key, $namespace, $item)
    {
        $itemSegments = explode('.', $item);

        // Si le fichier de configuration n'existe pas pour le groupe de packages donné, nous pouvons
        // supposons que nous devrions implicitement utiliser le fichier de configuration 
        // correspondant au nom de l'espace de noms. Généralement, les packages doivent utiliser 
        // un type ou un autre.
        if (! $this->loader->exists($itemSegments[0], $namespace)) {
            return array($namespace, 'config', $item);
        }

        return parent::parseNamespacedSegments($key);
    }

    /**
     * Enregistrez un package pour la configuration en cascade.
     *
     * @param  string  $package
     * @param  string  $hint
     * @param  string  $namespace
     * @return void
     */
    public function package($package, $hint, $namespace = null)
    {
        $namespace = $this->getPackageNamespace($package, $namespace);

        $this->packages[$namespace] = $package;

        // D'abord, nous allons simplement enregistrer l'espace de noms auprès du référentiel afin qu'il
        // peut être chargé. Une fois que nous aurons fait cela, nous enregistrerons un espace de 
        // noms après rappel afin que nous puissions cascader une configuration de package 
        // d'application.
        $this->addNamespace($namespace, $hint);

        $this->afterLoading($namespace, function($me, $group, $items) use ($package)
        {
            $env = $me->getEnvironment();

            $loader = $me->getLoader();

            return $loader->cascadePackage($env, $package, $group, $items);
        });
    }

    /**
     * Obtenez l'espace de noms de configuration pour un package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @return string
     */
    protected function getPackageNamespace($package, $namespace)
    {
        if (is_null($namespace)) {
            list($vendor, $namespace) = explode('/', $package);
        }

        return $namespace;
    }

    /**
     * Enregistrer un rappel après chargement pour un espace de noms donné.
     *
     * @param  string   $namespace
     * @param  \Closure  $callback
     * @return void
     */
    public function afterLoading($namespace, Closure $callback)
    {
        $this->afterLoad[$namespace] = $callback;
    }

    /**
     * Obtenez l'identifiant de la collection.
     *
     * @param  string  $group
     * @param  string  $namespace
     * @return string
     */
    protected function getCollection($group, $namespace = null)
    {
        $namespace = $namespace ?: '*';

        return $namespace .'::' .$group;
    }

    /**
     * Ajouter un nouvel espace de noms au chargeur.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    /**
     * Renvoie tous les espaces de noms enregistrés avec la configuration chargeur.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->loader->getNamespaces();
    }

    /**
     * Obtenez l'implémentation du chargeur.
     *
     * @return \Two\Config\Contracts\LoaderInterface
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Définir l'implémentation du chargeur.
     *
     * @param  \Two\Config\Contracts\LoaderInterface  $loader
     * @return void
     */
    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Obtenez l'environnement de configuration actuel.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Obtenez le tableau de rappel après chargement.
     *
     * @return array
     */
    public function getAfterLoadCallbacks()
    {
        return $this->afterLoad;
    }

    /**
     * Obtenez les packages de configuration actuels.
     *
     * @return string
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * Obtenez tous les éléments de configuration.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Déterminez si l'option de configuration donnée existe.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * Obtenez une option de configuration.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->get($key);
    }

    /**
     * Définissez une option de configuration.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Annuler une option de configuration.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        $this->set($key, null);
    }

}

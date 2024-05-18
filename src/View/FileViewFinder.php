<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Two\Support\Str;

use InvalidArgumentException;
use Two\Filesystem\Filesystem;
use Two\View\Contracts\ViewFinderInterface;


class FileViewFinder implements ViewFinderInterface
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le tableau des chemins d’affichage actifs.
     *
     * @var array
     */
    protected $paths;

    /**
     * L'ensemble des vues qui ont été localisées.
     *
     * @var array
     */
    protected $views = array();

    /**
     * L'espace de noms pour les indications de chemin de fichier.
     *
     * @var array
     */
    protected $hints = array();

    /**
     * Enregistrez une extension de vue avec le Finder.
     *
     * @var array
     */
    protected $extensions = array('tpl', 'php', 'css', 'js', 'md');

    /**
     * Valeur du délimiteur de chemin d’indice.
     *
     * @var string
     */
    const HINT_PATH_DELIMITER = '::';


    /**
     * Créez une nouvelle instance de chargeur de vue de fichier.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  array  $extensions
     * @param  array  $paths
     * @return void
     */
    public function __construct(Filesystem $files, array $paths, array $extensions = null)
    {
        $this->files = $files;

         $this->paths = $paths;

        if (isset($extensions)) {
            $this->extensions = $extensions;
        }
    }

    /**
     * Obtenez l’emplacement complet de la vue.
     *
     * @param  string  $name
     * @return string
     */
    public function find($name)
    {
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        if ($this->hasHintInformation($name = trim($name))) {
            return $this->views[$name] = $this->findNamedPathView($name);
        }

        return $this->views[$name] = $this->findInPaths($name, $this->paths);
    }

    /**
     * Obtenez le chemin d'accès à un modèle avec un chemin nommé.
     *
     * @param  string  $name
     * @return string
     */
    protected function findNamedPathView($name)
    {
        list($namespace, $view) = $this->getNamespaceSegments($name);

        $paths = $this->hints[$namespace];

        if (Str::endsWith($path = head($this->paths), DS .'Overrides')) {
            $path = $path .DS .'Packages' .DS .$namespace;

            if (! in_array($path, $paths) && $this->files->isDirectory($path)) {
                array_unshift($paths, $path);
            }
        }

        return $this->findInPaths($view, $paths);
    }

    /**
     * Obtenez les segments d'un modèle avec un chemin nommé.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getNamespaceSegments($name)
    {
        $segments = explode(static::HINT_PATH_DELIMITER, $name);

        if (count($segments) != 2) {
            throw new InvalidArgumentException("View [$name] has an invalid name.");
        }

        if ( ! isset($this->hints[$segments[0]])) {
            throw new InvalidArgumentException("No hint path defined for [{$segments[0]}].");
        }

        return $segments;
    }

    /**
     * Recherchez la vue donnée dans la liste des chemins.
     *
     * @param  string  $name
     * @param  array   $paths
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function findInPaths($name, array $paths)
    {
        foreach ($paths as $path) {
            foreach ($this->getPossibleViewFiles($name) as $fileName) {
                $viewPath = $path .DS .$fileName;

                if ($this->files->exists($viewPath)) {
                    return $viewPath;
                }
            }
        }

        throw new InvalidArgumentException("View [$name] not found.");
    }

    /**
     * Obtenez un tableau de fichiers de visualisation possibles.
     *
     * @param  string  $name
     * @return array
     */
    protected function getPossibleViewFiles($name)
    {
        return array_map(function($extension) use ($name)
        {
            return str_replace('.', '/', $name) .'.' .$extension;

        }, $this->extensions);
    }

    /**
     * Ajoutez un emplacement au chercheur.
     *
     * @param  string  $location
     * @return void
     */
    public function addLocation($location)
    {
        $this->paths[] = $location;
    }

    /**
     * Ajoutez un emplacement au chercheur.
     *
     * @param  string  $location
     * @return void
     */
    public function prependLocation($location)
    {
        array_unshift($this->paths, $location);
    }

    /**
     * Ajoutez un indice d'espace de noms au chercheur.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return void
     */
    public function addNamespace($namespace, $hints)
    {
        $hints = (array) $hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($this->hints[$namespace], $hints);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Ajoutez un indice d'espace de noms au chercheur.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return void
     */
    public function prependNamespace($namespace, $hints)
    {
        $hints = (array) $hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($hints, $this->hints[$namespace]);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Enregistrez une extension avec le viseur.
     *
     * @param  string  $extension
     * @return void
     */
    public function addExtension($extension)
    {
        if (($index = array_search($extension, $this->extensions)) !== false) {
            unset($this->extensions[$index]);
        }

        array_unshift($this->extensions, $extension);
    }

    /**
     * Indique si la vue spécifie ou non des informations d'indice.
     *
     * @param  string  $name
     * @return boolean
     */
    public function hasHintInformation($name)
    {
        return strpos($name, static::HINT_PATH_DELIMITER) > 0;
    }

    /**
     * Ajoutez un chemin spécifié par son espace de noms.
     *
     * @param  string  $namespace
     * @return void
     */
    public function overridesFrom($namespace)
    {
        if (! isset($this->hints[$namespace])) {
            return;
        }

        $paths = $this->hints[$namespace];

        // Le dossier Views Override doit être situé dans le même répertoire que celui de Views.
        // Par exemple : <BASEPATH>/themes/Bootstrap/Views -> <BASEPATH>/themes/Bootstrap/Override

        $path = dirname(head($paths)) .DS .'Overrides';

        if (! in_array($path, $this->paths) && $this->files->isDirectory($path)) {
            // Si un autre chemin de remplacement de vues a déjà été ajouté, nous le supprimerons.

            if (Str::endsWith(head($this->paths), DS .'Overrides')) {
                array_shift($this->paths);
            }

            array_unshift($this->paths, $path);
        }
    }

    /**
     * Obtenez l'instance du système de fichiers.
     *
     * @return \Two\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Obtenez les chemins de vue actifs.
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Obtenez l'espace de noms pour les indications de chemin de fichier.
     *
     * @return array
     */
    public function getHints()
    {
        return $this->hints;
    }

    /**
     * Obtenez des extensions enregistrées.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

}

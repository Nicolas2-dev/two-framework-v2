<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Config;

use Two\Filesystem\Filesystem;
use Two\Config\Contracts\LoaderInterface;


class FileLoader implements LoaderInterface
{
    
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le chemin de configuration par défaut.
     *
     * @var string
     */
    protected $defaultPath;

    /**
     * Tous les indicateurs de chemin nommés.
     *
     * @var array
     */
    protected $hints = array();

    /**
     * Un cache indiquant si les espaces de noms et les groupes existent.
     *
     * @var array
     */
    protected $exists = array();


    /**
     * Créez un nouveau chargeur de configuration de fichier.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $defaultPath
     * @return void
     */
    public function __construct(Filesystem $files, $defaultPath)
    {
        $this->files = $files;

        $this->defaultPath = $defaultPath;
    }

    /**
     * Chargez le groupe de configuration donné.
     *
     * @param  string  $environment
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($environment, $group, $namespace = null)
    {
        $items = array();

        // Nous allons d'abord obtenir le chemin de configuration racine pour l'environnement qui est
        // où tous les fichiers de configuration vivent pour cet espace de noms, ainsi
        // comme tous les dossiers d'environnement avec leurs éléments de configuration spécifiques.
        $path = $this->getPath($namespace);

        if (is_null($path)) {
            return $items;
        }

        // Nous allons d'abord obtenir le fichier de configuration principal pour les groupes. 
        // Une fois que nous avons  que nous pouvons vérifier tous les fichiers spécifiques à 
        // l'environnement, qui obtiendront fusionné au-dessus des tableaux principaux pour créer 
        // une cascade d'environnements.
        $group = ucfirst($group);

        $file = "{$path}/{$group}.php";

        if ($this->files->exists($file)) {
            $items = $this->getRequire($file);
        }

        // Enfin, nous sommes prêts à vérifier la configuration spécifique à l'environnement
        // fichier qui sera fusionné au-dessus des tableaux principaux afin qu'ils obtiennent
        // priorité sur eux si nous sommes actuellement dans une configuration d'environnements.
        $environment = ucfirst($environment);

        $file = "{$path}/{$environment}/{$group}.php";

        if ($this->files->exists($file)) {
            $items = $this->mergeEnvironment($items, $file);
        }

        return $items;
    }

    /**
     * Fusionne les éléments du fichier donné dans les éléments.
     *
     * @param  array   $items
     * @param  string  $file
     * @return array
     */
    protected function mergeEnvironment(array $items, $file)
    {
        return array_replace_recursive($items, $this->getRequire($file));
    }

    /**
     * Déterminez si le groupe donné existe.
     *
     * @param  string  $group
     * @param  string  $namespace
     * @return bool
     */
    public function exists($group, $namespace = null)
    {
        $key = $group .$namespace;

        //
        $group = ucfirst($group);

        // Nous allons d'abord vérifier si nous avons déterminé si cet espace de noms et
        // la combinaison de groupe a déjà été vérifiée. S'ils l'ont fait, nous le ferons
        // renvoie simplement le résultat mis en cache afin que nous n'ayons pas à frapper le disque.
        if (isset($this->exists[$key])) {
            return $this->exists[$key];
        }

        $path = $this->getPath($namespace);

        // Pour vérifier si un groupe existe, nous obtiendrons simplement le chemin basé sur le
        // espace de noms, puis vérifiez si ces fichiers existent dans cet espace
        // espace de noms. False est renvoyé si aucun chemin n'existe pour un espace de noms.
        if (is_null($path)) {
            return $this->exists[$key] = false;
        }

        $file = "{$path}/{$group}.php";

        // Enfin, nous pouvons simplement vérifier si ce fichier existe. Nous mettrons également 
        // en cache la valeur dans un tableau afin que nous n'ayons pas à passer par ce processus 
        // à nouveau lors des vérifications ultérieures de l'existence du fichier de configuration.
        $exists = $this->files->exists($file);

        return $this->exists[$key] = $exists;
    }

    /**
     * Appliquez toutes les cascades à un tableau d'options de package.
     *
     * @param  string  $env
     * @param  string  $package
     * @param  string  $group
     * @param  array   $items
     * @return array
     */
    public function cascadePackage($env, $package, $group, $items)
    {
        $group = ucfirst($group);

        // Nous allons d'abord chercher un fichier de configuration dans la configuration des packages
        // dossier. S'il existe, nous le chargerons et le fusionnerons avec ces originaux
        // options pour pouvoir "cascader" facilement les configurations d'un paquet.
        $file = str_replace('/', DS, "Packages/{$package}/{$group}.php");

        if ($this->files->exists($path = $this->defaultPath .DS .$file)) {
            $items = array_merge(
                $items, $this->getRequire($path)
            );
        }

        // Une fois que nous avons fusionné la configuration régulière du package, nous devons
        // rechercher un fichier de configuration spécifique à l'environnement. S'il existe, 
        // nous obtiendrons le contenu et les fusionner au-dessus de ce tableau 
        // d'options que nous avons.
        $path = $this->getPackagePath($env, $package, $group);

        if ($this->files->exists($path)) {
            $items = array_merge(
                $items, $this->getRequire($path)
            );
        }

        return $items;
    }

    /**
     * Obtenez le chemin du package pour un environnement et un groupe.
     *
     * @param  string  $env
     * @param  string  $package
     * @param  string  $group
     * @return string
     */
    protected function getPackagePath($env, $package, $group)
    {
        $file = str_replace('/', DS, "Packages/{$package}/{$env}/{$group}.php");

        return $this->defaultPath .DS .$file;
    }

    /**
     * Obtenez le chemin de configuration d'un espace de noms.
     *
     * @param  string  $namespace
     * @return string
     */
    protected function getPath($namespace)
    {
        if (is_null($namespace)) {
            return $this->defaultPath;
        } elseif (isset($this->hints[$namespace])) {
            return $this->hints[$namespace];
        }
    }

    /**
     * Ajoutez un nouvel espace de noms au chargeur.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Renvoie tous les espaces de noms enregistrés avec la configuration chargeur.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->hints;
    }

    /**
     * Obtenir le contenu d'un fichier en l'exigeant.
     *
     * @param  string  $path
     * @return mixed
     */
    protected function getRequire($path)
    {
        return $this->files->getRequire($path);
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

}

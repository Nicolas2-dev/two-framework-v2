<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Publishers;

use InvalidArgumentException;

use Two\Filesystem\Filesystem;
use Two\Config\Repository as Config;


class ConfigPublisher
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * L'instance du référentiel de configuration.
     *
     * @var \Two\Config\Repository
     */
    protected $config;

    /**
     * La destination des fichiers de configuration.
     *
     * @var string
     */
    protected $publishPath;


    /**
     * Créez une nouvelle instance d'éditeur de configuration.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $publishPath
     * @return void
     */
    public function __construct(Filesystem $files, Config $config, $publishPath)
    {
        $this->files = $files;

        $this->config = $config;

        $this->publishPath = $publishPath;
    }

    /**
     * Publier les fichiers de configuration à partir d'un chemin donné.
     *
     * @param  string  $package
     * @param  string  $source
     * @return bool
     */
    public function publish($package, $source)
    {
        $destination = $this->getDestinationPath($package);

        $this->makeDestination($destination);

        return $this->files->copyDirectory($source, $destination);
    }

    /**
     * Publiez les fichiers de configuration d'un package.
     *
     * @param  string  $package
     * @param  string  $packagePath
     * @return bool
     */
    public function publishPackage($package)
    {
        $source = $this->getSource($package);

        return $this->publish($package, $source);
    }

    /**
     * Obtenez le répertoire de configuration source à publier.
     *
     * @param  string  $package
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function getSource($package)
    {
        $namespaces = $this->config->getNamespaces();

        $source = isset($namespaces[$package]) ? $namespaces[$package] : null;

        if (is_null($source) || ! $this->files->isDirectory($source)) {
            throw new InvalidArgumentException("Configuration not found.");
        }

        return $source;
    }

    /**
     * Créez le répertoire de destination s'il n'existe pas.
     *
     * @param  string  $destination
     * @return void
     */
    protected function makeDestination($destination)
    {
        if ( ! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0777, true);
        }
    }

    /**
     * Déterminez si un package donné a déjà été publié.
     *
     * @param  string  $package
     * @return bool
     */
    public function alreadyPublished($package)
    {
        $path = $this->getDestinationPath($package);

        return $this->files->isDirectory($path);
    }

    /**
     * Obtenez le chemin de destination cible pour les fichiers de configuration.
     *
     * @param  string  $package
     * @return string
     */
    public function getDestinationPath($package)
    {
        $packages = $this->config->getPackages();

        $namespace = isset($packages[$package]) ? $packages[$package] : null;

        if (is_null($namespace)) {
            throw new InvalidArgumentException("Configuration not found.");
        }

        return $this->publishPath .str_replace('/', DS, "/Packages/{$namespace}");
    }

}

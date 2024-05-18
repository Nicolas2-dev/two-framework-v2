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


class ViewPublisher
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * La destination des fichiers de vue.
     *
     * @var string
     */
    protected $publishPath;

    /**
     * Le chemin d'accès aux packages de l'application.
     *
     * @var string
     */
    protected $packagePath;


    /**
     * Créez une nouvelle instance d'éditeur de vues.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $publishPath
     * @return void
     */
    public function __construct(Filesystem $files, $publishPath)
    {
        $this->files = $files;

        $this->publishPath = $publishPath;
    }

    /**
     * Publier les fichiers de vue à partir d'un chemin donné.
     *
     * @param  string  $package
     * @param  string  $source
     * @return void
     */
    public function publish($package, $source)
    {
        $destination = $this->publishPath .str_replace('/', DS, "/Packages/{$package}");

        $this->makeDestination($destination);

        return $this->files->copyDirectory($source, $destination);
    }

    /**
     * Publiez les fichiers de vue pour un package.
     *
     * @param  string  $package
     * @param  string  $packagePath
     * @return void
     */
    public function publishPackage($package, $packagePath = null)
    {
        $source = $this->getSource($package, $packagePath ?: $this->packagePath);

        return $this->publish($package, $source);
    }

    /**
     * Obtenez le répertoire des vues sources à publier.
     *
     * @param  string  $package
     * @param  string  $packagePath
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function getSource($package, $packagePath)
    {
        $source = $packagePath .str_replace('/', DS, "/{$package}/src/Views");

        if (! $this->files->isDirectory($source)) {
            throw new InvalidArgumentException("Views not found.");
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
        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0777, true);
        }
    }

    /**
     * Définissez le chemin du package par défaut.
     *
     * @param  string  $packagePath
     * @return void
     */
    public function setPackagePath($packagePath)
    {
        $this->packagePath = $packagePath;
    }

}

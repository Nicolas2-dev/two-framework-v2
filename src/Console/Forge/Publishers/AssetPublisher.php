<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Publishers;

use RuntimeException;

use Two\Filesystem\Filesystem;


class AssetPublisher
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Chemin vers lequel les actifs doivent être publiés.
     *
     * @var string
     */
    protected $publishPath;

    /**
     * Le chemin où se trouvent les packages.
     *
     * @var string
     */
    protected $packagePath;


    /**
     * Créez une nouvelle instance d'éditeur d'actifs.
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
     * Copiez toutes les ressources d'un chemin donné vers le chemin de publication.
     *
     * @param  string  $name
     * @param  string  $source
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function publish($name, $source)
    {
        $package = str_replace('_', '-', $name);

        $destination = $this->publishPath .str_replace('/', DS, "/packages/{$package}");

        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0777, true);
        }

        $success = $this->files->copyDirectory($source, $destination);

        if (! $success) {
            throw new RuntimeException("Unable to publish assets.");
        }

        return $success;
    }

    /**
     * Publiez les ressources d'un package donné dans le chemin de publication.
     *
     * @param  string  $package
     * @param  string  $packagePath
     * @return bool
     */
    public function publishPackage($package, $packagePath = null)
    {
        $source = $packagePath ?: $this->packagePath;

        return $this->publish($package, $source);
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

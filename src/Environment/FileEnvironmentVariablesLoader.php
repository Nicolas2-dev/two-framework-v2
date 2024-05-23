<?php

namespace Two\Environment;


use Two\Filesystem\Filesystem;
use Two\Environment\Contracts\EnvironmentVariablesLoaderInterface;


class FileEnvironmentVariablesLoader implements EnvironmentVariablesLoaderInterface
{

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le chemin d'accès aux fichiers de configuration.
     *
     * @var string
     */
    protected $path;


    /**
     * Créez une nouvelle instance de chargeur d'environnement de fichier.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files, $path = null)
    {
        $this->files = $files;

        $this->path = $path ?: base_path();
    }

    /**
     * Chargez les variables d'environnement pour l'environnement donné.
     *
     * @param  string  $environment
     * @return array
     */
    public function load($environment = null)
    {
        if ($environment == 'production') $environment = null;

        if (! $this->files->exists($path = $this->getFile($environment))) {
            return array();
        } else {
            return $this->files->getRequire($path);
        }
    }

    /**
     * Obtenez le fichier pour l'environnement donné.
     *
     * @param  string  $environment
     * @return string
     */
    protected function getFile($environment)
    {
        if ($environment) {
            return $this->path.'/.env.'.$environment.'.php';
        } else {
            return $this->path.'/.env.php';
        }
    }

}

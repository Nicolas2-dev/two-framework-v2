<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application;

use Two\Filesystem\Filesystem;
use Two\Support\ProcessUtils;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;


class Composer
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le chemin de travail à partir duquel se régénérer.
     *
     * @var string
     */
    protected $workingPath;


    /**
     * Créez une nouvelle instance du gestionnaire Composer.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $workingPath
     * @return void
     */
    public function __construct(Filesystem $files, $workingPath = null)
    {
        $this->files = $files;

        $this->workingPath = $workingPath;
    }

    /**
     * Régénérez les fichiers du chargeur automatique Composer.
     *
     * @param  string  $extra
     * @return void
     */
    public function dumpAutoloads($extra = '')
    {
        $extra = $extra ? (array) $extra : [];

        $command = $this->findComposer() .' dump-autoload';

        if (! empty($extra)) {
            $command = trim($command .' ' .$extra);
        }

        return $this->getProcess($command)->run();
    }

    /**
     * Régénérez les fichiers optimisés du chargeur automatique Composer.
     *
     * @return void
     */
    public function dumpOptimized()
    {
        $this->dumpAutoloads('--optimize');
    }

    /**
     * Obtenez la commande composer pour l’environnement.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPhar = $this->workingPath .DS .'composer.phar';

        if ($this->files->exists($composerPhar)) {
            $executable = with(new PhpExecutableFinder)->find(false);

            return ProcessUtils::escapeArgument($executable) .' ' .$composerPhar;
        }

        return 'composer';
    }

    /**
     * Obtenez une nouvelle instance de processus Symfony.
     *
     * @return \Symfony\Component\Process\Process
     */
    protected function getProcess($command)
    {
        $process = new Process($command, $this->workingPath);

        return $process->setTimeout(null);
    }

    /**
     * Définissez le chemin de travail utilisé par la classe.
     *
     * @param  string  $path
     * @return $this
     */
    public function setWorkingPath($path)
    {
        $this->workingPath = realpath($path);

        return $this;
    }

}

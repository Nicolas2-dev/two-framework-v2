<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;

use RuntimeException;


class ViewClearCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'view:clear';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Clear all compiled View files";

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Créez une nouvelle instance de View Clear Command.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $cachePath
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        //
        $this->files = $files;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $path = $this->container['config']->get('view.compiled');

        if (! $this->files->exists($path)) {
            throw new RuntimeException('View path not found.');
        }

        foreach ($this->files->glob("{$path}/*.php") as $view) {
            $this->files->delete($view);
        }

        $this->info('Compiled views cleared!');
    }
}

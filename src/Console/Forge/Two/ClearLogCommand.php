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


class ClearLogCommand extends Command
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'log:clear';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Clear log files';


    /**
     * Créez une nouvelle commande de générateur de clés.
     *
     * @param \Two\Filesystem\Filesystem $files
     * @author Sang Nguyen
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Exécutez la commande de la console.
     * 
     * @author Sang Nguyen
     */
    public function handle()
    {
        $pattern = storage_path('logs') .DS .'*.log';

        $files = $this->files->glob($pattern);

        foreach ($files as $file) {
            $this->files->delete($file);
        }

        $this->info('Log files cleared successfully!');
    }
}

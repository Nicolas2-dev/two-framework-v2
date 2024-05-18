<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;


class UpCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'maintenance:up';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Bring the Application out of Maintenance Mode";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $basePath = $this->container['path.storage'];

        @unlink($basePath .DS .'down');

        $this->info('Application is now live.');
    }

}

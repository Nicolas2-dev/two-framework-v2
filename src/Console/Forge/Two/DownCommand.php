<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;


class DownCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'maintenance:down';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Put the Application into Maintenance Mode";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $basePath = $this->container['path.storage'];

        touch($basePath .DS .'down');

        $this->comment('Application is now in maintenance mode.');
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;


class ClearCompiledCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'clear-compiled';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Remove the compiled class file";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $config = $this->container['config'];

        if (file_exists($path = $config->get('app.manifest') .DS .'services.php')) {
            @unlink($path);
        }

        //$this->info('Compiled class file removed successfully!');
    }

}

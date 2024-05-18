<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;


class EnvironmentCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'environement:env';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Display the current Framework Environment";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->line('<info>Current Application Environment:</info> <comment>'.$this->container['env'].'</comment>');
    }

}

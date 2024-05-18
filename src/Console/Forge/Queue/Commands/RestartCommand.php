<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Console\Commands\Command;


class RestartCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:restart';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Restart queue worker daemons after their current job";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->container['cache']->forever('Two:queue:restart', time());

        $this->info('Broadcasting queue restart signal.');
    }

}

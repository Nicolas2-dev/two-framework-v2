<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Console\Commands\Command;


class FlushFailedCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:flush';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Flush all of the failed queue jobs';

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->container['queue.failer']->flush();

        $this->info('All failed jobs deleted successfully!');
    }

}

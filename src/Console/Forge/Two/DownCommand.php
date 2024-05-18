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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'maintenance:down';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Put the Application into Maintenance Mode";

    /**
     * Execute the console command.
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

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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'maintenance:up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Bring the Application out of Maintenance Mode";

    /**
     * Execute the console command.
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

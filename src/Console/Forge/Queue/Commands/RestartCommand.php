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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Restart queue worker daemons after their current job";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->container['cache']->forever('Two:queue:restart', time());

        $this->info('Broadcasting queue restart signal.');
    }

}

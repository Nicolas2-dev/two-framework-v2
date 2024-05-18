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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'environement:env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Display the current Framework Environment";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->line('<info>Current Application Environment:</info> <comment>'.$this->container['env'].'</comment>');
    }

}

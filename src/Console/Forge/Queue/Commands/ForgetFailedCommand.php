<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputArgument;


class ForgetFailedCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:forget';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed queue job';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->container['queue.failer']->forget($this->argument('id'))) {
            $this->info('Failed job deleted successfully!');
        } else {
            $this->error('No failed job matches the given ID.');
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('id', InputArgument::REQUIRED, 'The ID of the failed job'),
        );
    }

}

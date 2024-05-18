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
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:forget';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Delete a failed queue job';

    /**
     * Exécutez la commande de la console.
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
     * Obtenez les arguments de la commande de la console.
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

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Console\Commands\Command;


class ListFailedCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:failed';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'List all of the failed queue jobs';

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $rows = array();

        foreach ($this->container['queue.failer']->all() as $failed)
        {
            $rows[] = $this->parseFailedJob((array) $failed);
        }

        if (count($rows) == 0)
        {
            return $this->info('No failed jobs!');
        }

        $this->table(array('ID', 'Connection', 'Queue', 'Class', 'Failed At'), $rows);
    }

    /**
     * Analysez la ligne du travail ayant échoué.
     *
     * @param  array  $failed
     * @return array
     */
    protected function parseFailedJob(array $failed)
    {
        $row = array_values(array_except($failed, array('payload')));

        array_splice($row, 3, 0, array_get(json_decode($failed['payload'], true), 'job'));

        return $row;
    }

}

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


class RetryCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:retry';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Retry a failed queue job';

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $failed = $this->container['queue.failer']->find($this->argument('id'));

        if ( ! is_null($failed))
        {
            $failed->payload = $this->resetAttempts($failed->payload);

            $this->container['queue']->connection($failed->connection)->pushRaw($failed->payload, $failed->queue);

            $this->container['queue.failer']->forget($failed->id);

            $this->info('The failed job has been pushed back onto the queue!');
        }
        else
        {
            $this->error('No failed job matches the given ID.');
        }
    }

    /**
     * Réinitialisez les tentatives de charge utile.
     *
     * @param  string  $payload
     * @return string
     */
    protected function resetAttempts($payload)
    {
        $payload = json_decode($payload, true);

        if (isset($payload['attempts'])) {
            $payload['attempts'] = 0;
        }

        return json_encode($payload);
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

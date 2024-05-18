<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Queue\Listener;
use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ListenCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:listen';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Listen to a given queue';

    /**
     * Instance d'écoute de file d'attente.
     *
     * @var \Two\Queue\Listener
     */
    protected $listener;

    /**
     * Créez une nouvelle commande d'écoute de file d'attente.
     *
     * @param  \Two\Queue\Listener  $listener
     * @return void
     */
    public function __construct(Listener $listener)
    {
        parent::__construct();

        $this->listener = $listener;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->setListenerOptions();

        $delay = $this->input->getOption('delay');

        // La limite de mémoire est la quantité de mémoire que nous autoriserons le script à occuper
        // avant de le tuer et de laisser un gestionnaire de processus le redémarrer pour nous, ce qui
        // est de nous protéger contre les éventuelles fuites de mémoire qui seront dans les scripts.
        $memory = $this->input->getOption('memory');

        $connection = $this->input->getArgument('connection');

        $timeout = $this->input->getOption('timeout');

        // Nous devons obtenir la bonne file d'attente pour la connexion qui est définie dans la file d'attente
        // fichier de configuration de l'application. Nous le tirerons en fonction de l'ensemble
        // connexion en cours d'exécution pour l'opération de file d'attente en cours d'exécution.
        $queue = $this->getQueue($connection);

        $this->listener->listen(
            $connection, $queue, $delay, $memory, $timeout
        );
    }

    /**
     * Obtenez le nom de la connexion de file d’attente sur laquelle écouter.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        if (is_null($connection))
        {
            $connection = $this->container['config']['queue.default'];
        }

        $queue = $this->container['config']->get("queue.connections.{$connection}.queue", 'default');

        return $this->input->getOption('queue') ?: $queue;
    }

    /**
     * Définissez les options sur l'écouteur de file d'attente.
     *
     * @return void
     */
    protected function setListenerOptions()
    {
        $this->listener->setEnvironment($this->container->environment());

        $this->listener->setSleep($this->option('sleep'));

        $this->listener->setMaxTries($this->option('tries'));

        $this->listener->setOutputHandler(function($type, $line)
        {
            $this->output->write($line);
        });
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('connection', InputArgument::OPTIONAL, 'The name of connection'),
        );
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('queue',   null, InputOption::VALUE_OPTIONAL, 'The queue to listen on', null),
            array('delay',   null, InputOption::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 0),
            array('memory',  null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128),
            array('timeout', null, InputOption::VALUE_OPTIONAL, 'Seconds a job may run before timing out', 60),
            array('sleep',   null, InputOption::VALUE_OPTIONAL, 'Seconds to wait before checking queue for jobs', 3),
            array('tries',   null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 0),
        );
    }

}

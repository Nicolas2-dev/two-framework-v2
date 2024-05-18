<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Queue\Worker;
use Two\Console\Commands\Command;

use Carbon\Carbon;
use Two\Queue\Job;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class WorkCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:work';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Process the next job on a queue';

    /**
     * Instance de travail de file d’attente.
     *
     * @var \Two\Queue\Worker
     */
    protected $worker;

    /**
     * Créez une nouvelle commande d'écoute de file d'attente.
     *
     * @param  \Two\Queue\Worker  $worker
     * @return void
     */
    public function __construct(Worker $worker)
    {
        parent::__construct();

        $this->worker = $worker;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $daemon = $this->option('daemon');

        if ($this->downForMaintenance() && ! $daemon) {
            return;
        }

        // Nous écouterons les événements traités et ayant échoué afin de pouvoir écrire des informations
        // à la console au fur et à mesure que les tâches sont traitées, ce qui permettra au développeur de surveiller
        // quels travaux arrivent dans une file d'attente et soyez informé de sa progression.

        $this->listenForEvents();

        // Obtenez l'instance du référentiel de configuration.
        $config = $this->container['config'];

        $connection = $this->argument('connection') ?: $config->get('queue.default');

        $delay = $this->option('delay');

        // La limite de mémoire est la quantité de mémoire que nous autoriserons le script à occuper
        // avant de le tuer et de laisser un gestionnaire de processus le redémarrer pour nous, ce qui
        // est de nous protéger contre les éventuelles fuites de mémoire qui seront dans les scripts.

        $memory = $this->option('memory');

        // Nous devons obtenir la bonne file d'attente pour la connexion qui est définie dans la file d'attente
        // fichier de configuration de l'application. Nous le tirerons en fonction de l'ensemble
        // connexion en cours d'exécution pour l'opération de file d'attente en cours d'exécution.

        $queue = $this->option('queue') ?: $config->get(
            "queue.connections.{$connection}.queue", 'default'
        );

        $this->runWorker($connection, $queue, $delay, $memory, $daemon);
    }

    /**
     * Écoutez les événements de file d'attente afin de mettre à jour la sortie de la console.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $events = $this->container['events'];

        $events->listen('Two.queue.processing', function ($connection, $job)
        {
            $this->writeOutput($job, 'starting');
        });

        $events->listen('Two.queue.processed', function ($connection, $job)
        {
            $this->writeOutput($job, 'success');
        });

        $events->listen('Two.queue.failed', function ($connection, $job)
        {
            $this->writeOutput($job, 'failed');
        });
    }

    /**
     * Exécutez l'instance de travail.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int  $delay
     * @param  int  $memory
     * @param  bool  $daemon
     * @return array
     */
    protected function runWorker($connection, $queue, $delay, $memory, $daemon = false)
    {
        $this->worker->setDaemonExceptionHandler(
            $this->container['Two\Exceptions\Contracts\HandlerInterface']
        );

        $sleep = $this->option('sleep');
        $tries = $this->option('tries');

        if (! $daemon) {
            return $this->worker->runNextJob($connection, $queue, $delay, $sleep, $tries);
        }

        $this->worker->setCache(
            $this->container['cache']->driver()
        );

        return $this->worker->daemon($connection, $queue, $delay, $memory, $sleep, $tries);
    }

    /**
     * Écrivez la sortie d'état du gestionnaire de file d'attente.
     *
     * @param  \Two\Queue\Job  $job
     * @param  string  $status
     * @return void
     */
    protected function writeOutput(Job $job, $status)
    {
        switch ($status) {
            case 'starting':
                return $this->writeStatus($job, 'Processing', 'comment');
            case 'success':
                return $this->writeStatus($job, 'Processed', 'info');
            case 'failed':
                return $this->writeStatus($job, 'Failed', 'error');
        }
    }

    /**
     * Formatez la sortie d'état du gestionnaire de file d'attente.
     *
     * @param  \Two\Queue\Job  $job
     * @param  string  $status
     * @param  string  $type
     * @return void
     */
    protected function writeStatus(Job $job, $status, $type)
    {
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $message = sprintf("<{$type}>[%s] %s</{$type}> %s", $date, str_pad("{$status}:", 11), $job->resolveName());

        $this->output->writeln($message);
    }

    /**
     * Déterminez si le travailleur doit s’exécuter en mode maintenance.
     *
     * @return bool
     */
    protected function downForMaintenance()
    {
        if ($this->option('force')) {
            return false;
        }

        return $this->container->isDownForMaintenance();
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('connection', InputArgument::OPTIONAL, 'The name of connection', null),
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
            array('queue',  null, InputOption::VALUE_OPTIONAL, 'The queue to listen on'),
            array('daemon', null, InputOption::VALUE_NONE,     'Run the worker in daemon mode'),
            array('delay',  null, InputOption::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 0),
            array('force',  null, InputOption::VALUE_NONE,     'Force the worker to run even in maintenance mode'),
            array('memory', null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128),
            array('sleep',  null, InputOption::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3),
            array('tries',  null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 0),
        );
    }

}

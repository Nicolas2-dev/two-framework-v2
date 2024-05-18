<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Closure;

use Symfony\Component\Process\Process;


class Listener
{
    /**
     * Le chemin de travail de la commande.
     *
     * @var string
     */
    protected $commandPath;

    /**
     * L’environnement dans lequel les travailleurs devraient travailler.
     *
     * @var string
     */
    protected $environment;

    /**
     * Nombre de secondes à attendre avant d'interroger la file d'attente.
     *
     * @var int
     */
    protected $sleep = 3;

    /**
     * Nombre de tentatives d'exécution d'une tâche avant son échec de journalisation.
     *
     * @var int
     */
    protected $maxTries = 0;

    /**
     * La ligne de commande du gestionnaire de file d'attente.
     *
     * @var string
     */
    protected $workerCommand;

    /**
     * Le rappel du gestionnaire de sortie.
     *
     * @var \Closure|null
     */
    protected $outputHandler;


    /**
     * Créez un nouvel écouteur de file d'attente.
     *
     * @param  string  $commandPath
     * @return void
     */
    public function __construct($commandPath)
    {
        $this->commandPath = $commandPath;

        $this->workerCommand = '"' .PHP_BINARY .'" forge queue:work %s --queue="%s" --delay=%s --memory=%s --sleep=%s --tries=%s';
    }

    /**
     * Écoutez la connexion à la file d'attente donnée.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $delay
     * @param  string  $memory
     * @param  int     $timeout
     * @return void
     */
    public function listen($connection, $queue, $delay, $memory, $timeout = 60)
    {
        $process = $this->makeProcess($connection, $queue, $delay, $memory, $timeout);

        while(true) {
            $this->runProcess($process, $memory);
        }
    }

    /**
     * Exécutez le processus donné.
     *
     * @param  \Symfony\Component\Process\Process  $process
     * @param  int  $memory
     * @return void
     */
    public function runProcess(Process $process, $memory)
    {
        $process->run(function($type, $line)
        {
            $this->handleWorkerOutput($type, $line);
        });

        // Une fois que nous aurons exécuté le travail, nous irons vérifier si la limite de mémoire a été
        // dépassé pour le script. Si c'est le cas, nous tuerons ce script afin de
        // les gestionnaires de processus redémarreront cela avec une table rase de mémoire.

        if ($this->memoryExceeded($memory)) {
            $this->stop();

            return;
        }
    }

    /**
     * Créez un nouveau processus Symfony pour le travailleur.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int     $delay
     * @param  int     $memory
     * @param  int     $timeout
     * @return \Symfony\Component\Process\Process
     */
    public function makeProcess($connection, $queue, $delay, $memory, $timeout)
    {
        $string = $this->workerCommand;

        // Si l'environnement est défini, nous l'ajouterons à la chaîne de commande afin que le
        // Les travailleurs s'exécuteront dans l'environnement spécifié. Sinon, ils le feront
        // s'exécute simplement dans l'environnement de production, ce qui n'est pas toujours correct.
        if (isset($this->environment)) {
            $string .= ' --env=' .$this->environment;
        }

        // Ensuite, nous formaterons simplement les commandes de travail avec tous les différents
        // options disponibles pour la commande. Cela produira la commande finale
        // ligne que nous passerons dans un objet processus Symfony pour traitement.
        $command = sprintf(
            $string, $connection, $queue, $delay,
            $memory, $this->sleep, $this->maxTries
        );

        return new Process([$command], $this->commandPath, null, null, $timeout);
    }

    /**
     * Gérer la sortie du processus de travail.
     *
     * @param  int  $type
     * @param  string  $line
     * @return void
     */
    protected function handleWorkerOutput($type, $line)
    {
        if (isset($this->outputHandler)) {
            call_user_func($this->outputHandler, $type, $line);
        }
    }

    /**
     * Déterminez si la limite de mémoire a été dépassée.
     *
     * @param  int   $memoryLimit
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Arrêtez d'écouter et sortez du script.
     *
     * @return void
     */
    public function stop()
    {
        die;
    }

    /**
     * Définissez le rappel du gestionnaire de sortie.
     *
     * @param  \Closure  $outputHandler
     * @return void
     */
    public function setOutputHandler(Closure $outputHandler)
    {
        $this->outputHandler = $outputHandler;
    }

    /**
     * Obtenez l'environnement d'écoute actuel.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Définissez l'environnement actuel.
     *
     * @param  string  $environment
     * @return void
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Obtenez le nombre de secondes à attendre avant d'interroger la file d'attente.
     *
     * @return int
     */
    public function getSleep()
    {
        return $this->sleep;
    }

    /**
     * Définissez le nombre de secondes à attendre avant d'interroger la file d'attente.
     *
     * @param  int  $sleep
     * @return void
     */
    public function setSleep($sleep)
    {
        $this->sleep = $sleep;
    }

    /**
     * Définissez le nombre de tentatives d'une tâche avant de la consigner en cas d'échec.
     *
     * @param  int  $tries
     * @return void
     */
    public function setMaxTries($tries)
    {
        $this->maxTries = $tries;
    }

}

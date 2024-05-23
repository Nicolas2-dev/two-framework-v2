<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Exception;
use Throwable;

use Two\Queue\Job;
use Two\Support\Str;
use Two\Events\Dispatcher;
use Two\Cache\Repository as CacheRepository;
use Two\Exceptions\Exception\FatalThrowableError;
use Two\Queue\Contracts\Failed\FailedJobProviderInterface;


class Worker
{
    /**
     * Instance du gestionnaire de files d'attente.
     *
     * @var \Two\Queue\QueueManager
     */
    protected $manager;

    /**
     * L’échec de la mise en œuvre du fournisseur de travaux.
     *
     * @var \Two\Queue\Contracts\Failed\FailedJobProviderInterface
     */
    protected $failer;

    /**
     * Instance du répartiteur d’événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'implémentation du référentiel de cache.
     *
     * @var \Two\Cache\Repository
     */
    protected $cache;

    /**
     * Instance du gestionnaire d’exceptions.
     *
     * @var \Two\Exceptions\TwoHandler
     */
    protected $exceptions;

    /**
     * Indique si le travailleur doit quitter.
     *
     * @var bool
     */
    public $shouldQuit = false;


    /**
     * Créez un nouveau gestionnaire de file d'attente.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @param  \Two\Queue\Contracts\Failed\FailedJobProviderInterface  $failer
     * @param  \Two\Events\Dispatcher  $events
     * @return void
     */
    public function __construct(QueueManager $manager, FailedJobProviderInterface $failer = null, Dispatcher $events = null)
    {
        $this->failer  = $failer;
        $this->events  = $events;
        $this->manager = $manager;
    }

    /**
     * Écoutez la file d'attente donnée en boucle.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int     $delay
     * @param  int     $memory
     * @param  int     $sleep
     * @param  int     $maxTries
     * @return array
     */
    public function daemon($connection, $queue, $delay = 0, $memory = 128, $sleep = 3, $maxTries = 0)
    {
        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            if (! $this->daemonShouldRun($connection, $queue)) {
                $this->sleep($sleep);
            } else {
                $this->runNextJob($connection, $queue, $delay, $sleep, $maxTries);
            }

            if ($this->daemonShouldQuit()) {
                $this->kill();
            }

            // Vérifiez si le démon doit être arrêté.
            else if ($this->memoryExceeded($memory) || $this->queueShouldRestart($lastRestart)) {
                $this->stop();
            }
        }
    }

    /**
     * Déterminez si le démon doit traiter cette itération.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @return bool
     */
    protected function daemonShouldRun($connection, $queue)
    {
        if ($this->manager->isDownForMaintenance()) {
            return false;
        }

        $result = $this->events->until('Two.queue.looping', array($connection, $queue));

        return ($result !== false);
    }

    /**
     * Renvoie vrai si le démon doit se fermer.
     *
     * @return bool
     */
    protected function daemonShouldQuit()
    {
        return $this->shouldQuit;
    }

    /**
     * Écoutez la file d'attente donnée.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int     $delay
     * @param  int     $sleep
     * @param  int     $maxTries
     * @return array
     */
    public function runNextJob($connection, $queue = null, $delay = 0, $sleep = 3, $maxTries = 0)
    {
        $job = $this->getNextJob(
            $this->manager->connection($connection), $queue
        );

        // Si nous parvenons à extraire une tâche de la pile, nous la traiterons et
        // puis revient immédiatement. S'il n'y a aucun travail dans la file d'attente
        // nous allons "dormir" le travailleur pendant le nombre de secondes spécifié.

        if (! is_null($job)) {
            return $this->runJob($job, $connection, $maxTries, $delay);
        }

        $this->sleep($sleep);

        return array('job' => null, 'failed' => false);
    }

    /**
     * Obtenez le travail suivant à partir de la connexion à la file d'attente.
     *
     * @param  \Two\Queue\Queue  $connection
     * @param  string  $queue
     * @return \Two\Queue\Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        try {
            if (is_null($queue)) {
                return $connection->pop();
            }

            foreach (explode(',', $queue) as $queue) {
                if (! is_null($job = $connection->pop($queue))) {
                    return $job;
                }
            }
        }
        catch (Exception $e) {
            $this->handleException($e);
        }
        catch (Throwable $e) {
            $this->handleException(new FatalThrowableError($e));
        }
    }

    /**
     * Traitez le travail donné.
     *
     * @param  \Two\Queue\Job  $job
     * @param  string  $connection
     * @param  \Two\Queue\WorkerOptions  $options
     * @return void
     */
    protected function runJob($job, $connection, $maxTries, $delay)
    {
        try {
            return $this->process($connection, $job, $maxTries, $delay);
        }
        catch (Exception $e) {
            $this->handleException($e);
        }
        catch (Throwable $e) {
            $this->handleException(new FatalThrowableError($e));
        }
    }

    /**
     * Gérez une exception survenue lors du traitement d’une tâche.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function handleException($e)
    {
        if (isset($this->exceptions)) {
            $this->exceptions->report($e);
        }

        if ($this->causedByLostConnection($e)) {
            $this->shouldQuit = true;
        }
    }

    /**
     * Déterminez si l’exception donnée a été causée par une perte de connexion.
     *
     * @param  \Exception
     * @return bool
     */
    protected function causedByLostConnection(Exception $e)
    {
        return Str::contains($e->getMessage(), array(
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
        ));
    }

    /**
     * Traitez un travail donné à partir de la file d'attente.
     *
     * @param  string  $connection
     * @param  \Two\Queue\Job  $job
     * @param  int  $maxTries
     * @param  int  $delay
     * @return void
     *
     * @throws \Exception
     */
    public function process($connection, Job $job, $maxTries = 0, $delay = 0)
    {
        if (($maxTries > 0) && ($job->attempts() > $maxTries)) {
            return $this->logFailedJob($connection, $job);
        }

        // Nous allons d'abord déclencher l'événement avant le travail et lancer le travail. Une fois que c'est fait
        // nous verrons s'il sera automatiquement supprimé après traitement et si c'est le cas nous irons
        // avance et exécute la méthode delete sur le travail. Sinon, nous continuerons à avancer.


        try {
            $this->raiseBeforeJobEvent($connection, $job);

            $job->handle();

            if ($job->autoDelete()) {
                $job->delete();
            }

            $this->raiseAfterJobEvent($connection, $job);

            return array('job' => $job, 'failed' => false);
        }

        // Si nous captons une exception, nous tenterons de relâcher le travail sur
        // la file d'attente pour qu'elle ne soit pas perdue. Cela sera réessayé plus tard
        // heure par un autre auditeur (ou le même). Nous le ferons ici.

        catch (Exception $e) {
            if (! $job->isDeleted()) {
                $job->release($delay);
            }

            throw $e;
        }
        catch (Throwable $e) {
            if (! $job->isDeleted()) {
                $job->release($delay);
            }

            throw $e;
        }
    }

    /**
     * Enregistrez une tâche ayant échoué dans le stockage.
     *
     * @param  string  $connection
     * @param  \Two\Queue\Job  $job
     * @return array
     */
    protected function logFailedJob($connection, Job $job)
    {
        if (isset($this->failer)) {
            $this->failer->log(
                $connection, $job->getQueue(), $job->getRawBody()
            );

            $job->delete();

            $this->raiseFailedJobEvent($connection, $job);
        }

        return array('job' => $job, 'failed' => true);
    }

    /**
     * Déclenchez l'événement de travail avant la file d'attente.
     *
     * @param  string  $connection
     * @param  \Two\Queue\Job  $job
     * @return void
     */
    protected function raiseBeforeJobEvent($connection, Job $job)
    {
        if (isset($this->events)) {
            $this->events->dispatch('Two.queue.processing', array($connection, $job));
        }
    }

    /**
     * Déclenchez l'événement de travail après la file d'attente.
     *
     * @param  string  $connection
     * @param  \Two\Queue\Job  $job
     * @return void
     */
    protected function raiseAfterJobEvent($connection, Job $job)
    {
        if (isset($this->events)) {
            $this->events->dispatch('Two.queue.processed', array($connection, $job));
        }
    }

    /**
     * Déclenche l'événement de travail de file d'attente ayant échoué.
     *
     * @param  string  $connection
     * @param  \Two\Queue\Job  $job
     * @return void
     */
    protected function raiseFailedJobEvent($connection, Job $job)
    {
        if (isset($this->events)) {
            $this->events->dispatch('Two.queue.failed', array($connection, $job));
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
        $memoryUsage = memory_get_usage() / 1024 / 1024;

        return ($memoryUsage >= $memoryLimit);
    }

    /**
     * Arrêtez d'écouter et sortez du script.
     *
     * @param  int  $status
     * @return void
     */
    public function stop($status = 0)
    {
        $this->events->dispatch('Two.queue.stopping');

        exit($status);
    }

    /**
     * Tuez le processus.
     *
     * @param  int  $status
     * @return void
     */
    public function kill($status = 0)
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Mettez le script en veille pendant un nombre de secondes donné.
     *
     * @param  int   $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        sleep($seconds);
    }

    /**
     * Obtenez l’horodatage du dernier redémarrage de la file d’attente ou null.
     *
     * @return int|null
     */
    protected function getTimestampOfLastQueueRestart()
    {
        if (isset($this->cache)) {
            return $this->cache->get('Two:queue:restart');
        }
    }

    /**
     * Déterminez si le gestionnaire de file d'attente doit redémarrer.
     *
     * @param  int|null  $lastRestart
     * @return bool
     */
    protected function queueShouldRestart($lastRestart)
    {
        return ($this->getTimestampOfLastQueueRestart() != $lastRestart);
    }

    /**
     * Définissez le gestionnaire d'exceptions à utiliser en mode démon.
     *
     * @param  \Two\Exceptions\ExceptionHandler  $handler
     * @return void
     */
    public function setDaemonExceptionHandler($handler)
    {
        $this->exceptions = $handler;
    }

    /**
     * Définissez l'implémentation du référentiel de cache.
     *
     * @param  \Two\Cache\Repository  $cache
     * @return void
     */
    public function setCache(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Obtenez l'instance du gestionnaire de files d'attente.
     *
     * @return \Two\Queue\QueueManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Définissez l'instance du gestionnaire de files d'attente.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    public function setManager(QueueManager $manager)
    {
        $this->manager = $manager;
    }

}

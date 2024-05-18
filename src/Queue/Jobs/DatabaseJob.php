<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Jobs;

use Two\Container\Container;
use Two\Queue\Queues\DatabaseQueue;
use Two\Queue\Job;


class DatabaseJob extends Job
{
    /**
     * Instance de file d’attente de base de données.
     *
     * @var \Two\Queue\DatabaseQueue
     */
    protected $database;

    /**
     * Charge utile du travail de base de données.
     *
     * @var \StdClass
     */
    protected $job;


    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Two\Queue\DatabaseQueue  $database
     * @param  \StdClass  $job
     * @param  string  $queue
     * @return void
     */
    public function __construct(Container $container, DatabaseQueue $database, $job, $queue)
    {
        $this->job = $job;
        $this->queue = $queue;
        $this->database = $database;
        $this->container = $container;

        $this->job->attempts = $this->job->attempts + 1;
    }

    /**
     * Licenciez le travail.
     *
     * @return void
     */
    public function handle()
    {
        $payload = json_decode($this->getRawBody(), true);

        $this->resolveAndHandle($payload);
    }

    /**
     * Obtenez la corde de corps brute pour le travail.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->payload;
    }

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->database->deleteReserved($this->queue, $this->job->id);
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->delete();

        $this->database->release($this->queue, $this->job, $delay);
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->attempts;
    }

    /**
     * Obtenez l'identifiant du travail.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->id;
    }

    /**
     * Obtenez l'instance de conteneur IoC.
     *
     * @return \Two\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Obtenez l'instance de pilote de file d'attente sous-jacente.
     *
     * @return \Two\Queue\DatabaseQueue
     */
    public function getDatabaseQueue()
    {
        return $this->database;
    }

    /**
     * Obtenez le travail de base de données sous-jacent.
     *
     * @return \StdClass
     */
    public function getDatabaseJob()
    {
        return $this->job;
    }
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Jobs;

use Two\Container\Container;
use Two\Queue\Job;


class SyncJob extends Job
{

    /**
     * Le nom de classe du travail.
     *
     * @var string
     */
    protected $job;

    /**
     * Les données des messages de file d'attente.
     *
     * @var string
     */
    protected $payload;


    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  string  $job
     * @param  string  $data
     * @return void
     */
    public function __construct(Container $container, $payload, $queue = '')
    {
        $this->payload = $payload;
        $this->queue = $queue;
        $this->container = $container;
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
        return $this->payload;
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        //
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return 1;
    }

    /**
     * Obtenez l'identifiant du travail.
     *
     * @return string
     */
    public function getJobId()
    {
        return '';
    }

}

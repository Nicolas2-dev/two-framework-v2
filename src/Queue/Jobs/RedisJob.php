<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Jobs;

use Two\Container\Container;
use Two\Queue\Queues\RedisQueue;
use Two\Queue\Job;


class RedisJob extends Job
{

    /**
     * L'instance de file d'attente Redis.
     *
     * @var \Two\Queue\RedisQueue
     */
    protected $redis;

    /**
     * La charge utile de la tâche Redis.
     *
     * @var string
     */
    protected $job;

    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Two\Queue\RedisQueue  $redis
     * @param  string  $job
     * @param  string  $queue
     * @return void
     */
    public function __construct(Container $container, RedisQueue $redis, $job, $queue)
    {
        $this->job = $job;
        $this->redis = $redis;
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
        return $this->job;
    }

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->redis->deleteReserved($this->queue, $this->job);
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->delete();

        $this->redis->release($this->queue, $this->job, $delay, $this->attempts() + 1);
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return array_get(json_decode($this->job, true), 'attempts');
    }

    /**
     * Obtenez l'identifiant du travail.
     *
     * @return string
     */
    public function getJobId()
    {
        return array_get(json_decode($this->job, true), 'id');
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
     * @return \Two\Redis\Database
     */
    public function getRedisQueue()
    {
        return $this->redis;
    }

    /**
     * Obtenez le travail Redis sous-jacent.
     *
     * @return string
     */
    public function getRedisJob()
    {
        return $this->job;
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Queue\Jobs\SyncJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;


class SyncQueue extends Queue implements QueueInterface
{
    /**
     * Placez un nouveau travail dans la file d'attente.
     *
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        $queueJob = $this->resolveJob($this->createPayload($job, $data, $queue), $queue);

        $queueJob->handle();

        return 0;
    }

    /**
     * Insérez une charge utile brute dans la file d'attente.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array   $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = array())
    {
        //
    }

    /**
     * Placez une nouvelle tâche dans la file d'attente après un certain délai.
     *
     * @param  \DateTime|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Retirez le travail suivant de la file d'attente.
     *
     * @param  string  $queue
     * @return \Two\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        //
    }

    /**
     * Résolvez une instance de tâche de synchronisation.
     *
     * @param  string  $payload
     * @param  string  $queues
     * @return \Two\Queue\Jobs\SyncJob
     */
    protected function resolveJob($payload, $queue)
    {
        return new SyncJob($this->container, $payload, $queue);
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Traits;

use Two\Queue\Job;


trait InteractsWithQueueTrait
{
    /**
     * Instance de tâche de file d'attente sous-jacente.
     *
     * @var \Two\Queue\Job
     */
    protected $job;

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        if (isset($this->job)) {
            return $this->job->delete();
        }
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        if (isset($this->job)) {
            return $this->job->release($delay);
        }
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return isset($this->job) ? $this->job->attempts() : 1;
    }

    /**
     * Définissez l'instance de travail de file d'attente de base.
     *
     * @param  \Two\Queue\Job  $job
     * @return $this
     */
    public function setJob(Job $job)
    {
        $this->job = $job;

        return $this;
    }
}

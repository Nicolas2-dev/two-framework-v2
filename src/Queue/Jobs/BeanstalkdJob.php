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

use Pheanstalk_Job;
use Pheanstalk_Pheanstalk as Pheanstalk;


class BeanstalkdJob extends Job
{

    /**
     * L'instance Pheanstalk.
     *
     * @var \Pheanstalk_Pheanstalk
     */
    protected $pheanstalk;

    /**
     * L'instance de travail Pheanstalk.
     *
     * @var \Pheanstalk_Job
     */
    protected $job;

    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Pheanstalk_Pheanstalk  $pheanstalk
     * @param  \Pheanstalk_Job  $job
     * @param  string  $queue
     * @return void
     */
    public function __construct(Container $container,
                                Pheanstalk $pheanstalk,
                                Pheanstalk_Job $job,
                                $queue)
    {
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->pheanstalk = $pheanstalk;
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
        return $this->job->getData();
    }

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->pheanstalk->delete($this->job);
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $priority = Pheanstalk::DEFAULT_PRIORITY;

        $this->pheanstalk->release($this->job, $priority, $delay);
    }

    /**
     * Enterrez le travail dans la file d’attente.
     *
     * @return void
     */
    public function bury()
    {
        $this->pheanstalk->bury($this->job);
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        $stats = $this->pheanstalk->statsJob($this->job);

        return (int) $stats->reserves;
    }

    /**
     * Obtenez l'identifiant du travail.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->getId();
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
     * Obtenez l'instance Pheanstalk sous-jacente.
     *
     * @return \Pheanstalk_Pheanstalk
     */
    public function getPheanstalk()
    {
        return $this->pheanstalk;
    }

    /**
     * Obtenez le travail Pheanstalk sous-jacent.
     *
     * @return \Pheanstalk_Job
     */
    public function getPheanstalkJob()
    {
        return $this->job;
    }

}

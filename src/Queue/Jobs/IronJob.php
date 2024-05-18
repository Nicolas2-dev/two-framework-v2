<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Jobs;

use Two\Container\Container;
use Two\Queue\Queues\IronQueue;
use Two\Queue\Job;


class IronJob extends Job
{

    /**
     * L'instance de file d'attente Iron.
     *
     * @var \Two\Queue\IronQueue
     */
    protected $iron;

    /**
     * L'instance de message IronMQ.
     *
     * @var object
     */
    protected $job;

    /**
     * Indique si le message était un message push.
     *
     * @var bool
     */
    protected $pushed = false;

    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Two\Queue\IronQueue  $iron
     * @param  object  $job
     * @param  bool    $pushed
     * @return void
     */
    public function __construct(Container $container,
                                IronQueue $iron,
                                $job,
                                $pushed = false)
    {
        $this->job = $job;
        $this->iron = $iron;
        $this->pushed = $pushed;
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
     * Obtenez la chaîne de corps brute pour le travail.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->body;
    }

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        if (isset($this->job->pushed)) return;

        $this->iron->deleteMessage($this->getQueue(), $this->job->id);
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        if ( ! $this->pushed) $this->delete();

        $this->recreateJob($delay);
    }

    /**
     * Remettez une tâche repoussée dans la file d'attente.
     *
     * @param  int  $delay
     * @return void
     */
    protected function recreateJob($delay)
    {
        $payload = json_decode($this->job->body, true);

        array_set($payload, 'attempts', array_get($payload, 'attempts', 1) + 1);

        $this->iron->recreate(json_encode($payload), $this->getQueue(), $delay);
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return array_get(json_decode($this->job->body, true), 'attempts', 1);
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
     * Obtenez l’instance de file d’attente Iron sous-jacente.
     *
     * @return \Two\Queue\IronQueue
     */
    public function getIron()
    {
        return $this->iron;
    }

    /**
     * Obtenez le travail IronMQ sous-jacent.
     *
     * @return array
     */
    public function getIronJob()
    {
        return $this->job;
    }

    /**
     * Obtenez le nom de la file d'attente à laquelle appartient le travail.
     *
     * @return string
     */
    public function getQueue()
    {
        return array_get(json_decode($this->job->body, true), 'queue');
    }

}

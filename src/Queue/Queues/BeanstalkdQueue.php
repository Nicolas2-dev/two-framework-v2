<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Queue\Jobs\BeanstalkdJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;

use Pheanstalk_Job;
use Pheanstalk_Pheanstalk as Pheanstalk;


class BeanstalkdQueue extends Queue implements QueueInterface
{

    /**
     * L'instance Pheanstalk.
     *
     * @var \Pheanstalk_Pheanstalk
     */
    protected $pheanstalk;

    /**
     * Le nom du tube par défaut.
     *
     * @var string
     */
    protected $default;

    /**
     * Le « temps d'exécution » pour toutes les tâches poussées.
     *
     * @var int
     */
    protected $timeToRun;


    /**
     * Créez une nouvelle instance de file d'attente Beanstalkd.
     *
     * @param  \Pheanstalk_Pheanstalk  $pheanstalk
     * @param  string  $default
     * @param  int  $timeToRun
     * @return void
     */
    public function __construct(Pheanstalk $pheanstalk, $default, $timeToRun)
    {
        $this->default = $default;
        $this->timeToRun = $timeToRun;
        $this->pheanstalk = $pheanstalk;
    }

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
        return $this->pushRaw($this->createPayload($job, $data), $queue);
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
        return $this->pheanstalk->useTube($this->getQueue($queue))->put(
            $payload, Pheanstalk::DEFAULT_PRIORITY, Pheanstalk::DEFAULT_DELAY, $this->timeToRun
        );
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
        $payload = $this->createPayload($job, $data);

        $pheanstalk = $this->pheanstalk->useTube($this->getQueue($queue));

        return $pheanstalk->put($payload, Pheanstalk::DEFAULT_PRIORITY, $this->getSeconds($delay), $this->timeToRun);
    }

    /**
     * Retirez le travail suivant de la file d'attente.
     *
     * @param  string  $queue
     * @return \Two\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $job = $this->pheanstalk->watchOnly($queue)->reserve(0);

        if ($job instanceof Pheanstalk_Job) {
            return new BeanstalkdJob($this->container, $this->pheanstalk, $job, $queue);
        }
    }

    /**
     * Supprimez un message de la file d'attente Beanstalk.
     *
     * @param  string  $queue
     * @param  string  $id
     * @return void
     */
    public function deleteMessage($queue, $id)
    {
        $this->pheanstalk->useTube($this->getQueue($queue))->delete($id);
    }

    /**
     * Obtenez la file d'attente ou renvoyez la valeur par défaut.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
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

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Queue\Jobs\SqsJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;

use Aws\Sqs\SqsClient;


class SqsQueue extends Queue implements QueueInterface
{
    /**
     * L'instance Amazon SQS.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * Le nom du tube par défaut.
     *
     * @var string
     */
    protected $default;

    /**
     * Créez une nouvelle instance de file d'attente Amazon SQS.
     *
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  string  $default
     * @return void
     */
    public function __construct(SqsClient $sqs, $default)
    {
        $this->sqs = $sqs;

        $this->default = $default;
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
        $response = $this->sqs->sendMessage(array('QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload));

        return $response->get('MessageId');
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

        $delay = $this->getSeconds($delay);

        return $this->sqs->sendMessage(array(

            'QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload, 'DelaySeconds' => $delay,

        ))->get('MessageId');
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

        $response = $this->sqs->receiveMessage(
            array('QueueUrl' => $queue, 'AttributeNames' => array('ApproximateReceiveCount'))
        );

        if (count($response['Messages']) > 0)
        {
            return new SqsJob($this->container, $this->sqs, $queue, $response['Messages'][0]);
        }
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
     * Obtenez l'instance SQS sous-jacente.
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getSqs()
    {
        return $this->sqs;
    }

}

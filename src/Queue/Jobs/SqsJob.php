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

use Aws\Sqs\SqsClient;


class SqsJob extends Job
{

    /**
     * L'instance client Amazon SQS.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * L'instance de travail Amazon SQS.
     *
     * @var array
     */
    protected $job;

    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  string  $queue
     * @param  array   $job
     * @return void
     */
    public function __construct(Container $container,
                                SqsClient $sqs,
                                $queue,
                                array $job)
    {
        $this->sqs = $sqs;
        $this->job = $job;
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
        return $this->job['Body'];
    }

    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->sqs->deleteMessage(array(

            'QueueUrl' => $this->queue, 'ReceiptHandle' => $this->job['ReceiptHandle'],

        ));
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        // Les versions de travaux SQS sont gérées par la configuration du serveur...
    }

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job['Attributes']['ApproximateReceiveCount'];
    }

    /**
     * Obtenez l'identifiant du travail.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job['MessageId'];
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
     * Obtenez l'instance client SQS sous-jacente.
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getSqs()
    {
        return $this->sqs;
    }

    /**
     * Obtenez le travail SQS brut sous-jacent.
     *
     * @return array
     */
    public function getSqsJob()
    {
        return $this->job;
    }

}

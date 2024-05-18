<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Http\Request;
use Two\Http\Response;
use Two\Queue\Jobs\IronJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;

use IronMQ;


class IronQueue extends Queue implements QueueInterface
{
    /**
     * L'instance IronMQ.
     *
     * @var IronMQ
     */
    protected $iron;

    /**
     * L’instance de requête actuelle.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * Le nom du tube par défaut.
     *
     * @var string
     */
    protected $default;

    /**
     * Indique si les messages doivent être chiffrés.
     *
     * @var bool
     */
    protected $shouldEncrypt;

    /**
     * 
     */
    protected $crypt;

    /**
     * Créez une nouvelle instance de file d'attente IronMQ.
     *
     * @param  \IronMQ  $iron
     * @param  \Two\Http\Request  $request
     * @param  string  $default
     * @param  bool  $shouldEncrypt
     * @return void
     */
    public function __construct(IronMQ $iron, Request $request, $default, $shouldEncrypt = false)
    {
        $this->iron = $iron;
        $this->request = $request;
        $this->default = $default;
        $this->shouldEncrypt = $shouldEncrypt;
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
        return $this->pushRaw($this->createPayload($job, $data, $queue), $queue);
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
        if ($this->shouldEncrypt) $payload = $this->crypt->encrypt($payload);

        return $this->iron->postMessage($this->getQueue($queue), $payload, $options)->id;
    }

    /**
     * Insérez une charge utile brute dans la file d'attente après avoir chiffré la charge utile.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  int     $delay
     * @return mixed
     */
    public function recreate($payload, $queue = null, $delay)
    {
        $options = array('delay' => $this->getSeconds($delay));

        return $this->pushRaw($payload, $queue, $options);
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
        $delay = $this->getSeconds($delay);

        $payload = $this->createPayload($job, $data, $queue);

        return $this->pushRaw($payload, $queue, compact('delay'));
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

        $job = $this->iron->getMessage($queue);

        // Si nous parvenons à retirer un message de la file d'attente, nous devrons le déchiffrer
        // le corps du message, car tous les messages Iron.io sont cryptés, depuis le push
        // les files d'attente constitueront un risque de sécurité pour les développeurs peu méfiants qui l'utilisent.
        if ( ! is_null($job))
        {
            $job->body = $this->parseJobBody($job->body);

            return new IronJob($this->container, $this, $job);
        }
    }

    /**
     * Supprimez un message de la file d'attente Iron.
     *
     * @param  string  $queue
     * @param  string  $id
     * @return void
     */
    public function deleteMessage($queue, $id)
    {
        $this->iron->deleteMessage($queue, $id);
    }

    /**
     * Organisez une demande de file d’attente push et lancez la tâche.
     *
     * @return \Two\Http\Response
     */
    public function marshal()
    {
        $this->createPushedIronJob($this->marshalPushedJob())->handle();

        return new Response('OK');
    }

    /**
     * Organisez le travail poussé et la charge utile.
     *
     * @return object
     */
    protected function marshalPushedJob()
    {
        $r = $this->request;

        $body = $this->parseJobBody($r->getContent());

        return (object) array(
            'id' => $r->header('iron-message-id'), 'body' => $body, 'pushed' => true,
        );
    }

    /**
     * Créez un nouvel IronJob pour un travail poussé.
     *
     * @param  object  $job
     * @return \Two\Queue\Jobs\IronJob
     */
    protected function createPushedIronJob($job)
    {
        return new IronJob($this->container, $this, $job, true);
    }

    /**
     * Créez une chaîne de charge utile à partir de la tâche et des données données.
     *
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return string
     */
    protected function createPayload($job, $data = '', $queue = null)
    {
        $payload = $this->setMeta(parent::createPayload($job, $data), 'attempts', 1);

        return $this->setMeta($payload, 'queue', $this->getQueue($queue));
    }

    /**
     * Analyser le corps de travail pour le tir.
     *
     * @param  string  $body
     * @return string
     */
    protected function parseJobBody($body)
    {
        return $this->shouldEncrypt ? $this->crypt->decrypt($body) : $body;
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
     * Obtenez l'instance IronMQ sous-jacente.
     *
     * @return \IronMQ
     */
    public function getIron()
    {
        return $this->iron;
    }

    /**
     * Obtenez l’instance de requête.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Définissez l'instance de requête.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Closure;
use DateTime;
use RuntimeException;

use Two\Container\Container;
use Two\Encryption\Encrypter;
use Two\Queue\Contracts\QueueableEntityInterface;

use SuperClosure\Serializer;


abstract class Queue
{
    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * 
     */
    protected $crypt;

    /**
     * Placez un nouveau travail dans la file d'attente.
     *
     * @param  string  $queue
     * @param  string  $job
     * @param  mixed   $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '')
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Placez une nouvelle tâche dans la file d'attente après un certain délai.
     *
     * @param  string  $queue
     * @param  \DateTime|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->later($delay, $job, $data, $queue);
    }

    /**
     * Organisez une demande de file d’attente push et lancez la tâche.
     *
     * @throws \RuntimeException
     */
    public function marshal()
    {
        throw new RuntimeException("Push queues only supported by Iron.");
    }

    /**
     * Placez un ensemble de tâches dans la file d'attente.
     *
     * @param  array   $jobs
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            $this->push($job, $data, $queue);
        }
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
        if ($job instanceof Closure) {
            $payload = $this->createClosurePayload($job, $data);
        } else if (is_object($job)) {
            $payload = $this->createObjectPayload($job, $data);
        } else {
            $payload = array(
                'job'  => $job,
                'data' => $this->prepareQueueableEntities($data),
            );
        }

        return json_encode($payload);
    }

    /**
     * Préparez toutes les entités pouvant être placées en file d'attente pour le stockage dans la file d'attente.
     *
     * @param  mixed  $data
     * @return mixed
     */
    protected function prepareQueueableEntities($data)
    {
        if ($data instanceof QueueableEntityInterface) {
            return $this->prepareQueueableEntity($data);
        }

        if (! is_array($data)) {
            return $data;
        }

        return array_map(function ($d)
        {
            if (is_array($d)) {
                return $this->prepareQueueableEntities($d);
            }

            return $this->prepareQueueableEntity($d);

        }, $data);
    }

    /**
     * Préparez une seule entité pouvant être stockée dans la file d'attente.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function prepareQueueableEntity($value)
    {
        if ($value instanceof QueueableEntityInterface) {
            return '::entity::|' .get_class($value) .'|' .$value->getQueueableId();
        }

        return $value;
    }

    /**
     * Créez une chaîne de charge utile pour la tâche de fermeture donnée.
     *
     * @param  object  $job
     * @param  mixed   $data
     * @return string
     */
    protected function createObjectPayload($job, $data)
    {
        $commandName = get_class($job);

        $command = serialize(clone $job);

        return array(
            'job'  => 'Two\Queue\CallQueuedHandler@call',

            'data' => compact('commandName', 'command'),
        );
    }

    /**
     * Créez une chaîne de charge utile pour la tâche de fermeture donnée.
     *
     * @param  \Closure  $job
     * @param  mixed     $data
     * @return string
     */
    protected function createClosurePayload($job, $data)
    {
        $closure = $this->crypt->encrypt(
            with(new Serializer)->serialize($job)
        );

        return array(
            'job'  => 'Two\Queue\CallQueuedClosure@call',

            'data' => compact('closure')
        );
    }

    /**
     * Définissez des méta supplémentaires sur une chaîne de charge utile.
     *
     * @param  string  $payload
     * @param  string  $key
     * @param  string  $value
     * @return string
     */
    protected function setMeta($payload, $key, $value)
    {
        $payload = json_decode($payload, true);

        return json_encode(array_set($payload, $key, $value));
    }

    /**
     * Calculez le nombre de secondes avec le délai donné.
     *
     * @param  \DateTime|int  $delay
     * @return int
     */
    protected function getSeconds($delay)
    {
        if ($delay instanceof DateTime) {
            return max(0, $delay->getTimestamp() - $this->getTime());
        }

        return (int) $delay;
    }

    /**
     * Obtenez l'horodatage UNIX actuel.
     *
     * @return int
     */
    public function getTime()
    {
        return time();
    }

    /**
     * Définissez l'instance de conteneur IoC.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Définissez l'instance de chiffrement.
     *
     * @param  \Two\Encryption\Encrypter  $crypt
     * @return void
     */
    public function setEncrypter(Encrypter $crypt)
    {
        $this->crypt = $crypt;
    }

}

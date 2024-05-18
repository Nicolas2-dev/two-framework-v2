<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use DateTime;

use Two\Support\Arr;
use Two\Support\Str;


abstract class Job
{
    /**
     * Instance du gestionnaire de tâches.
     *
     * @var mixed
     */
    protected $instance;

    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Nom de la file d'attente à laquelle appartient le travail.
     *
     * @var string
     */
    protected $queue;

    /**
     * Indique si le travail a été supprimé.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Licenciez le travail.
     *
     * @return void
     */
    abstract public function handle();


    /**
     * Supprimez le travail de la file d'attente.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Déterminez si le travail a été supprimé.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Remettez le travail dans la file d'attente.
     *
     * @param  int   $delay
     * @return void
     */
    abstract public function release($delay = 0);

    /**
     * Obtenez le nombre de tentatives de travail.
     *
     * @return int
     */
    abstract public function attempts();

    /**
     * Obtenez la corde de corps brute pour le travail.
     *
     * @return string
     */
    abstract public function getRawBody();

    /**
     * Résolvez et lancez la méthode du gestionnaire de tâches.
     *
     * @param  array  $payload
     * @return void
     */
    protected function resolveAndHandle(array $payload)
    {
        list($class, $method) = Str::parseCallback($payload['job'], 'handle');

        $this->instance = $this->resolve($class);

        call_user_func(array($this->instance, $method), $this, $payload['data']);
    }

    /**
     * Résolvez le gestionnaire de travaux donné.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function resolve($class)
    {
        return $this->container->make($class);
    }

    /**
     * Déterminez si le travail doit être supprimé automatiquement.
     *
     * @return bool
     */
    public function autoDelete()
    {
        return isset($this->instance->delete);
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
     * Obtenez l'heure actuelle du système.
     *
     * @return int
     */
    protected function getTime()
    {
        return time();
    }

    /**
     * Obtenez le nom de la classe de travaux en file d'attente.
     *
     * @return string
     */
    public function getName()
    {
        $payload = json_decode($this->getRawBody(), true);

        return $payload['job'];
    }

    /**
     * Obtenez le nom résolu de la classe de travaux en file d’attente.
     *
     * @return string
     */
    public function resolveName()
    {
        $payload = json_decode($this->getRawBody(), true);

        //
        $name = $payload['job'];

        // Lorsque le travail est une fermeture.
        if ($name == 'Two\Queue\CallQueuedClosure@call') {
            return 'Closure';
        }

        // Lorsque le poste est celui de Handler.
        else if ($name == 'Two\Queue\CallQueuedHandler@call') {
            return Arr::get($payload, 'data.commandName', $name);
        }

        // Lorsque le travail est un événement.
        else if ($name == 'Two\Events\CallQueuedHandler@call') {
            $className = Arr::get($payload, 'data.class');

            $method = Arr::get($payload, 'data.method');

            return $className .'@' .$method;
        }

        return $name;
    }

    /**
     * Obtenez le nom de la file d'attente à laquelle appartient le travail.
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

}

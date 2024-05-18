<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Database\Connection;
use Two\Queue\Jobs\DatabaseJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;

use Carbon\Carbon;

use DateTime;


class DatabaseQueue extends Queue implements QueueInterface
{
    /**
     * L'instance de connexion à la base de données.
     *
     * @var \Two\Database\Connection
     */
    protected $database;

    /**
     * Table de base de données qui contient les tâches.
     *
     * @var string
     */
    protected $table;

    /**
     * Le nom de la file d'attente par défaut.
     *
     * @var string
     */
    protected $default;

    /**
     * Le délai d'expiration d'un travail.
     *
     * @var int|null
     */
    protected $retryAfter = 60;

    /**
     * 
     */
    protected $expire;

    /**
     * Créez une nouvelle instance de file d'attente de base de données.
     *
     * @param  \Two\Database\Connection  $database
     * @param  string  $table
     * @param  string  $default
     * @param  int  $retryAfter
     * @return void
     */
    public function __construct(Connection $database, $table, $default = 'default', $retryAfter = 60)
    {
        $this->table = $table;
        $this->default = $default;
        $this->database = $database;
        $this->retryAfter = $retryAfter;
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
        return $this->pushToDatabase(0, $queue, $this->createPayload($job, $data));
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
        return $this->pushToDatabase(0, $queue, $payload);
    }

    /**
     * Placez une nouvelle tâche dans la file d'attente après un certain délai.
     *
     * @param  \DateTime|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return void
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushToDatabase($delay, $queue, $this->createPayload($job, $data));
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
        $queue = $this->getQueue($queue);

        $availableAt = $this->getAvailableAt(0);

        $records = array_map(function ($job) use ($queue, $data, $availableAt)
        {
            return $this->buildDatabaseRecord(
                $queue, $this->createPayload($job, $data), $availableAt
            );

        }, (array) $jobs);

        return $this->database->table($this->table)->insert($records);
    }

    /**
     * Remettez une tâche réservée dans la file d'attente.
     *
     * @param  string  $queue
     * @param  \StdClass  $job
     * @param  int  $delay
     * @return mixed
     */
    public function release($queue, $job, $delay)
    {
        return $this->pushToDatabase($delay, $queue, $job->payload, $job->attempts);
    }

    /**
     * Envoyez une charge utile brute vers la base de données avec un délai donné.
     *
     * @param  \DateTime|int  $delay
     * @param  string|null  $queue
     * @param  string  $payload
     * @param  int  $attempts
     * @return mixed
     */
    protected function pushToDatabase($delay, $queue, $payload, $attempts = 0)
    {
        $attributes = $this->buildDatabaseRecord(
            $this->getQueue($queue), $payload, $this->getAvailableAt($delay), $attempts
        );

        return $this->database->table($this->table)->insertGetId($attributes);
    }

    /**
     * Retirez le travail suivant de la file d'attente.
     *
     * @param  string  $queue
     * @return \Two\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->database->transaction(function () use ($queue)
        {
            if (! is_null($job = $this->getNextAvailableJob($queue))) {
                $this->markJobAsReserved($job->id, $job->attempts);

                return new DatabaseJob(
                    $this->container, $this, $job, $queue
                );
            }
        });
    }

    /**
     * Obtenez le prochain travail disponible pour la file d'attente.
     *
     * @param  string|null  $queue
     * @return \StdClass|null
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->database->table($this->table)
            ->lockForUpdate()
            ->where('queue', $this->getQueue($queue))
            ->where(function ($query)
            {
                $this->isAvailable($query);

                $this->isReservedButExpired($query);
            })
            ->orderBy('id', 'asc')
            ->first();

        if (! is_null($job)) {
            return (object) $job;
        }
    }

    /**
     * Modifiez la requête pour vérifier les tâches disponibles.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return void
     */
    protected function isAvailable($query)
    {
        $query->where(function ($query)
        {
            $query->whereNull('reserved_at')->where('available_at', '<=', $this->getTime());
        });
    }

    /**
     * Modifiez la requête pour rechercher les tâches réservées mais expirées.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return void
     */
    protected function isReservedButExpired($query)
    {
        $expiration = Carbon::now()->subSeconds($this->retryAfter)->getTimestamp();

        $query->orWhere(function ($query) use ($expiration)
        {
            $query->where('reserved_at', '<=', $expiration);
        });
    }

    /**
     * Marquez l'ID de travail donné comme réservé.
     *
     * @param  string  $id
     * @param  int     $attempts
     * @return void
     */
    protected function markJobAsReserved($id, $attempts)
    {
        $this->database->table($this->table)->where('id', $id)->update(array(
            'attempts'    => $attempts,
            'reserved_at' => $this->getTime(),
        ));
    }

    /**
     * Supprimez une tâche réservée de la file d'attente.
     *
     * @param  string  $queue
     * @param  string  $id
     * @return void
     */
    public function deleteReserved($queue, $id)
    {
        $this->database->transaction(function () use ($id)
        {
            $record = $this->database->table($this->table)->lockForUpdate()->find($id);

            if (! is_null($record)) {
                $this->database->table($this->table)->where('id', $id)->delete();
            }
        });
    }

    /**
     * Obtenez l'horodatage UNIX "disponible à".
     *
     * @param  \DateTime|int  $delay
     * @return int
     */
    protected function getAvailableAt($delay)
    {
        $availableAt = ($delay instanceof DateTime) ? $delay : Carbon::now()->addSeconds($delay);

        return $availableAt->getTimestamp();
    }

    /**
     * Créez un tableau à insérer pour le travail donné.
     *
     * @param  string|null  $queue
     * @param  string  $payload
     * @param  int  $availableAt
     * @param  int  $attempts
     * @return array
     */
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
    {
        return array(
            'queue'        => $queue,
            'payload'      => $payload,
            'attempts'     => $attempts,
            'reserved_at'  => null,
            'available_at' => $availableAt,
            'created_at'   => $this->getTime(),
        );
    }

    /**
     * Obtenez la file d'attente ou renvoyez la valeur par défaut.
     *
     * @param  string|null  $queue
     * @return string
     */
    protected function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Obtenez l'instance de base de données sous-jacente.
     *
     * @return \Two\Database\Connection
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Obtenez le délai d'expiration en secondes.
     *
     * @return int|null
     */
    public function getExpire()
    {
        return $this->expire;
    }

    /**
     * Définissez le délai d'expiration en secondes.
     *
     * @param  int|null  $seconds
     * @return void
     */
    public function setExpire($seconds)
    {
        $this->expire = $seconds;
    }

}

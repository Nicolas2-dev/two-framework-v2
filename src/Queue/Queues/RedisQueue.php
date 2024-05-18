<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Queues;

use Two\Queue\Jobs\RedisJob;
use Two\Queue\Queue;
use Two\Queue\Contracts\QueueInterface;

use Two\Redis\Database;


class RedisQueue extends Queue implements QueueInterface
{
    /**
     * L'instance de base de données Redis.
     *
     * @var \Two\Redis\Database
     */
    protected $redis;

    /**
     * Le nom de la connexion.
     *
     * @var string
     */
    protected $connection;

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
    protected $expire = 60;


    /**
     * Créez une nouvelle instance de file d'attente Redis.
     *
     * @param  \Two\Redis\Database  $redis
     * @param  string  $default
     * @param  string  $connection
     * @return void
     */
    public function __construct(Database $redis, $default = 'default', $connection = null)
    {
        $this->redis = $redis;
        $this->default = $default;
        $this->connection = $connection;
    }

    /**
     * Placez un nouveau travail dans la file d'attente.
     *
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return void
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
        $this->getConnection()->rpush($this->getQueue($queue), $payload);

        return array_get(json_decode($payload, true), 'id');
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
        $payload = $this->createPayload($job, $data);

        $delay = $this->getSeconds($delay);

        $this->getConnection()->zadd($this->getQueue($queue).':delayed', $this->getTime() + $delay, $payload);

        return array_get(json_decode($payload, true), 'id');
    }

    /**
     * Remettez une tâche réservée dans la file d'attente.
     *
     * @param  string  $queue
     * @param  string  $payload
     * @param  int  $delay
     * @param  int  $attempts
     * @return void
     */
    public function release($queue, $payload, $delay, $attempts)
    {
        $payload = $this->setMeta($payload, 'attempts', $attempts);

        $this->getConnection()->zadd($this->getQueue($queue).':delayed', $this->getTime() + $delay, $payload);
    }

    /**
     * Retirez le travail suivant de la file d'attente.
     *
     * @param  string  $queue
     * @return \Two\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        $original = $queue ?: $this->default;

        $queue = $this->getQueue($queue);

        if ( ! is_null($this->expire)) {
            $this->migrateAllExpiredJobs($queue);
        }

        $job = $this->getConnection()->lpop($queue);

        if ( ! is_null($job)) {
            $this->getConnection()->zadd($queue.':reserved', $this->getTime() + $this->expire, $job);

            return new RedisJob($this->container, $this, $job, $original);
        }
    }

    /**
     * Supprimez une tâche réservée de la file d'attente.
     *
     * @param  string  $queue
     * @param  string  $job
     * @return void
     */
    public function deleteReserved($queue, $job)
    {
        $this->getConnection()->zrem($this->getQueue($queue).':reserved', $job);
    }

    /**
     * Migrez toutes les tâches en attente dans la file d'attente.
     *
     * @param  string  $queue
     * @return void
     */
    protected function migrateAllExpiredJobs($queue)
    {
        $this->migrateExpiredJobs($queue.':delayed', $queue);

        $this->migrateExpiredJobs($queue.':reserved', $queue);
    }

    /**
     * Migrez les tâches retardées qui sont prêtes vers la file d'attente normale.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    public function migrateExpiredJobs($from, $to)
    {
        $options = ['cas' => true, 'watch' => $from, 'retry' => 10];

        $this->getConnection()->transaction($options, function ($transaction) use ($from, $to)
        {
            // Nous devons d’abord récupérer tous les emplois qui ont expiré en fonction de l’heure actuelle.
            // afin que nous puissions les placer dans la file d'attente principale. Après les avoir obtenus, nous
            // les supprime de ces files d'attente "délai". Tout cela dans une transaction.
            $jobs = $this->getExpiredJobs(
                $transaction, $from, $time = $this->getTime()
            );

            // Si nous trouvons réellement des emplois, nous les supprimerons de l'ancienne file d'attente et nous
            // les insérera dans la nouvelle "file d'attente" (prête). Cela signifie qu'ils resteront debout
            // prêt à être traité par le gestionnaire de file d'attente à chaque fois que son tour arrive.
            if (count($jobs) > 0) {
                $this->removeExpiredJobs($transaction, $from, $time);

                $this->pushExpiredJobsOntoNewQueue($transaction, $to, $jobs);
            }
        });
    }

    /**
     * Récupérez les travaux expirés d’une file d’attente donnée.
     *
     * @param  \Predis\Transaction\MultiExec  $transaction
     * @param  string  $from
     * @param  int  $time
     * @return array
     */
    protected function getExpiredJobs($transaction, $from, $time)
    {
        return $transaction->zrangebyscore($from, '-inf', $time);
    }

    /**
     * Supprimez les tâches expirées d'une file d'attente donnée.
     *
     * @param  \Predis\Transaction\MultiExec  $transaction
     * @param  string  $from
     * @param  int  $time
     * @return void
     */
    protected function removeExpiredJobs($transaction, $from, $time)
    {
        $transaction->multi();

        $transaction->zremrangebyscore($from, '-inf', $time);
    }

    /**
     * Poussez toutes les tâches données vers une autre file d’attente.
     *
     * @param  \Predis\Transaction\MultiExec  $transaction
     * @param  string  $to
     * @param  array  $jobs
     * @return void
     */
    protected function pushExpiredJobsOntoNewQueue($transaction, $to, $jobs)
    {
        call_user_func_array([$transaction, 'rpush'], array_merge([$to], $jobs));
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
        $payload = parent::createPayload($job, $data);

        $payload = $this->setMeta($payload, 'id', $this->getRandomId());

        return $this->setMeta($payload, 'attempts', 1);
    }

    /**
     * Obtenez une chaîne d'identification aléatoire.
     *
     * @return string
     */
    protected function getRandomId()
    {
        return str_random(32);
    }

    /**
     * Obtenez la file d'attente ou renvoyez la valeur par défaut.
     *
     * @param  string|null  $queue
     * @return string
     */
    protected function getQueue($queue)
    {
        return 'queues:'.($queue ?: $this->default);
    }

    /**
     * Obtenez la connexion pour la file d'attente.
     *
     * @return \Predis\ClientInterface
     */
    protected function getConnection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * Obtenez l'instance Redis sous-jacente.
     *
     * @return \Two\Redis\Database
     */
    public function getRedis()
    {
        return $this->redis;
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

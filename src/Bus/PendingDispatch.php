<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus;


class PendingDispatch
{
    /**
     * Le travail.
     *
     * @var mixed
     */
    protected $job;


    /**
     * Créez une nouvelle répartition de tâches en attente.
     *
     * @param  mixed  $job
     * @return void
     */
    public function __construct($job)
    {
        $this->job = $job;
    }

    /**
     * Gérez la destruction de l'objet.
     *
     * @return void
     */
    public function __destruct()
    {
        dispatch($this->job);
    }

    /**
     * Définissez la connexion souhaitée pour le travail.
     *
     * @param  string|null  $connection
     * @return $this
     */
    public function onConnection($connection)
    {
        $this->job->onConnection($connection);

        return $this;
    }

    /**
     * Définissez la file d'attente souhaitée pour le travail.
     *
     * @param  string|null  $queue
     * @return $this
     */
    public function onQueue($queue)
    {
        $this->job->onQueue($queue);

        return $this;
    }

    /**
     * Définissez le délai souhaité pour le travail.
     *
     * @param  \DateTime|int|null  $delay
     * @return $this
     */
    public function delay($delay)
    {
        $this->job->delay($delay);

        return $this;
    }
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus\Traits;


trait QueueableTrait
{
    /**
     * Le nom de la connexion à laquelle la tâche doit être envoyée.
     *
     * @var string|null
     */
    public $connection;

    /**
     * Le nom de la file d'attente vers laquelle le travail doit être envoyé.
     *
     * @var string|null
     */
    public $queue;

    /**
     * Nombre de secondes avant que le travail ne soit rendu disponible.
     *
     * @var \DateTime|int|null
     */
    public $delay;


    /**
     * Définissez la connexion souhaitée pour le travail.
     *
     * @param  string|null  $connection
     * @return $this
     */
    public function onConnection($connection)
    {
        $this->connection = $connection;

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
        $this->queue = $queue;

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
        $this->delay = $delay;

        return $this;
    }
}

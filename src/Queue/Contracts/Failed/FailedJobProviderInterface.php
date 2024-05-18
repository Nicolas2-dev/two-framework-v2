<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Contracts\Failed;


interface FailedJobProviderInterface
{

    /**
     * Enregistrez une tâche ayant échoué dans le stockage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @return void
     */
    public function log($connection, $queue, $payload);

    /**
     * Obtenez une liste de toutes les tâches ayant échoué.
     *
     * @return array
     */
    public function all();

    /**
     * Obtenez un seul travail échoué.
     *
     * @param  mixed  $id
     * @return array
     */
    public function find($id);

    /**
     * Supprimez une seule tâche ayant échoué du stockage.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function forget($id);

    /**
     * Videz toutes les tâches ayant échoué du stockage.
     *
     * @return void
     */
    public function flush();

}

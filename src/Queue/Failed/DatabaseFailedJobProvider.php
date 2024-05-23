<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Failed;

use Two\Database\Contracts\ConnectionResolverInterface;
use Two\Queue\Contracts\Failed\FailedJobProviderInterface;

use Carbon\Carbon;


class DatabaseFailedJobProvider implements FailedJobProviderInterface
{

    /**
     * L’implémentation du résolveur de connexion.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Le nom de la connexion à la base de données.
     *
     * @var string
     */
    protected $database;

    /**
     * Le tableau de la base de données.
     *
     * @var string
     */
    protected $table;

    /**
     * Créez un nouveau fournisseur de tâches de base de données ayant échoué.
     *
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $resolver
     * @param  string  $database
     * @param  string  $table
     * @return void
     */
    public function __construct(ConnectionResolverInterface $resolver, $database, $table)
    {
        $this->table = $table;

        $this->resolver = $resolver;
        $this->database = $database;
    }

    /**
     * Enregistrez une tâche ayant échoué dans le stockage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @return void
     */
    public function log($connection, $queue, $payload)
    {
        $failed_at = Carbon::now();

        $this->getTable()->insert(compact('connection', 'queue', 'payload', 'failed_at'));
    }

    /**
     * Obtenez une liste de toutes les tâches ayant échoué.
     *
     * @return array
     */
    public function all()
    {
        return $this->getTable()->orderBy('id', 'desc')->get();
    }

    /**
     * Obtenez un seul travail échoué.
     *
     * @param  mixed  $id
     * @return array
     */
    public function find($id)
    {
        return $this->getTable()->find($id);
    }

    /**
     * Supprimez une seule tâche ayant échoué du stockage.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function forget($id)
    {
        $result = $this->getTable()->where('id', $id)->delete();

        return $result > 0;
    }

    /**
     * Videz toutes les tâches ayant échoué du stockage.
     *
     * @return void
     */
    public function flush()
    {
        $this->getTable()->delete();
    }

    /**
     * Obtenez une nouvelle instance du générateur de requêtes pour la table.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->resolver->connection($this->database)->table($this->table);
    }

}

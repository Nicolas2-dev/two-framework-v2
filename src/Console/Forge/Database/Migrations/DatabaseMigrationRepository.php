<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Migrations;

use Two\Database\Contracts\ConnectionResolverInterface as Resolver;
use Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface;


class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
    /**
     * Instance du résolveur de connexion à la base de données.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Le nom de la table de migration.
     *
     * @var string
     */
    protected $table;

    /**
     * Le nom de la connexion à la base de données à utiliser.
     *
     * @var string
     */
    protected $connection;

    /**
     * Créez une nouvelle instance de référentiel de migration de base de données.
     *
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $resolver
     * @param  string  $table
     * @return void
     */
    public function __construct(Resolver $resolver, $table)
    {
        $this->table = $table;
        $this->resolver = $resolver;
    }

    /**
     * Obtenez les migrations exécutées.
     *
     * @return array
     */
    public function getRan()
    {
        return $this->table()->lists('migration');
    }

    /**
     * Obtenez le dernier lot de migration.
     *
     * @param  string|null  $group
     *
     * @return array
     */
    public function getLast($group)
    {
        $query = $this->table()
            ->where('batch', $this->getLastBatchNumber($group))
            ->where('group', $group);

        return $query->orderBy('migration', 'desc')->get();
    }

    /**
     * Enregistrez qu'une migration a été exécutée.
     *
     * @param  string  $file
     * @param  int     $batch
     * @param  string  $group
     * @return void
     */
    public function log($file, $batch, $group)
    {
        $record = array('migration' => $file, 'batch' => $batch, 'group' => $group);

        $this->table()->insert($record);
    }

    /**
     * Supprimez une migration du journal.
     *
     * @param  object  $migration
     * @return void
     */
    public function delete($migration)
    {
        $this->table()->where('migration', $migration->migration)->delete();
    }

    /**
     * Obtenez le prochain numéro de lot de migration.
     *
     * @param  string  $group
     *
     * @return int
     */
    public function getNextBatchNumber($group)
    {
        return $this->getLastBatchNumber($group) + 1;
    }

    /**
     * Obtenez le dernier numéro de lot de migration.
     *
     * @param  string  $group
     *
     * @return int
     */
    public function getLastBatchNumber($group)
    {
        $query = $this->table()->where('group', $group);

        return $query->max('batch');
    }

    /**
     * Créez le magasin de données du référentiel de migration.
     *
     * @return void
     */
    public function createRepository()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        $schema->create($this->table, function($table)
        {
            // La table des migrations est chargée de garder la trace des
            // les migrations ont effectivement été exécutées pour l'application. Nous allons créer le
            // table contenant le chemin du fichier de migration ainsi que l'ID du lot.
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->string('group');
        });
    }

    /**
     * Déterminez si le référentiel de migration existe.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        return $schema->hasTable($this->table);
    }

    /**
     * Obtenez un générateur de requêtes pour la table de migration.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function table()
    {
        return $this->getConnection()->table($this->table);
    }

    /**
     * Obtenez l’instance du résolveur de connexion.
     *
     * @return \Two\Database\Contracts\ConnectionResolverInterface
     */
    public function getConnectionResolver()
    {
        return $this->resolver;
    }

    /**
     * Résolvez l’instance de connexion à la base de données.
     *
     * @return \Two\Database\Connection
     */
    public function getConnection()
    {
        return $this->resolver->connection($this->connection);
    }

    /**
     * Définissez la source d’informations pour collecter des données.
     *
     * @param  string  $name
     * @return void
     */
    public function setSource($name)
    {
        $this->connection = $name;
    }

}

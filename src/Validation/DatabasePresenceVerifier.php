<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation;

use Two\Database\Contracts\ConnectionResolverInterface;
use Two\Database\Query\Builder as QueryBuilder;
use Two\Validation\Contracts\PresenceVerifierInterface;


class DatabasePresenceVerifier implements PresenceVerifierInterface
{
    /**
     * Implémentation du résolveur de connexion à la base de données.
     *
     * @var  \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $db;

    /**
     * La connexion à la base de données à utiliser.
     *
     * @var string
     */
    protected $connection = null;


    /**
     * Créez un nouveau vérificateur de présence de base de données.
     *
     * @return void
     */
    public function __construct(ConnectionResolverInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Comptez le nombre d'objets dans une collection ayant la valeur donnée.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  string  $value
     * @param  int     $excludeId
     * @param  string  $idColumn
     * @param  array   $extra
     * @return int
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = array())
    {
        $query = $this->table($collection)->where($column, '=', $value);

        if (! is_null($excludeId) && ($excludeId != 'NULL')) {
            $query->where($idColumn ?: 'id', '<>', $excludeId);
        }

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($query, $key, $extraValue);
        }

        return $query->count();
    }

    /**
     * Comptez le nombre d'objets dans une collection avec les valeurs données.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  array   $values
     * @param  array   $extra
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = array())
    {
        $query = $this->table($collection)->whereIn($column, $values);

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($query, $key, $extraValue);
        }

        return $query->count();
    }

    /**
     * Ajoutez une clause "WHERE" à la requête donnée.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  string  $key
     * @param  string  $extraValue
     * @return void
     */
    protected function addWhere(QueryBuilder $query, $key, $extraValue)
    {
        if ($extraValue === 'NULL') {
            $query->whereNull($key);
        } elseif ($extraValue === 'NOT_NULL') {
            $query->whereNotNull($key);
        } else {
            $query->where($key, $extraValue);
        }
    }

    /**
     * Obtenez une instance QueryBuilder pour la table de base de données donnée.
     *
     * @param  string  $table
     * @return \Two\Database\Query\Builder
     */
    protected function table($table)
    {
        $connection = $this->db->connection($this->connection);

        return $connection->table($table);
    }

    /**
     * Définissez la connexion à utiliser.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}

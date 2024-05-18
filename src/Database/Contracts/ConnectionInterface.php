<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Contracts;

use Closure;


interface ConnectionInterface
{
    /**
     * Commencez une requête fluide sur une table de base de données.
     *
     * @param  string  $table
     * @return \Two\Database\Query\Builder
     */
    public function table($table);

    /**
     * Obtenez une nouvelle expression de requête brute.
     *
     * @param  mixed  $value
     * @return \Two\Database\Query\Expression
     */
    public function raw($value);

    /**
     * Exécutez une instruction select et renvoyez un seul résultat.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = array());

    /**
     * Exécutez une instruction select sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, $bindings = array());

    /**
     * Exécutez une instruction insert sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, $bindings = array());

    /**
     * Exécutez une instruction de mise à jour sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, $bindings = array());

    /**
     * Exécutez une instruction delete sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = array());

    /**
     * Exécutez une instruction SQL et renvoyez le résultat booléen.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = array());

    /**
     * Exécutez une instruction SQL et obtenez le nombre de lignes affectées.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = array());

    /**
     * Exécutez une requête brute et non préparée sur la connexion PDO.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query);

    /**
     * Préparez les liaisons de requête pour l’exécution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings);

    /**
     * Exécuter une clôture dans une transaction.
     *
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction(Closure $callback);

    /**
     * Démarrez une nouvelle transaction de base de données.
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Validez la transaction de base de données active.
     *
     * @return void
     */
    public function commit();

    /**
     * Annulez la transaction de base de données active.
     *
     * @return void
     */
    public function rollBack();

    /**
     * Obtenez le nombre de transactions actives.
     *
     * @return int
     */
    public function transactionLevel();

    /**
     * Exécutez le rappel donné en mode « exécution à sec ».
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback);

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation\Contracts;


interface PresenceVerifierInterface
{
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
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = array());

    /**
     * Comptez le nombre d'objets dans une collection avec les valeurs données.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  array   $values
     * @param  array   $extra
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = array());

    /**
     * Définissez la connexion à utiliser.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection);

}

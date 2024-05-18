<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query\Processors;

use Two\Database\Query\Builder;
use Two\Database\Query\Processor;


class PostgresProcessor extends Processor
{
    /**
     * Traitez une requête « insérer obtenir un ID ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $results = $query->getConnection()->selectFromWriteConnection($sql, $values);

        $sequence = $sequence ?: 'id';

        $result = (array) $results[0];

        $id = $result[$sequence];

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Traitez les résultats d’une requête de liste de colonnes.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_values(array_map(function ($result)
        {
            $result = (object) $result;

            return $result->column_name;

        }, $results));
    }
}

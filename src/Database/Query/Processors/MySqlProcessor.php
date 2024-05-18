<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query\Processors;

use Two\Database\Query\Processor;


class MySqlProcessor extends Processor
{
    /**
     * Traitez les résultats d’une requête de liste de colonnes.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result)
        {
            $result = (object) $result;

            return $result->column_name;

        }, $results);
    }
}

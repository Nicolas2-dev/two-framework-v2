<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema;


class MySqlBuilder extends Builder
{
    /**
     * Déterminez si la table donnée existe.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        $sql = $this->grammar->compileTableExists();

        $database = $this->connection->getDatabaseName();

        $table = $this->connection->getTablePrefix().$table;

        return count($this->connection->select($sql, array($database, $table))) > 0;
    }

    /**
     * Obtenez la liste des colonnes pour une table donnée.
     *
     * @param  string  $table
     * @return array
     */
    public function getColumnListing($table)
    {
        $sql = $this->grammar->compileColumnExists();

        $database = $this->connection->getDatabaseName();

        $table = $this->connection->getTablePrefix().$table;

        $results = $this->connection->select($sql, array($database, $table));

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

}

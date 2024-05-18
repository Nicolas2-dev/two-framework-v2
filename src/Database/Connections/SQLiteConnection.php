<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Connections;

use Two\Database\Connection;
use Two\Database\Query\Grammars\SQLiteGrammar as QueryGrammar;
use Two\Database\Query\Processors\SQLiteProcessor as QueryProcessor;
use Two\Database\Schema\Grammars\SQLiteGrammar as SchemaGrammar;

use Doctrine\DBAL\Driver\PDOSqlite\Driver as DoctrineDriver;


class SQLiteConnection extends Connection
{

    /**
     * Obtenez l'instance de grammaire de requête par défaut.
     *
     * @return \Two\Database\Query\Grammars\SQLiteGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Obtenez l'instance de grammaire de schéma par défaut.
     *
     * @return \Two\Database\Schema\Grammars\SQLiteGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Obtenez l'instance de post-processeur par défaut.
     *
     * @return \Two\Database\Query\Processors\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor();
    }

    /**
     * Obtenez le pilote Doctrine DBAL.
     *
     * @return \Doctrine\DBAL\Driver\PDOSqlite\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}

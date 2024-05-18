<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Connections;

use Two\Database\Connection;
use Two\Database\Schema\MySqlBuilder;
use Two\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Two\Database\Query\Processors\MySqlProcessor as QueryProcessor;
use Two\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;

use Doctrine\DBAL\Driver\PDOMySql\Driver as DoctrineDriver;


class MySqlConnection extends Connection
{

    /**
     * Obtenez une instance de générateur de schéma pour la connexion.
     *
     * @return \Two\Database\Schema\MySqlBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new MySqlBuilder($this);
    }

    /**
     * Obtenez l'instance de grammaire de requête par défaut.
     *
     * @return \Two\Database\Query\Grammars\MySqlGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Obtenez l'instance de grammaire de schéma par défaut.
     *
     * @return \Two\Database\Schema\Grammars\MySqlGrammar
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
        return new QueryProcessor;
    }

    /**
     * Obtenez le pilote Doctrine DBAL.
     *
     * @return \Doctrine\DBAL\Driver\PDOMySql\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}

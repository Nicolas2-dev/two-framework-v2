<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Connections;

use Closure;

use Two\Database\Connection;
use Two\Database\Query\Grammars\SqlServerGrammar as QueryGrammar;
use Two\Database\Query\Processors\SqlServerProcessor as QueryProcessor;
use Two\Database\Schema\Grammars\SqlServerGrammar as SchemaGrammar;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as DoctrineDriver;


class SqlServerConnection extends Connection
{

    /**
     * Exécuter une clôture dans une transaction.
     *
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction(Closure $callback)
    {
        if ($this->getDriverName() == 'sqlsrv') {
            return parent::transaction($callback);
        }

        $this->pdo->exec('BEGIN TRAN');

        try {
            $result = $callback($this);

            $this->pdo->exec('COMMIT TRAN');
        }
        catch (\Exception $e) {
            $this->pdo->exec('ROLLBACK TRAN');

           throw $e;
        }
        catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK TRAN');

            throw $e;
        }

        return $result;
    }

    /**
     * Obtenez l'instance de grammaire de requête par défaut.
     *
     * @return \Two\Database\Query\Grammars\SqlServerGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Obtenez l'instance de grammaire de schéma par défaut.
     *
     * @return \Two\Database\Schema\Grammars\SqlServerGrammar
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
     * @return \Doctrine\DBAL\Driver\PDOSqlsrv\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}

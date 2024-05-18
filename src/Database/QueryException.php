<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use PDOException;


class QueryException extends PDOException
{
    /**
     * Le SQL pour la requête.
     *
     * @var string
     */
    protected $sql;

    /**
     * Les liaisons pour la requête.
     *
     * @var array
     */
    protected $bindings;

    /**
     * 
     */
    protected $previous;

    /**
     * Créez une nouvelle instance d'exception de requête.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  \Exception $previous
     * @return void
     */
    public function __construct($sql, array $bindings, $previous)
    {
        $this->sql      = $sql;
        $this->bindings = $bindings;
        $this->previous = $previous;
        $this->code     = $previous->getCode();
        $this->message  = $this->formatMessage($sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Formatez le message d'erreur SQL.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  \Exception $previous
     * @return string
     */
    protected function formatMessage($sql, $bindings, $previous)
    {
        return $previous->getMessage() .' (SQL: '.str_replace_array('\?', $bindings, $sql).')';
    }

    /**
     * Obtenez le SQL pour la requête.
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Obtenez les liaisons pour la requête.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

}

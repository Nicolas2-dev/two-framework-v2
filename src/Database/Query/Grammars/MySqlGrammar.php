<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */

namespace Two\Database\Query\Grammars;

use Two\Database\Query\Builder;
use Two\Database\Query\Grammar;


class MySqlGrammar extends Grammar
{
    /**
     * Les composants qui composent une clause select.
     *
     * @var array
     */
    protected $selectComponents = array(
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock'
    );


    /**
     * Compilez une requête de sélection en SQL.
     *
     * @param  \Two\Database\Query\Builder
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $sql = parent::compileSelect($query);

        if ($query->unions) {
            $sql = '(' .$sql .') ' .$this->compileUnions($query);
        }

        return $sql;
    }

    /**
     * Compilez une seule déclaration syndicale.
     *
     * @param  array  $union
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $joiner = $union['all'] ? ' union all ' : ' union ';

        return $joiner .'(' .$union['query']->toSql() .')';
    }

    /**
     * Compilez le verrou en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) return $value;

        return $value ? 'for update' : 'lock in share mode';
    }

    /**
     * Compilez une instruction de mise à jour en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, $values)
    {
        $sql = parent::compileUpdate($query, $values);

        if (isset($query->orders)) {
            $sql .= ' ' .$this->compileOrders($query, $query->orders);
        }

        if (isset($query->limit)) {
            $sql .= ' ' .$this->compileLimit($query, $query->limit);
        }

        return rtrim($sql);
    }

    /**
     * Compilez une instruction de suppression en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        if (isset($query->joins)) {
            $joins = ' ' .$this->compileJoins($query, $query->joins);

            return trim("delete $table from {$table}{$joins} $where");
        }

        return trim("delete from $table $where");
    }

    /**
     * Enveloppez une seule chaîne dans des identifiants de mots clés.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') return $value;

        return '`'.str_replace('`', '``', $value).'`';
    }

}

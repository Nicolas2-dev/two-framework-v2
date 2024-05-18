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


class PostgresGrammar extends Grammar
{
    /**
     * Tous les opérateurs de clause disponibles.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '#', '<<', '>>',
    );


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

        return $value ? 'for update' : 'for share';
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
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($values);

        $from = $this->compileUpdateFrom($query);

        $where = $this->compileUpdateWheres($query);

        return trim("update {$table} set {$columns}{$from} $where");
    }

    /**
     * Compilez les colonnes pour l'instruction de mise à jour.
     *
     * @param  array   $values
     * @return string
     */
    protected function compileUpdateColumns($values)
    {
        $columns = array();

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key).' = '.$this->parameter($value);
        }

        return implode(', ', $columns);
    }

    /**
     * Compilez la clause "from" pour une mise à jour avec une jointure.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUpdateFrom(Builder $query)
    {
        if (! isset($query->joins)) return '';

        $froms = array();

        foreach ($query->joins as $join) {
            $froms[] = $this->wrapTable($join->table);
        }

        if (count($froms) > 0) return ' from '.implode(', ', $froms);
    }

    /**
     * Compilez les clauses Where supplémentaires pour les mises à jour avec des jointures.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUpdateWheres(Builder $query)
    {
        $baseWhere = $this->compileWheres($query);

        if (! isset($query->joins)) return $baseWhere;

        $joinWhere = $this->compileUpdateJoinWheres($query);

        if (trim($baseWhere) == '') {
            return 'where '.$this->removeLeadingBoolean($joinWhere);
        }

        return $baseWhere .' ' .$joinWhere;
    }

    /**
     * Compilez les clauses "join" pour une mise à jour.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUpdateJoinWheres(Builder $query)
    {
        $joinWheres = array();

        foreach ($query->joins as $join) {
            foreach ($join->clauses as $clause) {
                $joinWheres[] = $this->compileJoinConstraint($clause);
            }
        }

        return implode(' ', $joinWheres);
    }

    /**
     * Compilez une instruction insert et get ID dans SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array   $values
     * @param  string  $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        if (is_null($sequence)) $sequence = 'id';

        return $this->compileInsert($query, $values).' returning '.$this->wrap($sequence);
    }

    /**
     * Compilez une instruction de table tronquée en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return array('truncate ' .$this->wrapTable($query->from) .' restart identity' => array());
    }
}


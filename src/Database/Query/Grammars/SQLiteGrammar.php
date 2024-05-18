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


class SQLiteGrammar extends Grammar
{
    /**
     * Tous les opérateurs de clause disponibles.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '<<', '>>',
    );

    /**
     * Compilez une instruction d'insertion dans SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = array($values);
        }

        if (count($values) == 1) {
            return parent::compileInsert($query, reset($values));
        }

        $names = $this->columnize(array_keys(reset($values)));

        $columns = array();

        foreach (array_keys(reset($values)) as $column) {
            $columns[] = '? as '.$this->wrap($column);
        }

        $columns = array_fill(0, count($values), implode(', ', $columns));

        return "insert into $table ($names) select ".implode(' union select ', $columns);
    }

    /**
     * Compilez une instruction de table tronquée en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        $sql = array(
            'delete from sqlite_sequence where name = ?'  => array($query->from),
            'delete from ' .$this->wrapTable($query->from) => array()
        );

        return $sql;
    }

    /**
     * Compilez une clause « où jour ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('%d', $query, $where);
    }

    /**
     * Compilez une clause « où mois ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('%m', $query, $where);
    }

    /**
     * Compilez une clause « où année ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('%Y', $query, $where);
    }

    /**
     * Compilez une date basée sur la clause Where.
     *
     * @param  string  $type
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = str_pad($where['value'], 2, '0', STR_PAD_LEFT);

        $value = $this->parameter($value);

        return 'strftime(\'' .$type .'\', ' .$this->wrap($where['column']).') ' .$where['operator'] .' ' .$value;
    }
}

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


class SqlServerGrammar extends Grammar
{
    /**
     * Tous les opérateurs de clause disponibles.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '&=', '|', '|=', '^', '^=',
    );

    /**
     * Compilez une requête de sélection en SQL.
     *
     * @param  \Two\Database\Query\Builder
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $components = $this->compileComponents($query);

        if ($query->offset > 0) {
            return $this->compileAnsiOffset($query, $components);
        }

        return $this->concatenate($components);
    }

    /**
     * Compilez la partie "select *" de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if (! is_null($query->aggregate)) return;

        $select = $query->distinct ? 'select distinct ' : 'select ';

        if (($query->limit > 0) && ($query->offset <= 0)) {
            $select .= 'top '.$query->limit.' ';
        }

        return $select.$this->columnize($columns);
    }

    /**
     * Compilez la partie « de » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        $from = parent::compileFrom($query, $table);

        if (is_string($query->lock)) return $from.' '.$query->lock;

        if (! is_null($query->lock)) {
            return $from.' with(rowlock,'.($query->lock ? 'updlock,' : '').'holdlock)';
        }

        return $from;
    }

    /**
     * Créez une clause de décalage ANSI complète pour la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $components
     * @return string
     */
    protected function compileAnsiOffset(Builder $query, $components)
    {
        if (! isset($components['orders'])) {
            $components['orders'] = 'order by (select 0)';
        }

        $orderings = $components['orders'];

        $components['columns'] .= $this->compileOver($orderings);

        unset($components['orders']);

        $constraint = $this->compileRowConstraint($query);

        $sql = $this->concatenate($components);

        return $this->compileTableExpression($sql, $constraint);
    }

    /**
     * Compilez l'instruction over pour une expression de table.
     *
     * @param  string  $orderings
     * @return string
     */
    protected function compileOver($orderings)
    {
        return ", row_number() over ({$orderings}) as row_num";
    }

    /**
     * Compilez la contrainte de ligne limite/décalage pour une requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileRowConstraint($query)
    {
        $start = $query->offset + 1;

        if ($query->limit > 0)
        {
            $finish = $query->offset + $query->limit;

            return "between {$start} and {$finish}";
        }

        return ">= {$start}";
    }

    /**
     * Compilez une expression de table commune pour une requête.
     *
     * @param  string  $sql
     * @param  string  $constraint
     * @return string
     */
    protected function compileTableExpression($sql, $constraint)
    {
        return "select * from ({$sql}) as temp_table where row_num {$constraint}";
    }

    /**
     * Compilez les parties « limite » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return '';
    }

    /**
     * Compilez les parties « décalage » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return '';
    }

    /**
     * Compilez une instruction de table tronquée en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return array('truncate table ' .$this->wrapTable($query->from) => array());
    }

    /**
     * Obtenez le format des dates stockées dans la base de données.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.000';
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

        return '[' .str_replace(']', ']]', $value).']';
    }

}

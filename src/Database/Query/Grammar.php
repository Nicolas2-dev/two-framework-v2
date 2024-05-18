<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query;

use Two\Database\Query\Builder;
use Two\Database\Grammar as BaseGrammar;


class Grammar extends BaseGrammar
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
        'unions',
        'lock',
    );

    /**
     * Compilez une requête de sélection en SQL.
     *
     * @param  \Two\Database\Query\Builder
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (is_null($query->columns)) $query->columns = array('*');

        return trim($this->concatenate($this->compileComponents($query)));
    }

    /**
     * Compilez les composants nécessaires à une clause select.
     *
     * @param  \Two\Database\Query\Builder
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = array();

        foreach ($this->selectComponents as $component) {
            if (! is_null($query->$component)) {
                $method = 'compile'.ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compilez une clause de sélection agrégée.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        if ($query->distinct && ($column !== '*')) {
            $column = 'distinct '.$column;
        }

        return 'select '.$aggregate['function'].'('.$column.') as aggregate';
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
        return 'from '.$this->wrapTable($table);
    }

    /**
     * Compilez les parties « rejoindre » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        $sql = array();

        $query->setBindings(array(), 'join');

        foreach ($joins as $join) {
            $table = $this->wrapTable($join->table);

            $clauses = array();

            foreach ($join->clauses as $clause) {
                $clauses[] = $this->compileJoinConstraint($clause);
            }

            foreach ($join->bindings as $binding) {
                $query->addBinding($binding, 'join');
            }

            $clauses[0] = $this->removeLeadingBoolean($clauses[0]);

            $clauses = implode(' ', $clauses);

            $type = $join->type;

            //
            $sql[] = "$type join $table on $clauses";
        }

        return implode(' ', $sql);
    }

    /**
     * Créez un segment de contrainte de clause de jointure.
     *
     * @param  array   $clause
     * @return string
     */
    protected function compileJoinConstraint(array $clause)
    {
        $first = $this->wrap($clause['first']);

        $second = $clause['where'] ? '?' : $this->wrap($clause['second']);

        return "{$clause['boolean']} $first {$clause['operator']} $second";
    }

    /**
     * Compilez les parties « où » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileWheres(Builder $query)
    {
        $sql = array();

        if (is_null($query->wheres)) return '';

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";

            $sql[] = $where['boolean'].' '.$this->$method($query, $where);
        }

        if (count($sql) > 0) {
            $sql = implode(' ', $sql);

            return 'where '.preg_replace('/and |or /', '', $sql, 1);
        }

        return '';
    }

    /**
     * Compilez une clause Where imbriquée.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNested(Builder $query, $where)
    {
        $nested = $where['query'];

        return '('.substr($this->compileWheres($nested), 6).')';
    }

    /**
     * Compilez une condition Where avec une sous-sélection.
     *
     * @param  \Two\Database\Query\Builder $query
     * @param  array   $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']).' '.$where['operator']." ($select)";
    }

    /**
     * Compilez une clause Where de base.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Compilez une clause Where « entre ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBetween(Builder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        return $this->wrap($where['column']).' '.$between.' ? and ?';
    }

    /**
     * Compilez une clause où existe.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereExists(Builder $query, $where)
    {
        return 'exists ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compilez une clause où existe.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotExists(Builder $query, $where)
    {
        return 'not exists ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compilez une clause « où dans ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereIn(Builder $query, $where)
    {
        if (empty($where['values'])) return '0 = 1';

        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']).' in ('.$values.')';
    }

    /**
     * Compilez une clause « où pas dans ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotIn(Builder $query, $where)
    {
        if (empty($where['values'])) return '1 = 1';

        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']).' not in ('.$values.')';
    }

    /**
     * Compilez une clause de sous-sélection Where In.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereInSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']).' in ('.$select.')';
    }

    /**
     * Compilez une clause où pas dans la sous-sélection.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotInSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']).' not in ('.$select.')';
    }

    /**
     * Compilez une clause "where null".
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        return $this->wrap($where['column']).' is null';
    }

    /**
     * Compilez une clause "where not null".
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return $this->wrap($where['column']).' is not null';
    }

    /**
     * Compilez une clause « où date ».
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('date', $query, $where);
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
        return $this->dateBasedWhere('day', $query, $where);
    }

    /**
     * Compilez une clause "où mois".
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('month', $query, $where);
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
        return $this->dateBasedWhere('year', $query, $where);
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
        $value = $this->parameter($where['value']);

        return $type.'('.$this->wrap($where['column']).') '.$where['operator'].' '.$value;
    }

    /**
     * Compilez une clause Where brute.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * Compilez les parties « regrouper par » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by '.$this->columnize($groups);
    }

    /**
     * Compilez les parties « avoir » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map(array($this, 'compileHaving'), $havings));

        return 'having '.preg_replace('/and |or /', '', $sql, 1);
    }

    /**
     * Compilez une seule clause have.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileHaving(array $having)
    {
        if ($having['type'] === 'raw') {
            return $having['boolean'].' '.$having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compilez une clause have de base.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }

    /**
     * Compilez les parties « trier par » de la requête.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        return 'order by '.implode(', ', array_map(function($order)
        {
            if (isset($order['sql'])) return $order['sql'];

            return $this->wrap($order['column']).' '.$order['direction'];
        }
        , $orders));
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
        return 'limit '.(int) $limit;
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
        return 'offset '.(int) $offset;
    }

    /**
     * Compilez les requêtes "union" attachées à la requête principale.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (isset($query->unionOrders)) {
            $sql .= ' '.$this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' '.$this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' '.$this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
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

        return $joiner.$union['query']->toSql();
    }

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

        $columns = $this->columnize(array_keys(reset($values)));

        $parameters = $this->parameterize(reset($values));

        $value = array_fill(0, count($values), "($parameters)");

        $parameters = implode(', ', $value);

        return "insert into $table ($columns) values $parameters";
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
        return $this->compileInsert($query, $values);
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

        $columns = array();

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key).' = '.$this->parameter($value);
        }

        $columns = implode(', ', $columns);

        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
        } else {
            $joins = '';
        }

        $where = $this->compileWheres($query);

        return trim("update {$table}{$joins} set $columns $where");
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

        return trim("delete from $table ".$where);
    }

    /**
     * Compilez une instruction de table tronquée en SQL.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return array('truncate '.$this->wrapTable($query->from) => array());
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
        return is_string($value) ? $value : '';
    }

    /**
     * Concaténez un tableau de segments, en supprimant les vides.
     *
     * @param  array   $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function($value)
        {
            return (string) $value !== '';
        }));
    }

    /**
     * Supprimez le premier booléen d’une instruction.
     *
     * @param  string  $value
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /', '', $value, 1);
    }

}

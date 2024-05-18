<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query;


class JoinClause
{
    /**
     * Type de jointure en cours d'exécution.
     *
     * @var string
     */
    public $type;

    /**
     * Table à laquelle la clause de jointure se joint.
     *
     * @var string
     */
    public $table;

    /**
     * Les clauses "on" pour la jointure.
     *
     * @var array
     */
    public $clauses = array();

    /**
    * Les liaisons « on » pour la jointure.
    *
    * @var array
    */
    public $bindings = array();

    /**
     * Créez une nouvelle instance de clause de jointure.
     *
     * @param  string  $type
     * @param  string  $table
     * @return void
     */
    public function __construct($type, $table)
    {
        $this->type = $type;
        $this->table = $table;
    }

    /**
     * Ajoutez une clause "on" à la jointure.
     *
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @param  string  $boolean
     * @param  bool  $where
     * @return $this
     */
    public function on($first, $operator, $second, $boolean = 'and', $where = false)
    {
        $this->clauses[] = compact('first', 'operator', 'second', 'boolean', 'where');

        if ($where) $this->bindings[] = $second;

        return $this;
    }

    /**
     * Ajoutez une clause "ou sur" à la jointure.
     *
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return \Two\Database\Query\JoinClause
     */
    public function orOn($first, $operator, $second)
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * Ajoutez une clause « sur où » à la jointure.
     *
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @param  string  $boolean
     * @return \Two\Database\Query\JoinClause
     */
    public function where($first, $operator, $second, $boolean = 'and')
    {
        return $this->on($first, $operator, $second, $boolean, true);
    }

    /**
     * Ajoutez une clause "ou sur où" à la jointure.
     *
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return \Two\Database\Query\JoinClause
     */
    public function orWhere($first, $operator, $second)
    {
        return $this->on($first, $operator, $second, 'or', true);
    }

    /**
     * Ajouter une clause "onwhere is null" à la jointure
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return \Two\Database\Query\JoinClause
     */
    public function whereNull($column, $boolean = 'and')
    {
        return $this->on($column, 'is', new Expression('null'), $boolean, false);
    }

}

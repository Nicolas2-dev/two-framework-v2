<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query;

use Closure;
use BadMethodCallException;
use InvalidArgumentException;

use Two\Support\Str;
use Two\Collection\Collection;
use Two\Pagination\Paginator;
use Two\Database\Query\Grammar;
use Two\Database\Query\Processor;
use Two\Database\Query\Expression;
use Two\Database\Query\JoinClause;
use Two\Pagination\SimplePaginator;
use Two\Database\Contracts\ConnectionInterface;


class Builder
{
    /**
     * L'instance de connexion à la base de données.
     *
     * @var \Two\Database\Connection
     */
    protected $connection;

    /**
     * Instance de grammaire de requête de base de données.
     *
     * @var \Two\Database\Query\Grammar
     */
    protected $grammar;

    /**
     * Instance de post-processeur de requête de base de données.
     *
     * @var \Two\Database\Query\Processor
     */
    protected $processor;

    /**
     * Liaisons de valeurs de requête actuelles.
     *
     * @var array
     */
    protected $bindings = array(
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
    );

    /**
     * Une fonction d'agrégation et une colonne à exécuter.
     *
     * @var array
     */
    public $aggregate;

    /**
     * Les colonnes qui doivent être renvoyées.
     *
     * @var array
     */
    public $columns;

    /**
     * Indique si la requête renvoie des résultats distincts.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * La table ciblée par la requête.
     *
     * @var string
     */
    public $from;

    /**
     * La table se joint pour la requête.
     *
     * @var array
     */
    public $joins;

    /**
     * Les contraintes Where pour la requête.
     *
     * @var array
     */
    public $wheres;

    /**
     * Les regroupements pour la requête.
     *
     * @var array
     */
    public $groups;

    /**
     * Les contraintes d'avoir pour la requête.
     *
     * @var array
     */
    public $havings;

    /**
     * Les commandes pour la requête.
     *
     * @var array
     */
    public $orders;

    /**
     * Le nombre maximum d'enregistrements à renvoyer.
     *
     * @var int
     */
    public $limit;

    /**
     * Le nombre d'enregistrements à ignorer.
     *
     * @var int
     */
    public $offset;

    /**
     * Les instructions d'union de requête.
     *
     * @var array
     */
    public $unions;

    /**
     * Nombre maximum d'enregistrements d'union à renvoyer.
     *
     * @var int
     */
    public $unionLimit;

    /**
     * Le nombre d'enregistrements syndicaux à ignorer.
     *
     * @var int
     */
    public $unionOffset;

    /**
     * Les commandes pour la requête union.
     *
     * @var array
     */
    public $unionOrders;

    /**
     * Indique si le verrouillage des lignes est utilisé.
     *
     * @var string|bool
     */
    public $lock;

    /**
     * Les sauvegardes de champs lors d'un décompte de pagination.
     *
     * @var array
     */
    protected $backups = array();

    /**
     * Clé à utiliser lors de la mise en cache de la requête.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Le nombre de minutes pour mettre en cache la requête.
     *
     * @var int
     */
    protected $cacheMinutes;

    /**
     * Les balises pour le cache de requêtes.
     *
     * @var array
     */
    protected $cacheTags;

    /**
     * Le pilote de cache à utiliser.
     *
     * @var string
     */
    protected $cacheDriver;

    /**
     * Tous les opérateurs de clause disponibles.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
    );


    /**
     * Créez une nouvelle instance du générateur de requêtes.
     *
     * @param  \Two\Database\Contracts\ConnectionInterface  $connection
     * @param  \Two\Database\Query\Grammar  $grammar
     * @param  \Two\Database\Query\Processor  $processor
     * @return void
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar, Processor $processor)
    {
        $this->grammar    = $grammar;
        $this->processor  = $processor;
        $this->connection = $connection;
    }

    /**
     * Définissez les colonnes à sélectionner.
     *
     * @param  array  $columns
     * @return $this
     */
    public function select($columns = array('*'))
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Ajoutez une nouvelle expression de sélection « brute » à la requête.
     *
     * @param  string  $expression
     * @param  array   $bindings
     * @return \Two\Database\Query\Builder|static
     */
    public function selectRaw($expression, array $bindings = array())
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Ajoutez une expression de sous-sélection à la requête.
     *
     * @param  \Closure|\Two\Database\Query\Builder|string $query
     * @param  string  $as
     * @return \Two\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function selectSub($query, $as)
    {
        if ($query instanceof Closure) {
            $callback = $query;

            $callback($query = $this->newQuery());
        }

        if ($query instanceof self) {
            $bindings = $query->getBindings();

            $query = $query->toSql();
        } else if (is_string($query)) {
            $bindings = array();
        } else {
            throw new InvalidArgumentException();
        }

        return $this->selectRaw('('. $query .') as '.$this->grammar->wrap($as), $bindings);
    }

    /**
     * Ajoutez une nouvelle colonne de sélection à la requête.
     *
     * @param  mixed  $column
     * @return $this
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    /**
     * Force la requête à renvoyer uniquement des résultats distincts.
     *
     * @return $this
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Définissez la table ciblée par la requête.
     *
     * @param  string  $table
     * @return $this
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Ajoutez une clause de jointure à la requête.
     *
     * @param  string  $table
     * @param  string  $one
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @param  bool    $where
     * @return $this
     */
    public function join($table, $one, $operator = null, $two = null, $type = 'inner', $where = false)
    {
        if ($one instanceof Closure) {
            $this->joins[] = new JoinClause($type, $table);

            call_user_func($one, end($this->joins));
        } else {
            $join = new JoinClause($type, $table);

            $this->joins[] = $join->on(
                $one, $operator, $two, 'and', $where
            );
        }

        return $this;
    }

    /**
     * Ajoutez une clause "joindre où" à la requête.
     *
     * @param  string  $table
     * @param  string  $one
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @return \Two\Database\Query\Builder|static
     */
    public function joinWhere($table, $one, $operator, $two, $type = 'inner')
    {
        return $this->join($table, $one, $operator, $two, $type, true);
    }

    /**
     * Ajoutez une jointure gauche à la requête.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return \Two\Database\Query\Builder|static
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Ajoutez une clause « joinwhere » à la requête.
     *
     * @param  string  $table
     * @param  string  $one
     * @param  string  $operator
     * @param  string  $two
     * @return \Two\Database\Query\Builder|static
     */
    public function leftJoinWhere($table, $one, $operator, $two)
    {
        return $this->joinWhere($table, $one, $operator, $two, 'left');
    }

    /**
     * Ajoutez une jointure à droite à la requête.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return \Two\Database\Query\Builder|static
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Ajoutez une clause « right joinwhere » à la requête.
     *
     * @param  string  $table
     * @param  string  $one
     * @param  string  $operator
     * @param  string  $two
     * @return \Two\Database\Query\Builder|static
     */
    public function rightJoinWhere($table, $one, $operator, $two)
    {
        return $this->joinWhere($table, $one, $operator, $two, 'right');
    }

    /**
     * Ajoutez une clause Where de base à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            return $this->whereNested(function($query) use ($column)
            {
                foreach ($column as $key => $value) {
                    $query->where($key, '=', $value);
                }
            }, $boolean);
        }

        if (func_num_args() == 2) {
            list($value, $operator) = array($operator, '=');
        } else if ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException("Value must be provided.");
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (! in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = array($operator, '=');
        }

        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '=');
        }

        //
        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * Ajoutez une clause « ou où » à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Déterminez si la combinaison d’opérateur et de valeur donnée est légale.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return ($isOperator && $operator != '=' && is_null($value));
    }

    /**
     * Ajoutez une clause Where brute à la requête.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @param  string  $boolean
     * @return $this
     */
    public function whereRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'raw';

        $this->wheres[] = compact('type', 'sql', 'boolean');

        $this->addBinding($bindings, 'where');

        return $this;
    }

    /**
     * Ajoutez une clause brute ou Where à la requête.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereRaw($sql, array $bindings = array())
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * Ajoutez une instruction Where Between à la requête.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'not');

        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * Ajoutez une instruction ou où entre à la requête.
     *
     * @param  string  $column
     * @param  array   $values
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Ajoutez une instruction Where Not Between à la requête.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Ajoutez une instruction ou où pas entre à la requête.
     *
     * @param  string  $column
     * @param  array   $values
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Ajoutez une instruction Where imbriquée à la requête.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        $query = $this->newQuery();

        $query->from($this->from);

        call_user_func($callback, $query);

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Ajoutez un autre générateur de requêtes en tant qu'emplacement imbriqué au générateur de requêtes.
     *
     * @param  \Two\Database\Query\Builder|static $query
     * @param  string  $boolean
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->mergeBindings($query);
        }

        return $this;
    }

    /**
     * Ajoutez une sous-sélection complète à la requête.
     *
     * @param  string   $column
     * @param  string   $operator
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return $this
     */
    protected function whereSub($column, $operator, Closure $callback, $boolean)
    {
        $type = 'Sub';

        $query = $this->newQuery();

        //
        call_user_func($callback, $query);

        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    /**
     * Ajoutez une clause existe à la requête.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @param  bool     $not
     * @return $this
     */
    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotExists' : 'Exists';

        $query = $this->newQuery();

        //
        call_user_func($callback, $query);

        $this->wheres[] = compact('type', 'operator', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    /**
     * Ajoutez une clause ou existe à la requête.
     *
     * @param  \Closure $callback
     * @param  bool     $not
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereExists(Closure $callback, $not = false)
    {
        return $this->whereExists($callback, 'or', $not);
    }

    /**
     * Ajoutez une clause Where Not Existe à la requête.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereNotExists(Closure $callback, $boolean = 'and')
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Ajoutez une clause Where Not Existe à la requête.
     *
     * @param  \Closure  $callback
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereNotExists(Closure $callback)
    {
        return $this->orWhereExists($callback, true);
    }

    /**
     * Ajoutez une clause « où dans » à la requête.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * Ajoutez une clause « ou où dans » à la requête.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Ajoutez une clause "where not in" à la requête.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Ajoutez une clause « ou où pas dans » à la requête.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Ajoutez un où avec une sous-sélection à la requête.
     *
     * @param  string   $column
     * @param  \Closure $callback
     * @param  string   $boolean
     * @param  bool     $not
     * @return $this
     */
    protected function whereInSub($column, Closure $callback, $boolean, $not)
    {
        $type = $not ? 'NotInSub' : 'InSub';

        //
        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    /**
     * Ajoutez une clause "where null" à la requête.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Ajoutez une clause « ou où null » à la requête.
     *
     * @param  string  $column
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Ajoutez une clause "where not null" à la requête.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Ajoutez une clause « ou où non nul » à la requête.
     *
     * @param  string  $column
     * @return \Two\Database\Query\Builder|static
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Ajoutez une instruction "where date" à la requête.
     *
     * @param  string  $column
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereDate($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
    }

    /**
     * Ajoutez une instruction "where day" à la requête.
     *
     * @param  string  $column
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereDay($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
    }

    /**
     * Ajoutez une instruction « où mois » à la requête.
     *
     * @param  string  $column
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereMonth($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
    }

    /**
     * Ajoutez une instruction « où année » à la requête.
     *
     * @param  string  $column
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
     * @return \Two\Database\Query\Builder|static
     */
    public function whereYear($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
    }

    /**
     * Ajoutez une instruction basée sur la date (année, mois, jour) à la requête.
     *
     * @param  string  $type
     * @param  string  $column
     * @param  string  $operator
     * @param  int  $value
     * @param  string  $boolean
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        $this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');

        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Gère les clauses dynamiques « où » de la requête.
     *
     * @param  string  $method
     * @param  string  $parameters
     * @return $this
     */
    public function dynamicWhere($method, $parameters)
    {
        $finder = substr($method, 5);

        $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            if ($segment != 'And' && $segment != 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            } else {
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * Ajoutez une seule instruction de clause Where dynamique à la requête.
     *
     * @param  string  $segment
     * @param  string  $connector
     * @param  array   $parameters
     * @param  int     $index
     * @return void
     */
    protected function addDynamic($segment, $connector, $parameters, $index)
    {
        $bool = strtolower($connector);

        $this->where(Str::snake($segment), '=', $parameters[$index], $bool);
    }

    /**
     * Ajoutez une clause "group by" à la requête.
     *
     * @param  array|string  $column,...
     * @return $this
     */
    public function groupBy()
    {
        foreach (func_get_args() as $arg) {
            $this->groups = array_merge((array) $this->groups, is_array($arg) ? $arg : [$arg]);
        }

        return $this;
    }

    /**
     * Ajoutez une clause « avoir » à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        $type = 'basic';

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        $this->addBinding($value, 'having');

        return $this;
    }

    /**
     * Ajoutez une clause "ou avoir" à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @return \Two\Database\Query\Builder|static
     */
    public function orHaving($column, $operator = null, $value = null)
    {
        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Ajoutez une clause have brute à la requête.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @param  string  $boolean
     * @return $this
     */
    public function havingRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->addBinding($bindings, 'having');

        return $this;
    }

    /**
     * Ajoutez une clause raw ou have à la requête.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @return \Two\Database\Query\Builder|static
     */
    public function orHavingRaw($sql, array $bindings = array())
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Ajoutez une clause « order by » à la requête.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $property = $this->unions ? 'unionOrders' : 'orders';

        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';

        $this->{$property}[] = compact('column', 'direction');

        return $this;
    }

    /**
     * Ajoutez une clause « order by » pour un horodatage à la requête.
     *
     * @param  string  $column
     * @return \Two\Database\Query\Builder|static
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Ajoutez une clause « order by » pour un horodatage à la requête.
     *
     * @param  string  $column
     * @return \Two\Database\Query\Builder|static
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Ajoutez une clause brute « order by » à la requête.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @return $this
     */
    public function orderByRaw($sql, $bindings = array())
    {
        $type = 'raw';

        $this->orders[] = compact('type', 'sql');

        $this->addBinding($bindings, 'order');

        return $this;
    }

    /**
     * Définissez la valeur "offset" de la requête.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value)
    {
        $property = $this->unions ? 'unionOffset' : 'offset';

        $this->$property = max(0, $value);

        return $this;
    }

    /**
     * Alias ​​pour définir la valeur "offset" de la requête.
     *
     * @param  int  $value
     * @return \Two\Database\Query\Builder|static
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Définissez la valeur "limite" de la requête.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        $property = $this->unions ? 'unionLimit' : 'limit';

        if ($value > 0) $this->$property = $value;

        return $this;
    }

    /**
     * Alias ​​pour définir la valeur "limite" de la requête.
     *
     * @param  int  $value
     * @return \Two\Database\Query\Builder|static
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Définissez la limite et le décalage pour une page donnée.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return \Two\Database\Query\Builder|static
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Ajoutez une instruction union à la requête.
     *
     * @param  \Two\Database\Query\Builder|\Closure  $query
     * @param  bool  $all
     * @return \Two\Database\Query\Builder|static
     */
    public function union($query, $all = false)
    {
        if ($query instanceof Closure) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        return $this->mergeBindings($query);
    }

    /**
     * Ajoutez une instruction union all à la requête.
     *
     * @param  \Two\Database\Query\Builder|\Closure  $query
     * @return \Two\Database\Query\Builder|static
     */
    public function unionAll($query)
    {
        return $this->union($query, true);
    }

    /**
     * Verrouillez les lignes sélectionnées dans le tableau.
     *
     * @param  bool  $value
     * @return $this
     */
    public function lock($value = true)
    {
        $this->lock = $value;

        return $this;
    }

    /**
     * Verrouillez les lignes sélectionnées dans le tableau pour la mise à jour.
     *
     * @return \Two\Database\Query\Builder
     */
    public function lockForUpdate()
    {
        return $this->lock(true);
    }

    /**
     * Partager verrouille les lignes sélectionnées dans le tableau.
     *
     * @return \Two\Database\Query\Builder
     */
    public function sharedLock()
    {
        return $this->lock(false);
    }

    /**
     * Obtenez la représentation SQL de la requête.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Indiquez que les résultats de la requête doivent être mis en cache.
     *
     * @param  \DateTime|int  $minutes
     * @param  string  $key
     * @return $this
     */
    public function remember($minutes, $key = null)
    {
        list($this->cacheMinutes, $this->cacheKey) = array($minutes, $key);

        return $this;
    }

    /**
     * Indiquez que les résultats de la requête doivent être mis en cache pour toujours.
     *
     * @param  string  $key
     * @return \Two\Database\Query\Builder|static
     */
    public function rememberForever($key = null)
    {
        return $this->remember(-1, $key);
    }

    /**
     * Indiquez que les résultats, s'ils sont mis en cache, doivent utiliser les balises de cache données.
     *
     * @param  array|mixed  $cacheTags
     * @return $this
     */
    public function cacheTags($cacheTags)
    {
        $this->cacheTags = $cacheTags;

        return $this;
    }

    /**
     * Indiquez que les résultats, s'ils sont mis en cache, doivent utiliser le pilote de cache donné.
     *
     * @param  string  $cacheDriver
     * @return $this
     */
    public function cacheDriver($cacheDriver)
    {
        $this->cacheDriver = $cacheDriver;

        return $this;
    }

    /**
     * Exécutez une requête pour un seul enregistrement par ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = array('*'))
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * Extrayez la valeur d’une seule colonne du premier résultat d’une requête.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = (array) $this->first(array($column));

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * Exécutez la requête et obtenez le premier résultat.
     *
     * @param  array   $columns
     * @return mixed|static
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? reset($results) : null;
    }

    /**
     * Exécutez la requête en tant qu'instruction "select".
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function get($columns = array('*'))
    {
        if (! is_null($this->cacheMinutes)) return $this->getCached($columns);

        return $this->getFresh($columns);
    }

    /**
     * Exécutez la requête sous la forme d'une nouvelle instruction "select".
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function getFresh($columns = array('*'))
    {
        if (is_null($this->columns)) $this->columns = $columns;

        return $this->processor->processSelect($this, $this->runSelect());
    }

    /**
     * Exécutez la requête en tant qu'instruction « select » sur la connexion.
     *
     * @return array
     */
    protected function runSelect()
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    /**
     * Exécutez la requête en tant qu'instruction « select » mise en cache.
     *
     * @param  array  $columns
     * @return array
     */
    public function getCached($columns = array('*'))
    {
        if (is_null($this->columns)) $this->columns = $columns;

        //
        list($key, $minutes) = $this->getCacheInfo();

        $cache = $this->getCache();

        $callback = $this->getCacheCallback($columns);

        if ($minutes < 0) {
            return $cache->rememberForever($key, $callback);
        }

        return $cache->remember($key, $minutes, $callback);
    }

    /**
     * Obtenez l'objet de cache avec les balises attribuées, le cas échéant.
     *
     * @return \Two\Cache\CacheManager
     */
    protected function getCache()
    {
        $cache = $this->connection->getCacheManager()->driver($this->cacheDriver);

        return $this->cacheTags ? $cache->tags($this->cacheTags) : $cache;
    }

    /**
     * Obtenez la clé de cache et les minutes de cache sous forme de tableau.
     *
     * @return array
     */
    protected function getCacheInfo()
    {
        return array($this->getCacheKey(), $this->cacheMinutes);
    }

    /**
     * Obtenez une clé de cache unique pour la requête complète.
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cacheKey ?: $this->generateCacheKey();
    }

    /**
     * Générez la clé de cache unique pour la requête.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $name = $this->connection->getName();

        return md5($name.$this->toSql().serialize($this->getBindings()));
    }

    /**
     * Obtenez le rappel de fermeture utilisé lors de la mise en cache des requêtes.
     *
     * @param  array  $columns
     * @return \Closure
     */
    protected function getCacheCallback($columns)
    {
        return function() use ($columns) { return $this->getFresh($columns); };
    }

    /**
     * Découpez les résultats de la requête.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @return void
     */
    public function chunk($count, callable $callback)
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            call_user_func($callback, $results);

            $page++;

            $results = $this->forPage($page, $count)->get();
        }
    }

    /**
     * Obtenez un tableau avec les valeurs d'une colonne donnée.
     *
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        $columns = $this->getListSelect($column, $key);

        //
        $results = new Collection($this->get($columns));

        $values = $results->fetch($columns[0])->all();

        if (! is_null($key) && count($results) > 0) {
            $keys = $results->fetch($key)->all();

            return array_combine($keys, $values);
        }

        return $values;
    }

    /**
     * Obtenez les colonnes qui doivent être utilisées dans un tableau de liste.
     *
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    protected function getListSelect($column, $key)
    {
        $select = is_null($key) ? array($column) : array($column, $key);

        if (($dot = strpos($select[0], '.')) !== false) {
            $select[0] = substr($select[0], $dot + 1);
        }

        return $select;
    }

    /**
     * Concaténer les valeurs d'une colonne donnée sous forme de chaîne.
     *
     * @param  string  $column
     * @param  string  $glue
     * @return string
     */
    public function implode($column, $glue = null)
    {
        if (is_null($glue)) return implode($this->lists($column));

        return implode($glue, $this->lists($column));
    }

    /**
     * Paginez la requête donnée dans un paginateur.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Two\Pagination\Paginator
     */
    public function paginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = Paginator::resolveCurrentPage($pageName);
        }

        $path = Paginator::resolveCurrentPath($pageName);

        //
        $total = $this->getPaginationCount();

        if ($total > 0) {
            $results = $this->forPage($page, $perPage)->get($columns);
        } else {
            $results = collect();
        }

        return new Paginator($results, $total, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Paginez la requête donnée dans un simple paginateur.
     *
     * Ceci est plus efficace sur des ensembles de données plus volumineux, etc.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Two\Pagination\SimplePaginator
     */
    public function simplePaginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = Paginator::resolveCurrentPage($pageName);
        }

        $path = Paginator::resolveCurrentPath($pageName);

        //
        $offset = ($page - 1) * $perPage;

        $results = $this->skip($offset)->take($perPage + 1)->get($columns);

        return new SimplePaginator($results, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Obtenez le nombre total d’enregistrements pour la pagination.
     *
     * @return int
     */
    public function getPaginationCount()
    {
        $this->backupFieldsForCount();

        $total = $this->count();

        $this->restoreFieldsForCount();

        return $total;
    }

    /**
     * Sauvegardez certains champs pour un nombre de pagination.
     *
     * @return void
     */
    protected function backupFieldsForCount()
    {
        foreach (array('orders', 'limit', 'offset') as $field) {
            $this->backups[$field] = $this->{$field};

            $this->{$field} = null;
        }

    }

    /**
     * Restaurez certains champs pour un nombre de pagination.
     *
     * @return void
     */
    protected function restoreFieldsForCount()
    {
        foreach (array('orders', 'limit', 'offset') as $field) {
            $this->{$field} = $this->backups[$field];
        }

        $this->backups = array();
    }

    /**
     * Déterminez s’il existe des lignes pour la requête actuelle.
     *
     * @return bool
     */
    public function exists()
    {
        $limit = $this->limit;

        $result = $this->limit(1)->count() > 0;

        $this->limit($limit);

        return $result;
    }

    /**
     * Récupérez le résultat "count" de la requête.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = '*')
    {
        if (! is_array($columns)) {
            $columns = array($columns);
        }

        return (int) $this->aggregate(__FUNCTION__, $columns);
    }

    /**
     * Récupère la valeur minimale d'une colonne donnée.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Récupère la valeur maximale d'une colonne donnée.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Récupère la somme des valeurs d'une colonne donnée.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $result = $this->aggregate(__FUNCTION__, array($column));

        return $result ?: 0;
    }

    /**
     * Récupère la moyenne des valeurs d'une colonne donnée.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Exécutez une fonction d'agrégation sur la base de données.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        $results = $this->get($columns);

        $this->aggregate = null;

        $this->columns = $previousColumns;

        if (isset($results[0])) {
            $result = array_change_key_case((array) $results[0]);

            return $result['aggregate'];
        }
    }

    /**
     * Insérez un nouvel enregistrement dans la base de données.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (! is_array(reset($values))) {
            $values = array($values);
        } else {
            foreach ($values as $key => $value) {
                ksort($value); $values[$key] = $value;
            }
        }

        $bindings = array();

        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        $sql = $this->grammar->compileInsert($this, $values);

        $bindings = $this->cleanBindings($bindings);

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Insérez un nouvel enregistrement et obtenez la valeur de la clé primaire.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * Mettre à jour un enregistrement dans la base de données.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $bindings = array_values(array_merge($values, $this->getBindings()));

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings($bindings));
    }

    /**
     * Incrémentez la valeur d'une colonne d'un montant donné.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped + $amount")), $extra);

        return $this->update($columns);
    }

    /**
     * Décrémenter la valeur d'une colonne d'un montant donné.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array())
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped - $amount")), $extra);

        return $this->update($columns);
    }

    /**
     * Supprimer un enregistrement de la base de données.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        if (! is_null($id)) $this->where('id', '=', $id);

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->getBindings());
    }

    /**
     * Exécutez une instruction tronquée sur la table.
     *
     * @return void
     */
    public function truncate()
    {
        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    /**
     * Obtenez une nouvelle instance du générateur de requêtes.
     *
     * @return \Two\Database\Query\Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Fusionnez un tableau de clauses Where et de liaisons.
     *
     * @param  array  $wheres
     * @param  array  $bindings
     * @return void
     */
    public function mergeWheres($wheres, $bindings)
    {
        $this->wheres = array_merge((array) $this->wheres, (array) $wheres);

        $this->bindings['where'] = array_values(array_merge($this->bindings['where'], (array) $bindings));
    }

    /**
     * Supprimez toutes les expressions d’une liste de liaisons.
     *
     * @param  array  $bindings
     * @return array
     */
    protected function cleanBindings(array $bindings)
    {
        return array_values(array_filter($bindings, function($binding)
        {
            return ! $binding instanceof Expression;
        }));
    }

    /**
     * Créez une expression de base de données brute.
     *
     * @param  mixed  $value
     * @return \Two\Database\Query\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * Obtenez les liaisons de valeurs de requête actuelles dans un tableau aplati.
     *
     * @return array
     */
    public function getBindings()
    {
        return array_flatten($this->bindings);
    }

    /**
     * Obtenez la gamme brute de liaisons.
     *
     * @return array
     */
    public function getRawBindings()
    {
        return $this->bindings;
    }

    /**
     * Définissez les liaisons sur le générateur de requêtes.
     *
     * @param  array   $bindings
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Ajoutez une liaison à la requête.
     *
     * @param  mixed   $value
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Fusionnez un tableau de liaisons dans nos liaisons.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return $this
     */
    public function mergeBindings(Builder $query)
    {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Obtenez l'instance de connexion à la base de données.
     *
     * @return \Two\Database\Contracts\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Obtenez l’instance du processeur de requêtes de base de données.
     *
     * @return \Two\Database\Query\Processors\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Obtenez l’instance de grammaire de requête.
     *
     * @return \Two\Database\Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * Gérez les appels de méthode dynamique dans la méthode.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        $className = get_class($this);

        throw new BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

}

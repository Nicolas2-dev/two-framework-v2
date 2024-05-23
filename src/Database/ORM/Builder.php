<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM;

use Closure;

use Two\Support\Str;
use Two\Pagination\Paginator;
use Two\Database\Query\Expression;
use Two\Pagination\SimplePaginator;
use Two\Database\ORM\Relations\Relation;
use Two\Database\Query\Builder as QueryBuilder;
use Two\Database\Exception\ModelNotFoundException;


class Builder
{
    /**
     * Instance du générateur de requêtes de base.
     *
     * @var \Two\Database\Query\Builder
     */
    protected $query;

    /**
     * Le modèle interrogé.
     *
     * @var \Two\Database\ORM\Model
     */
    protected $model;

    /**
     * Les relations qui devraient être chargées avec impatience.
     *
     * @var array
     */
    protected $eagerLoad = array();

    /**
     * Toutes les macros de constructeur enregistrées.
     *
     * @var array
     */
    protected $macros = array();

    /**
     * Un remplacement pour la fonction de suppression typique.
     *
     * @var \Closure
     */
    protected $onDelete;

    /**
     * Les méthodes qui doivent être renvoyées par le générateur de requêtes.
     *
     * @var array
     */
    protected $passthru = array(
        'toSql', 'lists', 'insert', 'insertGetId', 'pluck', 'count',
        'min', 'max', 'avg', 'sum', 'exists', 'getBindings', 'getGrammar'
    );

    /**
     * Créez une nouvelle instance du générateur de requêtes ORM.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Recherchez un modèle par sa clé primaire.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|static|null
     */
    public function find($id, $columns = array('*'))
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        $keyName = $this->model->getQualifiedKeyName();

        $this->query->where($keyName, '=', $id);

        return $this->first($columns);
    }

    /**
     * Recherchez un modèle par sa clé primaire.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|Collection|static
     */
    public function findMany($ids, $columns = array('*'))
    {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        $keyName = $this->model->getQualifiedKeyName();

        $this->query->whereIn($keyName, $ids);

        return $this->get($columns);
    }

    /**
     * Recherchez un modèle par sa clé primaire ou lancez une exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|static
     *
     * @throws \Two\Database\Exception\ModelNotFoundException
     */
    public function findOrFail($id, $columns = array('*'))
    {
        if (! is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        $className = get_class($this->model);

        throw (new ModelNotFoundException)->setModel($className);
    }

    /**
     * Exécutez la requête et obtenez le premier résultat.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|static|null
     */
    public function first($columns = array('*'))
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Exécutez la requête et obtenez le premier résultat ou lancez une exception.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|static
     *
     * @throws \Two\Database\Exception\ModelNotFoundException
     */
    public function firstOrFail($columns = array('*'))
    {
        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        $className = get_class($this->model);

        throw (new ModelNotFoundException)->setModel($className);
    }

    /**
     * Exécutez la requête et obtenez le premier résultat ou appelez un rappel.
     *
     * @param  \Closure|array  $columns
     * @param  \Closure|null  $callback
     * @return \Two\Database\ORM\Model|static|mixed
     */
    public function firstOr($columns = array('*'), Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;

            $columns = array('*');
        }

        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        return call_user_func($callback);
    }

    /**
     * Exécutez la requête en tant qu'instruction "select".
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Collection|static[]
     */
    public function get($columns = array('*'))
    {
        $models = $this->getModels($columns);

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->model->newCollection($models);
    }

    /**
     * Extrayez une seule colonne de la base de données.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = $this->first(array($column));

        if ($result) return $result->{$column};
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
        $results = $this->query->lists($column, $key);

        if ($this->model->hasGetMutator($column)) {
            foreach ($results as $key => &$value) {
                $fill = array($column => $value);

                $value = $this->model->newFromBuilder($fill)->$column;
            }
        }

        return $results;
    }

    /**
     * Paginez la requête donnée.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Two\Pagination\Paginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = array('*'), $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = Paginator::resolveCurrentPage($pageName);
        }

        $path = Paginator::resolveCurrentPath($pageName);

        if (is_null($perPage)) {
            $perPage = $this->model->getPerPage();
        }

        $total = $this->query->getPaginationCount();

        if ($total > 0) {
            $results = $this->forPage($page, $perPage)->get($columns);
        } else {
            $results = $this->model->newCollection();
        }

        return new Paginator($results, $total, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Paginez la requête donnée dans un simple paginateur.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Two\Pagination\SimplePaginator
     */
    public function simplePaginate($perPage = null, $columns = array('*'), $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = Paginator::resolveCurrentPage($pageName);
        }

        $path = Paginator::resolveCurrentPath($pageName);

        if (is_null($perPage)) {
            $perPage = $this->model->getPerPage();
        }

        $offset = ($page - 1) * $perPage;

        $results = $this->skip($offset)->take($perPage + 1)->get($columns);

        return new SimplePaginator($results, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Mettre à jour un enregistrement dans la base de données.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
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
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->increment($column, $amount, $extra);
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
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->decrement($column, $amount, $extra);
    }

    /**
     * Ajoutez la colonne « mis à jour à » à un tableau de valeurs.
     *
     * @param  array  $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps()) return $values;

        $column = $this->model->getUpdatedAtColumn();

        return array_add($values, $column, $this->model->freshTimestampString());
    }

    /**
     * Supprimer un enregistrement de la base de données.
     *
     * @return mixed
     */
    public function delete()
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        return $this->query->delete();
    }

    /**
     * Exécutez la fonction de suppression par défaut sur le générateur.
     *
     * @return mixed
     */
    public function forceDelete()
    {
        return $this->query->delete();
    }

    /**
     * Enregistrez un remplacement pour la fonction de suppression par défaut.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function onDelete(Closure $callback)
    {
        $this->onDelete = $callback;
    }

    /**
     * Obtenez les modèles hydratés sans chargement précipité.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Model[]
     */
    public function getModels($columns = array('*'))
    {
        $results = $this->query->get($columns);

        $connection = $this->model->getConnectionName();

        $models = array();

        foreach ($results as $result) {
            $models[] = $model = $this->model->newFromBuilder($result);

            $model->setConnection($connection);
        }

        return $models;
    }

    /**
     * Impatient de charger les relations pour les modèles.
     *
     * @param  array  $models
     * @return array
     */
    public function eagerLoadRelations(array $models)
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->loadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Chargez avec impatience la relation sur un ensemble de modèles.
     *
     * @param  array     $models
     * @param  string    $name
     * @param  \Closure  $constraints
     * @return array
     */
    protected function loadRelation(array $models, $name, Closure $constraints)
    {
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        call_user_func($constraints, $relation);

        $models = $relation->initRelation($models, $name);

        $results = $relation->getEager();

        return $relation->match($models, $results, $name);
    }

    /**
     * Obtenez l'instance de relation pour le nom de relation donné.
     *
     * @param  string  $relation
     * @return \Two\Database\ORM\Relations\Relation
     */
    public function getRelation($relation)
    {
        $query = Relation::noConstraints(function() use ($relation)
        {
            return $this->getModel()->$relation();
        });

        $nested = $this->nestedRelations($relation);

        if (count($nested) > 0) {
            $query->getQuery()->with($nested);
        }

        return $query;
    }

    /**
     * Obtenez les relations profondément imbriquées pour une relation de niveau supérieur donnée.
     *
     * @param  string  $relation
     * @return array
     */
    protected function nestedRelations($relation)
    {
        $nested = array();

        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNested($name, $relation)) {
                $nested[substr($name, strlen($relation.'.'))] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Déterminez si la relation est imbriquée.
     *
     * @param  string  $name
     * @param  string  $relation
     * @return bool
     */
    protected function isNested($name, $relation)
    {
        $dots = str_contains($name, '.');

        return $dots && Str::startsWith($name, $relation.'.');
    }

    /**
     * Ajoutez une clause Where de base à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof Closure) {
            $query = $this->model->newQueryWithoutScopes();

            call_user_func($column, $query);

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            call_user_func_array(array($this->query, 'where'), func_get_args());
        }

        return $this;
    }

    /**
     * Ajoutez une clause « ou où » à la requête.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return \Two\Database\ORM\Builder|static
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête.
     *
     * @param  string  $relation
     * @param  string  $operator
     * @param  int     $count
     * @param  string  $boolean
     * @param  \Closure|null  $callback
     * @return \Two\Database\ORM\Builder|static
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null)
    {
        if (strpos($relation, '.') !== false) {
            return $this->hasNested($relation, $operator, $count, $boolean, $callback);
        }

        $relation = $this->getHasRelationQuery($relation);

        $query = $relation->getRelationCountQuery($relation->getRelated()->newQuery(), $this);

        if ($callback) call_user_func($callback, $query);

        return $this->addHasWhere($query, $relation, $operator, $count, $boolean);
    }

    /**
     * Ajoutez des conditions de nombre de relations imbriquées à la requête.
     *
     * @param  string  $relations
     * @param  string  $operator
     * @param  int     $count
     * @param  string  $boolean
     * @param  \Closure  $callback
     * @return \Two\Database\ORM\Builder|static
     */
    protected function hasNested($relations, $operator = '>=', $count = 1, $boolean = 'and', $callback = null)
    {
        $relations = explode('.', $relations);

        $closure = function ($q) use (&$closure, &$relations, $operator, $count, $boolean, $callback)
        {
            if (count($relations) > 1) {
                $q->whereHas(array_shift($relations), $closure);
            } else {
                $q->has(array_shift($relations), $operator, $count, $boolean, $callback);
            }
        };

        return $this->whereHas(array_shift($relations), $closure);
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête.
     *
     * @param  string  $relation
     * @param  string  $boolean
     * @param  \Closure|null  $callback
     * @return \Two\Database\ORM\Builder|static
     */
    public function doesntHave($relation, $boolean = 'and', Closure $callback = null)
    {
        return $this->has($relation, '<', 1, $boolean, $callback);
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête avec des clauses Where.
     *
     * @param  string    $relation
     * @param  \Closure  $callback
     * @param  string    $operator
     * @param  int       $count
     * @return \Two\Database\ORM\Builder|static
     */
    public function whereHas($relation, Closure $callback, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'and', $callback);
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête avec des clauses Where.
     *
     * @param  string  $relation
     * @param  \Closure|null  $callback
     * @return \Two\Database\ORM\Builder|static
     */
    public function whereDoesntHave($relation, Closure $callback = null)
    {
        return $this->doesntHave($relation, 'and', $callback);
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête avec un « ou ».
     *
     * @param  string  $relation
     * @param  string  $operator
     * @param  int     $count
     * @return \Two\Database\ORM\Builder|static
     */
    public function orHas($relation, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'or');
    }

    /**
     * Ajoutez une condition de nombre de relations à la requête avec des clauses Where et un « ou ».
     *
     * @param  string    $relation
     * @param  \Closure  $callback
     * @param  string    $operator
     * @param  int       $count
     * @return \Two\Database\ORM\Builder|static
     */
    public function orWhereHas($relation, Closure $callback, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'or', $callback);
    }

    /**
     * Ajoutez la condition "has" où clause à la requête.
     *
     * @param  \Two\Database\ORM\Builder  $hasQuery
     * @param  \Two\Database\ORM\Relations\Relation  $relation
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @return \Two\Database\ORM\Builder
     */
    protected function addHasWhere(Builder $hasQuery, Relation $relation, $operator, $count, $boolean)
    {
        $this->mergeWheresToHas($hasQuery, $relation);

        if (is_numeric($count)) {
            $count = new Expression($count);
        }

        return $this->where(new Expression('('.$hasQuery->toSql().')'), $operator, $count, $boolean);
    }

    /**
     * Fusionnez les "où" d'une requête relationnelle vers une requête has.
     *
     * @param  \Two\Database\ORM\Builder  $hasQuery
     * @param  \Two\Database\ORM\Relations\Relation  $relation
     * @return void
     */
    protected function mergeWheresToHas(Builder $hasQuery, Relation $relation)
    {
        $relationQuery = $relation->getBaseQuery();

        $hasQuery = $hasQuery->getModel()->removeGlobalScopes($hasQuery);

        $hasQuery->mergeWheres(
            $relationQuery->wheres, $relationQuery->getBindings()
        );

        $this->query->mergeBindings($hasQuery->getQuery());
    }

    /**
     * Obtenez l’instance de requête de base « a une relation ».
     *
     * @param  string  $relation
     * @return \Two\Database\ORM\Builder
     */
    protected function getHasRelationQuery($relation)
    {
        return Relation::noConstraints(function() use ($relation)
        {
            return $this->getModel()->$relation();
        });
    }

    /**
     * Définissez les relations qui doivent être chargées avec impatience.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $eagers = $this->parseRelations($relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagers);

        return $this;
    }

    /**
     * Empêcher les relations spécifiées d'être chargées avec impatience.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function without($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip($relations));

        return $this;
    }

    /**
     * Ajoutez des requêtes de sous-sélection pour compter les relations.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function withCount($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        // Si aucune colonne n'est définie, ajoutez les colonnes * par défaut.
        if (is_null($this->query->columns)) {
            $this->query->select($this->query->from .'.*');
        }

        $relations = $this->parseRelations($relations);

        foreach ($relations as $name => $constraints) {
            $segments = explode(' ', $name);

            if ((count($segments) == 3) && (Str::lower($segments[1]) == 'as')) {
                list($name, $alias) = array($segments[0], $segments[2]);
            } else {
                unset($alias);
            }

            $relation = $this->getHasRelationQuery($name);

            // Ici, nous obtiendrons la requête de nombre de relations et nous préparerons à l'ajouter à la requête principale
            // en tant que sous-sélection. Tout d'abord, nous obtiendrons la requête "has" et l'utiliserons pour obtenir la relation
            // requête de comptage. Nous normaliserons le nom de la relation puis ajouterons _count comme nom.
            $query = $relation->getRelationCountQuery($relation->getRelated()->newQuery(), $this);

            call_user_func($constraints, $query);

            $this->mergeWheresToHas($query, $relation);

            $asColumn = snake_case(isset($alias) ? $alias : $name) .'_count';

            $this->selectSub($query->getQuery(), $asColumn);
        }

        return $this;
    }

    /**
     * Analyser une liste de relations en individus.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseRelations(array $relations)
    {
        $results = array();

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $f = function() {};

                list($name, $constraints) = array($constraints, $f);
            }

            $results = $this->parseNested($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Analyser les relations imbriquées dans une relation.
     *
     * @param  string  $name
     * @param  array   $results
     * @return array
     */
    protected function parseNested($name, $results)
    {
        $progress = array();

        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (! isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function() {};
            }
        }

        return $results;
    }

    /**
     * Appelez la portée du modèle donnée sur le modèle sous-jacent.
     *
     * @param  string  $scope
     * @param  array   $parameters
     * @return \Two\Database\Query\Builder
     */
    protected function callScope($scope, $parameters)
    {
        array_unshift($parameters, $this);

        return call_user_func_array(array($this->model, $scope), $parameters) ?: $this;
    }

    /**
     * Obtenez l'instance de générateur de requêtes sous-jacente.
     *
     * @return \Two\Database\Query\Builder|static
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Définissez l'instance du générateur de requêtes sous-jacente.
     *
     * @param  \Two\Database\Query\Builder  $query
     * @return void
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * Obtenez des relations chargées avec impatience.
     *
     * @return array
     */
    public function getEagerLoads()
    {
        return $this->eagerLoad;
    }

    /**
     * Définissez les relations chargées avec impatience.
     *
     * @param  array  $eagerLoad
     * @return void
     */
    public function setEagerLoads(array $eagerLoad)
    {
        $this->eagerLoad = $eagerLoad;
    }

    /**
     * Obtenez l'instance de modèle interrogée.
     *
     * @return \Two\Database\ORM\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Définissez une instance de modèle pour le modèle interrogé.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Étendez le constructeur avec un rappel donné.
     *
     * @param  string    $name
     * @param  \Closure  $callback
     * @return void
     */
    public function macro($name, Closure $callback)
    {
        $this->macros[$name] = $callback;
    }

    /**
     * Obtenez la macro donnée par son nom.
     *
     * @param  string  $name
     * @return \Closure
     */
    public function getMacro($name)
    {
        return array_get($this->macros, $name);
    }

    /**
     * Gérez dynamiquement les appels dans l’instance de requête.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (isset($this->macros[$method])) {
            array_unshift($parameters, $this);

            return call_user_func_array($this->macros[$method], $parameters);
        } else if (method_exists($this->model, $scope = 'scope' .ucfirst($method))) {
            return $this->callScope($scope, $parameters);
        }

        $result = call_user_func_array(array($this->query, $method), $parameters);

        return in_array($method, $this->passthru) ? $result : $this;
    }

    /**
     * Forcer un clone du générateur de requêtes sous-jacent lors du clonage.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM\Relations;

use Two\Support\Str;
use Two\Database\ORM\Model;
use Two\Database\ORM\Builder;
use Two\Database\ORM\Collection;
use Two\Database\Query\Expression;
use Two\Database\Exception\ModelNotFoundException;


class BelongsToMany extends Relation
{
    /**
     * La table intermédiaire pour la relation.
     *
     * @var string
     */
    protected $table;

    /**
     * La clé étrangère du modèle parent.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * La clé associée à la relation.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * Le « nom » de la relation.
     *
     * @var string
     */
    protected $relationName;

    /**
     * Les colonnes du tableau croisé dynamique à récupérer.

     *
     * @var array
     */
    protected $pivotColumns = array();

    /**
     * Toutes les restrictions du tableau croisé dynamique.
     *
     * @var array
     */
    protected $pivotWheres = array();

    /**
     * Nom de classe du modèle pivot personnalisé à utiliser pour la relation.
     *
     * @var string
     */
    protected $using;


    /**
     * Créez une nouvelle instance de relation comportant plusieurs.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relationName
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $table, $foreignKey, $otherKey, $relationName = null)
    {
        $this->table = $table;
        $this->otherKey = $otherKey;
        $this->foreignKey = $foreignKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    /**
     * Spécifiez le modèle pivot personnalisé à utiliser pour la relation.
     *
     * @param  string  $className
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    public function using($className)
    {
        $this->using = $className;

        return $this;
    }

    /**
     * Obtenez les résultats de la relation.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Définissez une clause Where pour une colonne de tableau croisé dynamique.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    public function wherePivot($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->pivotWheres[] = func_get_args();

        return $this->where($this->table.'.'.$column, $operator, $value, $boolean);
    }

    /**
     * Définissez une clause ou où pour une colonne de tableau croisé dynamique.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    public function orWherePivot($column, $operator = null, $value = null)
    {
        return $this->wherePivot($column, $operator, $value, 'or');
    }

    /**
     * Exécutez la requête et obtenez le premier résultat.
     *
     * @param  array   $columns
     * @return mixed
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? $results->first() : null;
    }

    /**
     * Exécutez la requête et obtenez le premier résultat ou lancez une exception.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Model|static
     *
     * @throws \Database\Exception\ModelNotFoundException
     */
    public function firstOrFail($columns = array('*'))
    {
        if (! is_null($model = $this->first($columns))) return $model;

        throw new ModelNotFoundException;
    }

    /**
     * Exécutez la requête en tant qu'instruction "select".
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Collection
     */
    public function get($columns = array('*'))
    {
        $columns = $this->query->getQuery()->columns ? array() : $columns;

        $select = $this->getSelectColumns($columns);

        $models = $this->query->addSelect($select)->getModels();

        $this->hydratePivotRelation($models);

        if (count($models) > 0) {
            $models = $this->query->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Obtenez un paginateur pour l'instruction "select".
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Two\Pagination\Paginator
     */
    public function paginate($perPage = null, $columns = array('*'), $pageName = 'page', $page = null)
    {
        $this->query->addSelect($this->getSelectColumns($columns));

        $paginator = $this->query->paginate($perPage, $columns, $pageName, $page);

        $this->hydratePivotRelation($paginator->items());

        return $paginator;
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
        $this->query->addSelect($this->getSelectColumns($columns));

        $paginator = $this->query->simplePaginate($perPage, $columns, $pageName, $page);

        $this->hydratePivotRelation($paginator->items());

        return $paginator;
    }

    /**
     * Hydratez la relation du tableau croisé dynamique sur les modèles.
     *
     * @param  array  $models
     * @return void
     */
    protected function hydratePivotRelation(array $models)
    {
        foreach ($models as $model) {
            $pivot = $this->newExistingPivot($this->cleanPivotAttributes($model));

            $model->setRelation('pivot', $pivot);
        }
    }

    /**
     * Obtenez les attributs pivot d'un modèle.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return array
     */
    protected function cleanPivotAttributes(Model $model)
    {
        $values = array();

        foreach ($model->getAttributes() as $key => $value) {
            if (strpos($key, 'pivot_') === 0) {
                $values[substr($key, 6)] = $value;

                unset($model->$key);
            }
        }

        return $values;
    }

    /**
     * Définissez les contraintes de base sur la requête relationnelle.
     *
     * @return void
     */
    public function addConstraints()
    {
        $this->setJoin();

        if (static::$constraints) {
            $this->setWhere();
        }
    }

    /**
     * Ajoutez les contraintes pour une requête de nombre de relations.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Builder  $parent
     * @return \Two\Database\ORM\Builder
     */
    public function getRelationCountQuery(Builder $query, Builder $parent)
    {
        if ($parent->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationCountQueryForSelfJoin($query, $parent);
        }

        $this->setJoin($query);

        return parent::getRelationCountQuery($query, $parent);
    }

    /**
     * Ajoutez les contraintes pour une requête de nombre de relations sur la même table.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Builder  $parent
     * @return \Two\Database\ORM\Builder
     */
    public function getRelationCountQueryForSelfJoin(Builder $query, Builder $parent)
    {
        $query->select(new Expression('count(*)'));

        $tablePrefix = $this->query->getQuery()->getConnection()->getTablePrefix();

        $query->from($this->table.' as '.$tablePrefix .$hash = $this->getRelationCountHash());

        $key = $this->wrap($this->getQualifiedParentKeyName());

        return $query->where($hash.'.'.$this->foreignKey, '=', new Expression($key));
    }

    /**
     * Obtenez un hachage de table de jointure de relation.
     *
     * @return string
     */
    public function getRelationCountHash()
    {
        return 'self_' .md5(microtime(true));
    }

    /**
     * Définissez la clause select pour la requête de relation.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    protected function getSelectColumns(array $columns = array('*'))
    {
        if ($columns == array('*')) {
            $columns = array($this->related->getTable().'.*');
        }

        return array_merge($columns, $this->getAliasedPivotColumns());
    }

    /**
     * Obtenez les colonnes pivot pour la relation.
     *
     * @return array
     */
    protected function getAliasedPivotColumns()
    {
        $defaults = array($this->foreignKey, $this->otherKey);

        $columns = array();

        foreach (array_merge($defaults, $this->pivotColumns) as $column) {
            $columns[] = $this->table.'.'.$column.' as pivot_'.$column;
        }

        return array_unique($columns);
    }

    /**
     * Déterminez si la colonne donnée est définie comme colonne pivot.
     *
     * @param  string  $column
     * @return bool
     */
    protected function hasPivotColumn($column)
    {
        return in_array($column, $this->pivotColumns);
    }

    /**
     * Définissez la clause de jointure pour la requête de relation.
     *
     * @param  \Two\Database\ORM\Builder|null
     * @return $this
     */
    protected function setJoin($query = null)
    {
        $query = $query ?: $this->query;

        $baseTable = $this->related->getTable();

        $key = $baseTable.'.'.$this->related->getKeyName();

        $query->join($this->table, $key, '=', $this->getOtherKey());

        return $this;
    }

    /**
     * Définissez la clause Where pour la requête relationnelle.
     *
     * @return $this
     */
    protected function setWhere()
    {
        $foreign = $this->getForeignKey();

        $this->query->where($foreign, '=', $this->parent->getKey());

        return $this;
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->getForeignKey(), $this->getKeys($models));
    }

    /**
     * Initialisez la relation sur un ensemble de modèles.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Faites correspondre les résultats chargés avec impatience à leurs parents.
     *
     * @param  array   $models
     * @param  \Two\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getKey()])) {
                $collection = $this->related->newCollection($dictionary[$key]);

                $model->setRelation($relation, $collection);
            }
        }

        return $models;
    }

    /**
     * Construisez un dictionnaire de modèle saisi par la clé étrangère de la relation.
     *
     * @param  \Two\Database\ORM\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->foreignKey;

        $dictionary = array();

        foreach ($results as $result) {
            $dictionary[$result->pivot->$foreign][] = $result;
        }

        return $dictionary;
    }

    /**
     * Touchez tous les modèles associés à la relation.
     *
     * Par exemple : touchez tous les rôles associés à cet utilisateur.
     *
     * @return void
     */
    public function touch()
    {
        $key = $this->getRelated()->getKeyName();

        $columns = $this->getRelatedFreshUpdate();

        $ids = $this->getRelatedIds();

        if (count($ids) > 0) {
            $this->getRelated()->newQuery()->whereIn($key, $ids)->update($columns);
        }
    }

    /**
     * Obtenez tous les identifiants des modèles associés.
     *
     * @return array
     */
    public function getRelatedIds()
    {
        $related = $this->getRelated();

        $fullKey = $related->getQualifiedKeyName();

        return $this->getQuery()->select($fullKey)->lists($related->getKeyName());
    }

    /**
     * Enregistrez un nouveau modèle et attachez-le au modèle parent.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @param  array  $joining
     * @param  bool   $touch
     * @return \Two\Database\ORM\Model
     */
    public function save(Model $model, array $joining = array(), $touch = true)
    {
        $model->save(array('touch' => false));

        $this->attach($model->getKey(), $joining, $touch);

        return $model;
    }

    /**
     * Enregistrez un tableau de nouveaux modèles et attachez-les au modèle parent.
     *
     * @param  array  $models
     * @param  array  $joinings
     * @return array
     */
    public function saveMany(array $models, array $joinings = array())
    {
        foreach ($models as $key => $model) {
            $this->save($model, (array) array_get($joinings, $key), false);
        }

        $this->touchIfTouching();

        return $models;
    }

    /**
     * Créez une nouvelle instance du modèle associé.
     *
     * @param  array  $attributes
     * @param  array  $joining
     * @param  bool   $touch
     * @return \Two\Database\ORM\Model
     */
    public function create(array $attributes, array $joining = array(), $touch = true)
    {
        $instance = $this->related->newInstance($attributes);

        $instance->save(array('touch' => false));

        $this->attach($instance->getKey(), $joining, $touch);

        return $instance;
    }

    /**
     * Créez un tableau de nouvelles instances des modèles associés.
     *
     * @param  array  $records
     * @param  array  $joinings
     * @return \Two\Database\ORM\Model
     */
    public function createMany(array $records, array $joinings = array())
    {
        $instances = array();

        foreach ($records as $key => $record) {
            $instances[] = $this->create($record, (array) array_get($joinings, $key), false);
        }

        $this->touchIfTouching();

        return $instances;
    }

    /**
     * Synchronisez les tables intermédiaires avec une liste d'identifiants ou une collection de modèles.
     *
     * @param  array  $ids
     * @param  bool   $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        $changes = array(
            'attached' => array(), 'detached' => array(), 'updated' => array()
        );

        if ($ids instanceof Collection) $ids = $ids->modelKeys();

        $current = $this->newPivotQuery()->lists($this->otherKey);

        $records = $this->formatSyncList($ids);

        $detach = array_diff($current, array_keys($records));

        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = (array) array_map(function($v) { return (int) $v; }, $detach);
        }

        $changes = array_merge(
            $changes, $this->attachNew($records, $current, false)
        );

        if (count($changes['attached']) || count($changes['updated'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Formatez la liste de synchronisation afin qu'elle soit saisie par ID.
     *
     * @param  array  $records
     * @return array
     */
    protected function formatSyncList(array $records)
    {
        $results = array();

        foreach ($records as $id => $attributes) {
            if (! is_array($attributes)) {
                list($id, $attributes) = array($attributes, array());
            }

            $results[$id] = $attributes;
        }

        return $results;
    }

    /**
     * Attachez tous les ID qui ne figurent pas dans le tableau actuel.
     *
     * @param  array  $records
     * @param  array  $current
     * @param  bool   $touch
     * @return array
     */
    protected function attachNew(array $records, array $current, $touch = true)
    {
        $changes = array('attached' => array(), 'updated' => array());

        foreach ($records as $id => $attributes) {
            if (! in_array($id, $current)) {
                $this->attach($id, $attributes, $touch);

                $changes['attached'][] = (int) $id;
            } else if ((count($attributes) > 0) && $this->updateExistingPivot($id, $attributes, $touch)) {
                $changes['updated'][] = (int) $id;
            }
        }

        return $changes;
    }

    /**
     * Mettez à jour un enregistrement pivot existant sur la table.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     * @return void
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        if (in_array($this->updatedAt(), $this->pivotColumns)) {
            $attributes = $this->setTimestampsOnAttach($attributes, true);
        }

        $updated = $this->newPivotStatementForId($id)->update($attributes);

        if ($touch) $this->touchIfTouching();

        return $updated;
    }

    /**
     * Attachez un modèle au parent.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     * @return void
     */
    public function attach($id, array $attributes = array(), $touch = true)
    {
        if ($id instanceof Model) $id = $id->getKey();

        $query = $this->newPivotStatement();

        $query->insert($this->createAttachRecords((array) $id, $attributes));

        if ($touch) $this->touchIfTouching();
    }

    /**
     * Créez un tableau d'enregistrements à insérer dans le tableau croisé dynamique.
     *
     * @param  array  $ids
     * @param  array  $attributes
     * @return array
     */
    protected function createAttachRecords($ids, array $attributes)
    {
        $records = array();

        $timed = ($this->hasPivotColumn($this->createdAt()) || $this->hasPivotColumn($this->updatedAt()));

        foreach ($ids as $key => $value) {
            $records[] = $this->attacher($key, $value, $attributes, $timed);
        }

        return $records;
    }

    /**
     * Créez une charge utile d’enregistrement de pièce jointe complète.
     *
     * @param  int    $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @param  bool   $timed
     * @return array
     */
    protected function attacher($key, $value, $attributes, $timed)
    {
        list($id, $extra) = $this->getAttachId($key, $value, $attributes);

        $record = $this->createAttachRecord($id, $timed);

        return array_merge($record, $extra);
    }

    /**
     * Obtenez l’ID de l’enregistrement joint et les attributs supplémentaires.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    protected function getAttachId($key, $value, array $attributes)
    {
        if (is_array($value)) {
            return array($key, array_merge($value, $attributes));
        }

        return array($value, $attributes);
    }

    /**
     * Créez un nouvel enregistrement de pièce jointe pivot.
     *
     * @param  int   $id
     * @param  bool  $timed
     * @return array
     */
    protected function createAttachRecord($id, $timed)
    {
        $record[$this->foreignKey] = $this->parent->getKey();

        $record[$this->otherKey] = $id;

        if ($timed) {
            $record = $this->setTimestampsOnAttach($record);
        }

        return $record;
    }

    /**
     * Définissez les horodatages de création et de mise à jour sur un enregistrement en pièce jointe.
     *
     * @param  array  $record
     * @param  bool   $exists
     * @return array
     */
    protected function setTimestampsOnAttach(array $record, $exists = false)
    {
        $fresh = $this->parent->freshTimestamp();

        if (! $exists && $this->hasPivotColumn($this->createdAt())) {
            $record[$this->createdAt()] = $fresh;
        }

        if ($this->hasPivotColumn($this->updatedAt())) {
            $record[$this->updatedAt()] = $fresh;
        }

        return $record;
    }

    /**
     * Détachez les modèles de la relation.
     *
     * @param  int|array  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = array(), $touch = true)
    {
        if ($ids instanceof Model) $ids = (array) $ids->getKey();

        $query = $this->newPivotQuery();

        $ids = (array) $ids;

        if (count($ids) > 0) {
            $query->whereIn($this->otherKey, (array) $ids);
        }

        if ($touch) $this->touchIfTouching();

        $results = $query->delete();

        return $results;
    }

    /**
     * Si nous touchons le modèle parent, touchez.
     *
     * @return void
     */
    public function touchIfTouching()
    {
        if ($this->touchingParent()) $this->getParent()->touch();

        if ($this->getParent()->touches($this->relationName)) $this->touch();
    }

    /**
     * Déterminez si nous devons toucher le parent lors de la synchronisation.
     *
     * @return bool
     */
    protected function touchingParent()
    {
        return $this->getRelated()->touches($this->guessInverseRelation());
    }

    /**
     * Essayez de deviner le nom de l’inverse de la relation.
     *
     * @return string
     */
    protected function guessInverseRelation()
    {
        $relation = Str::plural(class_basename($this->getParent()));

        return Str::camel($relation);
    }

    /**
     * Créez un nouveau générateur de requêtes pour le tableau croisé dynamique.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        $query = $this->newPivotStatement();

        foreach ($this->pivotWheres as $whereArgs) {
            call_user_func_array([$query, 'where'], $whereArgs);
        }

        return $query->where($this->foreignKey, $this->parent->getKey());
    }

    /**
     * Obtenez un nouveau générateur de requêtes simples pour le tableau croisé dynamique.
     *
     * @return \Two\Database\Query\Builder
     */
    public function newPivotStatement()
    {
        return $this->query->getQuery()->newQuery()->from($this->table);
    }

    /**
     * Obtenez une nouvelle instruction pivot pour un « autre » identifiant donné.
     *
     * @param  mixed  $id
     * @return \Two\Database\Query\Builder
     */
    public function newPivotStatementForId($id)
    {
        return $this->newPivotQuery()->where($this->otherKey, $id);
    }

    /**
     * Créez une nouvelle instance de modèle pivot.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return \Two\Database\ORM\Relations\Pivot
     */
    public function newPivot(array $attributes = array(), $exists = false)
    {
        $pivot = $this->related->newPivot($this->parent, $attributes, $this->table, $exists, $this->using);

        return $pivot->setPivotKeys($this->foreignKey, $this->otherKey);
    }

    /**
     * Créez une nouvelle instance de modèle pivot existante.
     *
     * @param  array  $attributes
     * @return \Two\Database\ORM\Relations\Pivot
     */
    public function newExistingPivot(array $attributes = array())
    {
        return $this->newPivot($attributes, true);
    }

    /**
     * Définissez les colonnes du tableau croisé dynamique à récupérer.
     *
     * @param  mixed  $columns
     * @return $this
     */
    public function withPivot($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        return $this;
    }

    /**
     * Spécifiez que le tableau croisé dynamique comporte des horodatages de création et de mise à jour.
     *
     * @param  mixed  $createdAt
     * @param  mixed  $updatedAt
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        return $this->withPivot($createdAt ?: $this->createdAt(), $updatedAt ?: $this->updatedAt());
    }

    /**
     * Obtenez la mise à jour du modèle associé au nom de la colonne.
     *
     * @return string
     */
    public function getRelatedFreshUpdate()
    {
        return array($this->related->getUpdatedAtColumn() => $this->related->freshTimestamp());
    }

    /**
     * Obtenez la clé à comparer avec la clé parent dans la requête "has".
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKey();
    }

    /**
     * Obtenez la clé étrangère complète pour la relation.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->table.'.'.$this->foreignKey;
    }

    /**
     * Obtenez l’« autre clé » pleinement qualifiée pour la relation.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->table .'.' .$this->otherKey;
    }

    /**
     * Obtenez la table intermédiaire pour la relation.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Obtenez le nom de la relation.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

}

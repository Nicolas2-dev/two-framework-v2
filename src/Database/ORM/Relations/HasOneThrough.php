<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM\Relations;

use Two\Database\ORM\Model;
use Two\Database\ORM\Builder;
use Two\Database\ORM\Collection;
use Two\Database\Query\Expression;
use Two\Database\ORM\Relations\Relation;
use Two\Database\Exception\ModelNotFoundException;


class HasOneThrough extends Relation
{
    /**
     * Instance de modèle parent à distance.
     *
     * @var \Two\Database\ORM\Model
     */
    protected $farParent;

    /**
     * La clé la plus proche de la relation.
     *
     * @var string
     */
    protected $firstKey;

    /**
     * La clé lointaine de la relation.
     *
     * @var string
     */
    protected $secondKey;

    /**
     * La clé locale sur la relation.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Créez une nouvelle instance de relation à plusieurs voies.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $farParent
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $parent, $firstKey, $secondKey, $localKey)
    {
        $this->localKey  = $localKey;
        $this->firstKey  = $firstKey;
        $this->secondKey = $secondKey;
        $this->farParent = $farParent;

        parent::__construct($query, $parent);
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
            $parentTable = $this->parent->getTable();

            $localValue = $this->farParent->{$this->localKey};

            $this->query->where($parentTable .'.' .$this->firstKey, '=', $localValue);
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
        $parentTable = $this->parent->getTable();

        $this->setJoin($query);

        $query->select(new Expression('count(*)'));

        $key = $this->wrap($parentTable .'.' .$this->firstKey);

        return $query->where($this->getHasCompareKey(), '=', new Expression($key));
    }

    /**
     * Définissez la clause de jointure sur la requête.
     *
     * @param  \Two\Database\ORM\Builder|null  $query
     * @return void
     */
    protected function setJoin(Builder $query = null)
    {
        $query = $query ?: $this->query;

        $foreignKey = $this->related->getTable().'.'.$this->related->getKeyName();

        $localKey = $this->parent->getTable().'.'.$this->secondKey;

        $query->join($this->parent->getTable(), $localKey, '=', $foreignKey);
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $table = $this->parent->getTable();

        $this->query->whereIn($table .'.' .$this->firstKey, $this->getKeys($models));
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
            $model->setRelation($relation, null);
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
            $key = $model->getKey();

            if (isset($dictionary[$key])) {
                $value = reset($dictionary[$key]);

                $model->setRelation($relation, $value);
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
        $dictionary = array();

        $foreign = 'related_' .$this->firstKey;

        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Obtenez les résultats de la relation.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->first();
    }

    /**
     * Exécutez la requête et obtenez le premier modèle associé.
     *
     * @param  array   $columns
     * @return mixed
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return (count($results) > 0) ? $results->first() : null;
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

        if (count($models) > 0) {
            $models = $this->query->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Définissez la clause select pour la requête de relation.
     *
     * @param  array  $columns
     * @return array
     */
    protected function getSelectColumns(array $columns = array('*'))
    {
        if ($columns == array('*')) {
            $columns = array($this->related->getTable().'.*');
        }

        return array_merge($columns, array($this->parent->getTable() .'.' .$this->firstKey .' as related_' .$this->firstKey));
    }

    /**
     * Obtenez la clé à comparer avec la clé parent dans la requête "has".s
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->farParent->getQualifiedKeyName();
    }
}

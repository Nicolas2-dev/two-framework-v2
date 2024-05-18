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
use Two\Database\Query\Expression;
use Two\Database\ORM\Collection;


class BelongsTo extends Relation
{
    /**
     * La clé étrangère du modèle parent.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * La clé associée sur le modèle parent.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * Le nom de la relation.
     *
     * @var string
     */
    protected $relation;

    /**
     * Créez une nouvelle instance de relation Appartient à.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey, $relation)
    {
        $this->otherKey = $otherKey;
        $this->relation = $relation;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Obtenez les résultats de la relation.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Définissez les contraintes de base sur la requête relationnelle.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            $this->query->where($table.'.'.$this->otherKey, '=', $this->parent->{$this->foreignKey});
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
        $query->select(new Expression('count(*)'));

        $otherKey = $this->wrap($query->getModel()->getTable().'.'.$this->otherKey);

        return $query->where($this->getQualifiedForeignKey(), '=', new Expression($otherKey));
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $key = $this->related->getTable().'.'.$this->otherKey;

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    /**
     * Rassemblez les clés d’un éventail de modèles associés.
     *
     * @param  array  $models
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = array();

        foreach ($models as $model) {
            if (! is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        if (count($keys) == 0) {
            return array(0);
        }

        return array_values(array_unique($keys));
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
        $foreign = $this->foreignKey;

        $other = $this->otherKey;

        $dictionary = array();

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($other)] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->$foreign])) {
                $model->setRelation($relation, $dictionary[$model->$foreign]);
            }
        }

        return $models;
    }

    /**
     * Associez l'instance de modèle au parent donné.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return \Two\Database\ORM\Model
     */
    public function associate(Model $model)
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->otherKey));

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Dissocier le modèle précédemment associé du parent donné.
     *
     * @return \Two\Database\ORM\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent->setRelation($this->relation, null);
    }

    /**
     * Mettez à jour le modèle parent sur la relation.
     *
     * @param  array  $attributes
     * @return mixed
     */
    public function update(array $attributes)
    {
        $instance = $this->getResults();

        return $instance->fill($attributes)->save();
    }

    /**
     * Obtenez la clé étrangère de la relation.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Obtenez la clé étrangère pleinement qualifiée de la relation.
     *
     * @return string
     */
    public function getQualifiedForeignKey()
    {
        return $this->parent->getTable().'.'.$this->foreignKey;
    }

    /**
     * Obtenez la clé associée à la relation.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->otherKey;
    }

    /**
     * Obtenez la clé associée pleinement qualifiée de la relation.
     *
     * @return string
     */
    public function getQualifiedOtherKeyName()
    {
        return $this->related->getTable().'.'.$this->otherKey;
    }

}

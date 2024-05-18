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


abstract class HasOneOrMany extends Relation
{
    /**
     * La clé étrangère du modèle parent.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * La clé locale du modèle parent.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Créez une nouvelle instance de relation comportant plusieurs.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Définissez les contraintes de base sur la requête relationnelle.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
        }
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
    }

    /**
     * Faites correspondre les résultats chargés avec impatience à leurs parents célibataires.
     *
     * @param  array   $models
     * @param  \Two\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchOne(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * Faites correspondre les résultats chargés avec impatience à leurs nombreux parents.
     *
     * @param  array   $models
     * @param  \Two\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchMany(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * Faites correspondre les résultats chargés avec impatience à leurs nombreux parents.
     *
     * @param  array   $models
     * @param  \Two\Database\ORM\Collection  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $value = $this->getRelationValue($dictionary, $key, $type);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * Obtenez la valeur d’une relation par un ou plusieurs types.
     *
     * @param  array   $dictionary
     * @param  string  $key
     * @param  string  $type
     * @return mixed
     */
    protected function getRelationValue(array $dictionary, $key, $type)
    {
        $value = $dictionary[$key];

        return $type == 'one' ? reset($value) : $this->related->newCollection($value);
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

        $foreign = $this->getPlainForeignKey();

        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Attachez une instance de modèle au modèle parent.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return \Two\Database\ORM\Model
     */
    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->getParentKey());

        return $model->save() ? $model : false;
    }

    /**
     * Attachez un tableau de modèles à l'instance parent.
     *
     * @param  array  $models
     * @return array
     */
    public function saveMany(array $models)
    {
        array_walk($models, array($this, 'save'));

        return $models;
    }

    /**
     * Créez une nouvelle instance du modèle associé.
     *
     * @param  array  $attributes
     * @return \Two\Database\ORM\Model
     */
    public function create(array $attributes)
    {
        $instance = $this->related->newInstance($attributes);

        $instance->setAttribute($this->getPlainForeignKey(), $this->getParentKey());

        $instance->save();

        return $instance;
    }

    /**
     * Créez un tableau de nouvelles instances du modèle associé.
     *
     * @param  array  $records
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = array();

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }

    /**
     * Effectuez une mise à jour sur tous les modèles associés.
     *
     * @param  array  $attributes
     * @return int
     */
    public function update(array $attributes)
    {
        if ($this->related->usesTimestamps()) {
            $attributes[$this->relatedUpdatedAt()] = $this->related->freshTimestamp();
        }

        return $this->query->update($attributes);
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
     * Obtenez la clé étrangère de la relation.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Obtenez la clé étrangère simple.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        $segments = explode('.', $this->getForeignKey());

        return $segments[count($segments) - 1];
    }

    /**
     * Obtenez la valeur clé de la clé locale du parent.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Obtenez le nom complet de la clé parent.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getTable().'.'.$this->localKey;
    }

}

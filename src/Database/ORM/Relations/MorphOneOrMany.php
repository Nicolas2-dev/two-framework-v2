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


abstract class MorphOneOrMany extends HasOneOrMany
{
    /**
     * Type de clé étrangère pour la relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * Le nom de classe du modèle parent.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Créez une nouvelle instance de relation comportant plusieurs.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $type, $id, $localKey)
    {
        $this->morphType = $type;

        $this->morphClass = $parent->getMorphClass();

        parent::__construct($query, $parent, $id, $localKey);
    }

    /**
     * Définissez les contraintes de base sur la requête relationnelle.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            parent::addConstraints();

            $this->query->where($this->morphType, $this->morphClass);
        }
    }

    /**
     * Obtenez la requête de nombre de relations.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Builder  $parent
     * @return \Two\Database\ORM\Builder
     */
    public function getRelationCountQuery(Builder $query, Builder $parent)
    {
        $query = parent::getRelationCountQuery($query, $parent);

        return $query->where($this->morphType, $this->morphClass);
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->morphType, $this->morphClass);
    }

    /**
     * Attachez une instance de modèle au modèle parent.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return \Two\Database\ORM\Model
     */
    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainMorphType(), $this->morphClass);

        return parent::save($model);
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

        $this->setForeignAttributesForCreate($instance);

        $instance->save();

        return $instance;
    }

    /**
     * Définissez l'ID étranger et le type pour créer un modèle associé.
     *
     * @param  \Two\Database\ORM\Model  $model
     * @return void
     */
    protected function setForeignAttributesForCreate(Model $model)
    {
        $property = $this->getPlainForeignKey();

        $model->{$property} = $this->getParentKey();

        //
        $property = $this->getPlainMorphType();

        $model->{$property} = $this->morphClass;
    }

    /**
     * Obtenez le nom du « type » de clé étrangère.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Obtenez le nom du type de morphing simple sans le tableau.
     *
     * @return string
     */
    public function getPlainMorphType()
    {
        return last(explode('.', $this->morphType));
    }

    /**
     * Obtenez le nom de classe du modèle parent.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass;
    }

}

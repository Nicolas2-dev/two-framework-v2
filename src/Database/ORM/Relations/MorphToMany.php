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


class MorphToMany extends BelongsToMany
{
    /**
     * Le type de relation polymorphe.
     *
     * @var string
     */
    protected $morphType;

    /**
     * Le nom de classe de la contrainte de type de morphing.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Indique si nous connectons l'inverse de la relation.
     *
     * Cela affecte principalement la contrainte morphClass.
     *
     * @var bool
     */
    protected $inverse;

    /**
     * Créez une nouvelle instance de relation comportant plusieurs.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relationName
     * @param  bool   $inverse
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $name, $table, $foreignKey, $otherKey, $relationName = null, $inverse = false)
    {
        $this->inverse = $inverse;

        $this->morphType = $name .'_type';

        $this->morphClass = $inverse ? $query->getModel()->getMorphClass() : $parent->getMorphClass();

        parent::__construct($query, $parent, $table, $foreignKey, $otherKey, $relationName);
    }

    /**
     * Définissez la clause Where pour la requête relationnelle.
     *
     * @return $this
     */
    protected function setWhere()
    {
        parent::setWhere();

        $this->query->where($this->table.'.'.$this->morphType, $this->morphClass);

        return $this;
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
        $query = parent::getRelationCountQuery($query, $parent);

        return $query->where($this->table.'.'.$this->morphType, $this->morphClass);
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

        $this->query->where($this->table.'.'.$this->morphType, $this->morphClass);
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
        $record = parent::createAttachRecord($id, $timed);

        return array_add($record, $this->morphType, $this->morphClass);
    }

    /**
     * Créez un nouveau générateur de requêtes pour le tableau croisé dynamique.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        $query = parent::newPivotQuery();

        return $query->where($this->morphType, $this->morphClass);
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
        $className = $this->using ?: MorphPivot::class;

        $pivot = new $className($this->parent, $attributes, $this->table, $exists);

        $pivot->setPivotKeys($this->foreignKey, $this->otherKey)
              ->setMorphType($this->morphType)
              ->setMorphClass($this->morphClass);

        return $pivot;
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
     * Obtenez le nom de classe du modèle parent.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass;
    }

}

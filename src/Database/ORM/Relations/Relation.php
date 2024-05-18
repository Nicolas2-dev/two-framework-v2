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

use Closure;


abstract class Relation
{
    /**
     * L'instance du générateur de requêtes ORM.
     *
     * @var \Two\Database\ORM\Builder
     */
    protected $query;

    /**
     * Instance de modèle parent.
     *
     * @var \Two\Database\ORM\Model
     */
    protected $parent;

    /**
     * L'instance de modèle associée.
     *
     * @var \Two\Database\ORM\Model
     */
    protected $related;

    /**
     * Indique si la relation ajoute des contraintes.
     *
     * @var bool
     */
    protected static $constraints = true;

    /**
     * Créez une nouvelle instance de relation.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @return void
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query  = $query;
        $this->parent = $parent;

        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Définissez les contraintes de base sur la requête relationnelle.
     *
     * @return void
     */
    abstract public function addConstraints();

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialisez la relation sur un ensemble de modèles.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Faites correspondre les résultats chargés avec impatience à leurs parents.
     *
     * @param  array   $models
     * @param  \Two\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    abstract public function match(array $models, Collection $results, $relation);

    /**
     * Obtenez les résultats de la relation.
     *
     * @return mixed
     */
    abstract public function getResults();

    /**
     * Obtenez la relation pour un chargement impatient.
     *
     * @return \Two\Database\ORM\Collection
     */
    public function getEager()
    {
        return $this->get();
    }

    /**
     * Touchez tous les modèles associés à la relation.
     *
     * @return void
     */
    public function touch()
    {
        $column = $this->getRelated()->getUpdatedAtColumn();

        $this->rawUpdate(array($column => $this->getRelated()->freshTimestampString()));
    }

    /**
     * Exécutez une mise à jour brute sur la requête de base.
     *
     * @param  array  $attributes
     * @return int
     */
    public function rawUpdate(array $attributes = array())
    {
        return $this->query->update($attributes);
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

        $key = $this->wrap($this->getQualifiedParentKeyName());

        return $query->where($this->getHasCompareKey(), '=', new Expression($key));
    }

    /**
     * Exécutez un rappel avec les contraintes désactivées sur la relation.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function noConstraints(Closure $callback)
    {
        static::$constraints = false;

        //
        $results = call_user_func($callback);

        static::$constraints = true;

        return $results;
    }

    /**
     * Obtenez toutes les clés primaires d’un éventail de modèles.
     *
     * @param  array   $models
     * @param  string  $key
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_unique(array_values(array_map(function($value) use ($key)
        {
            return $key ? $value->getAttribute($key) : $value->getKey();

        }, $models)));
    }

    /**
     * Obtenez la requête sous-jacente pour la relation.
     *
     * @return \Two\Database\ORM\Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Obtenez le générateur de requêtes de base pilotant le générateur ORM.
     *
     * @return \Two\Database\Query\Builder
     */
    public function getBaseQuery()
    {
        return $this->query->getQuery();
    }

    /**
     * Obtenez le modèle parent de la relation.
     *
     * @return \Two\Database\ORM\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Obtenez le nom complet de la clé parent.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getQualifiedKeyName();
    }

    /**
     * Obtenez le modèle associé de la relation.
     *
     * @return \Two\Database\ORM\Model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * Obtenez le nom de la colonne "créé à".
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Obtenez le nom de la colonne « mis à jour à ».
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Obtenez le nom de la colonne « mis à jour à » du modèle associé.
     *
     * @return string
     */
    public function relatedUpdatedAt()
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * Enveloppez la valeur donnée avec la grammaire de la requête parent.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        return $this->parent->newQueryWithoutScopes()->getQuery()->getGrammar()->wrap($value);
    }

    /**
     * Gérez les appels de méthode dynamique à la relation.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        if ($result === $this->query) return $this;

        return $result;
    }

}

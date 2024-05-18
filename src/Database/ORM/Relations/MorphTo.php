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
use Two\Collection\Collection as BaseCollection;


class MorphTo extends BelongsTo
{
    /**
     * Le type de relation polymorphe.
     *
     * @var string
     */
    protected $morphType;

    /**
     * Les modèles dont les relations sont chargées avec impatience.
     *
     * @var \Two\Database\ORM\Collection
     */
    protected $models;

    /**
     * Tous les modèles saisis par ID.
     *
     * @var array
     */
    protected $dictionary = array();

    /*
     * Indique si les instances de modèle supprimées de manière réversible doivent être récupérées.
     *
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Créez une nouvelle instance de relation Appartient à.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  \Two\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $type
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey, $type, $relation)
    {
        $this->morphType = $type;

        parent::__construct($query, $parent, $foreignKey, $otherKey, $relation);
    }

    /**
     * Définissez les contraintes pour un chargement hâtif de la relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->buildDictionary($this->models = Collection::make($models));
    }

    /**
     * Construisez un dictionnaire avec les modèles.
     *
     * @param  \Two\Database\ORM\Collection  $models
     * @return void
     */
    protected function buildDictionary(Collection $models)
    {
        foreach ($models as $model) {
            $morphType = $this->morphType;

            if (isset($model->{$morphType})) {
                $type = $model->{$morphType};

                $key = $model->getAttribute($this->foreignKey);

                $this->dictionary[$type][$key][] = $model;
            }
        }
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
        $this->parent->setAttribute($this->foreignKey, $model->getKey());

        $this->parent->setAttribute($this->morphType, $model->getMorphClass());

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Obtenez les résultats de la relation.
     *
     * Appelé via la méthode de chargement impatient du générateur de requêtes ORM.
     *
     * @return mixed
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models;
    }

    /**
     * Faites correspondre les résultats pour un type donné à leurs parents.
     *
     * @param  string  $type
     * @param  \Two\Database\ORM\Collection  $results
     * @return void
     */
    protected function matchToMorphParents($type, Collection $results)
    {
        foreach ($results as $result) {
            $key = $result->getKey();

            if (isset($this->dictionary[$type][$key])) {
                foreach ($this->dictionary[$type][$key] as $model) {
                    $model->setRelation($this->relation, $result);
                }
            }
        }
    }

    /**
     * Obtenez tous les résultats de relation pour un type.
     *
     * @param  string  $type
     * @return \Two\Database\ORM\Collection
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $key = $instance->getKeyName();

        $query = $instance->newQuery();

        $query = $this->useWithTrashed($query);

        // Collection
        return $query->whereIn($key, $this->gatherKeysByType($type)->all())->get();
    }

    /**
     * Rassemblez toutes les clés étrangères pour un type donné.
     *
     * @param  string  $type
     * @return array
     */
    protected function gatherKeysByType($type)
    {
        $foreign = $this->foreignKey;

        //
        $results = $this->dictionary[$type];

        return BaseCollection::make($results)->map(function($models) use ($foreign)
        {
            $model = head($models);

            return $model->{$foreign};

        })->unique();
    }

    /**
     * Créez une nouvelle instance de modèle par type.
     *
     * @param  string  $type
     * @return \Two\Database\ORM\Model
     */
    public function createModelByType($type)
    {
        return new $type;
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
     * Obtenez le dictionnaire utilisé par la relation.
     *
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }

    /**
     * Récupérer les instances de modèle supprimées de manière logicielle avec une requête
     *
     * @return $this
     */
    public function withTrashed()
    {
        $this->withTrashed = true;

        $this->query = $this->useWithTrashed($this->query);

        return $this;
    }

    /**
     * Renvoyez les modèles supprimés avec une requête si cela vous est demandé
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @return \Two\Database\ORM\Builder
     */
    protected function useWithTrashed(Builder $query)
    {
        if ($this->withTrashed && $query->getMacro('withTrashed') !== null) {
            return $query->withTrashed();
        }

        return $query;
    }

}

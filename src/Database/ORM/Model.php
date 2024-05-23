<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM;

use DateTime;
use ArrayAccess;
use LogicException;
use JsonSerializable;

use Two\Support\Str;
use Two\Events\Dispatcher;
use Two\Database\ORM\Relations\Pivot;
use Two\Database\ORM\Relations\HasOne;
use Two\Database\ORM\Relations\HasMany;
use Two\Database\ORM\Relations\MorphTo;
use Two\Database\ORM\Relations\MorphOne;
use Two\Database\ORM\Relations\Relation;
use Two\Database\ORM\Relations\BelongsTo;
use Two\Database\ORM\Relations\MorphMany;
use Two\Database\Contracts\ScopeInterface;
use Two\Database\ORM\Relations\MorphToMany;
use Two\Database\ORM\Relations\BelongsToMany;
use Two\Database\ORM\Relations\HasOneThrough;
use Two\Database\ORM\Relations\HasManyThrough;
use Two\Database\Query\Builder as QueryBuilder;
use Two\Application\Contracts\JsonableInterface;
use Two\Database\ORM\Relations\BelongsToThrough;
use Two\Application\Contracts\ArrayableInterface;
use Two\Database\Exception\ModelNotFoundException;
use Two\Database\Exception\MassAssignmentException;
use Two\Database\Contracts\ConnectionResolverInterface as Resolver;
use Two\Queue\Contracts\QueueableEntityInterface as QueueableEntity;

use Carbon\Carbon;


abstract class Model implements ArrayAccess, ArrayableInterface, JsonableInterface, JsonSerializable, QueueableEntity
{
    /**
     * Le nom de connexion pour le modèle.
     *
     * @var string
     */
    protected $connection;

    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table;

    /**
     * La clé primaire du modèle.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Le nombre de modèles à renvoyer pour la pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * Indique si les ID s’incrémentent automatiquement.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indique si le modèle doit être horodaté.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Les attributs du modèle.
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * L'état d'origine de l'attribut du modèle.
     *
     * @var array
     */
    protected $original = array();

    /**
     * Les relations chargées pour le modèle.
     *
     * @var array
     */
    protected $relations = array();

    /**
     * Les attributs qui doivent être masqués pour les tableaux.
     *
     * @var array
     */
    protected $hidden = array();

    /**
     * Les attributs qui doivent être visibles dans les tableaux.
     *
     * @var array
     */
    protected $visible = array();

    /**
     * Les accesseurs à ajouter au formulaire de tableau du modèle.
     *
     * @var array
     */
    protected $appends = array();

    /**
     * Attributs attribuables en masse.
     *
     * @var array
     */
    protected $fillable = array();

    /**
     * Les attributs qui ne sont pas attribuables en masse.
     *
     * @var array
     */
    protected $guarded = array('*');

    /**
     * Les attributs qui doivent être mutés en dates.
     *
     * @var array
     */
    protected $dates = array();

    /**
     * Les relations qui devraient être abordées sont sauvegardées.
     *
     * @var array
     */
    protected $touches = array();

    /**
     * Événements observables exposés par l'utilisateur
     *
     * @var array
     */
    protected $observables = array();

    /**
     * Les relations à charger avec impatience à chaque requête.
     *
     * @var array
     */
    protected $with = array();

    /**
     * Le nom de classe à utiliser dans les relations polymorphes.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Indique si le modèle existe.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Indique si les attributs sont en casse serpent sur les tableaux.
     *
     * @var bool
     */
    public static $snakeAttributes = true;

    /**
     * L'instance du résolveur de connexion.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * L'instance du répartiteur d'événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected static $dispatcher;

    /**
     * La gamme de modèles démarrés.
     *
     * @var array
     */
    protected static $booted = array();

    /**
     * Tableau des étendues globales sur le modèle.
     *
     * @var array
     */
    protected static $globalScopes = array();

    /**
     * Indique si toutes les affectations en masse sont activées.
     *
     * @var bool
     */
    protected static $unguarded = false;

    /**
     * Le cache des attributs mutés pour chaque classe.
     *
     * @var array
     */
    protected static $mutatorCache = array();

    /**
     * Les méthodes de relation plusieurs à plusieurs.
     *
     * @var array
     */
    public static $manyMethods = array('belongsToMany', 'morphToMany', 'morphedByMany');

    /**
     * Le nom de la colonne « créé à ».
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * Le nom de la colonne « mis à jour à ».
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Créez une nouvelle instance de modèle ORM.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = array())
    {
        $this->bootIfNotBooted();

        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Vérifiez si le modèle doit être démarré et si c'est le cas, faites-le.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        $class = get_class($this);

        if (! isset(static::$booted[$class])) {
            static::$booted[$class] = true;

            $this->fireModelEvent('booting', false);

            static::boot();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * La méthode de "booting" du modèle.
     *
     * @return void
     */
    protected static function boot()
    {
        $class = get_called_class();

        static::$mutatorCache[$class] = array();

        foreach (get_class_methods($class) as $method) {
            if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
                if (static::$snakeAttributes) $matches[1] = Str::snake($matches[1]);

                static::$mutatorCache[$class][] = lcfirst($matches[1]);
            }
        }

        static::bootTraits();
    }

    /**
     * Démarrez toutes les fonctionnalités de démarrage du modèle.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        foreach (class_uses_recursive(get_called_class()) as $trait) {
            if (method_exists(get_called_class(), $method = 'boot'.class_basename($trait))) {
                forward_static_call([get_called_class(), $method]);
            }
        }
    }

    /**
     * Enregistrez une nouvelle portée globale sur le modèle.
     *
     * @param  \Two\Database\Contracts\ScopeInterface  $scope
     * @return void
     */
    public static function addGlobalScope(ScopeInterface $scope)
    {
        static::$globalScopes[get_called_class()][get_class($scope)] = $scope;
    }

    /**
     * Déterminez si un modèle a une portée globale.
     *
     * @param  \Two\Database\Contracts\ScopeInterface  $scope
     * @return bool
     */
    public static function hasGlobalScope($scope)
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Obtenez une portée globale enregistrée avec le modèle.
     *
     * @param  \Two\Database\Contracts\ScopeInterface  $scope
     * @return \Two\Database\Contracts\ScopeInterface|null
     */
    public static function getGlobalScope($scope)
    {
        return array_first(static::$globalScopes[get_called_class()], function($key, $value) use ($scope)
        {
            return $scope instanceof $value;
        });
    }

    /**
     * Obtenez les étendues globales pour cette instance de classe.
     *
     * @return \Two\Database\Contracts\ScopeInterface[]
     */
    public function getGlobalScopes()
    {
        return array_get(static::$globalScopes, get_class($this), []);
    }

    /**
     * Enregistrez un observateur auprès du modèle.
     *
     * @param  object  $class
     * @return void
     */
    public static function observe($class)
    {
        $instance = new static;

        $className = is_string($class) ? $class : get_class($class);

        foreach ($instance->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event);
            }
        }
    }

    /**
     * Remplissez le modèle avec un tableau d'attributs.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Two\Database\Exception\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $key = $this->removeTableFromKey($key);

            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } else if ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * Remplissez le modèle avec un tableau d'attributs. Forcer l’affectation de masse.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        return static::unguarded(function () use ($attributes)
        {
            return $this->fill($attributes);
        });
    }

    /**
     * Obtenez les attributs remplissables d’un tableau donné.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes)
    {
        if (count($this->fillable) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    /**
     * Créez une nouvelle instance du modèle donné.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return static
     */
    public function newInstance($attributes = array(), $exists = false)
    {
        $model = new static((array) $attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Créez une nouvelle instance de modèle existante.
     *
     * @param  array  $attributes
     * @return static
     */
    public function newFromBuilder($attributes = array())
    {
        $instance = $this->newInstance(array(), true);

        $instance->setRawAttributes((array) $attributes, true);

        return $instance;
    }

    /**
     * Créez une collection de modèles à partir de tableaux simples.
     *
     * @param  array  $items
     * @param  string  $connection
     * @return \Two\Database\ORM\Collection
     */
    public static function hydrate(array $items, $connection = null)
    {
        $collection = with($instance = new static)->newCollection();

        foreach ($items as $item) {
            $model = $instance->newFromBuilder($item);

            if (! is_null($connection)) {
                $model->setConnection($connection);
            }

            $collection->push($model);
        }

        return $collection;
    }

    /**
     * Créez une collection de modèles à partir d'une requête brute.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  string  $connection
     * @return \Two\Database\ORM\Collection
     */
    public static function hydrateRaw($query, $bindings = array(), $connection = null)
    {
        $instance = new static;

        if (! is_null($connection)) {
            $instance->setConnection($connection);
        }

        $items = $instance->getConnection()->select($query, $bindings);

        return static::hydrate($items, $connection);
    }

    /**
     * Enregistrez un nouveau modèle et renvoyez l'instance.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function create(array $attributes)
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * Obtenez le premier enregistrement correspondant aux attributs ou créez-le.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function firstOrCreate(array $attributes)
    {
        if (! is_null($instance = static::where($attributes)->first())) {
            return $instance;
        }

        return static::create($attributes);
    }

    /**
     * Obtenez le premier enregistrement correspondant aux attributs ou instanciez-le.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function firstOrNew(array $attributes)
    {
        if (! is_null($instance = static::where($attributes)->first())) {
            return $instance;
        }

        return new static($attributes);
    }

    /**
     * Créez ou mettez à jour un enregistrement correspondant aux attributs et remplissez-le avec des valeurs.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static
     */
    public static function updateOrCreate(array $attributes, array $values = array())
    {
        $instance = static::firstOrNew($attributes);

        $instance->fill($values)->save();

        return $instance;
    }

    /**
     * Obtenez le premier modèle pour les attributs donnés.
     *
     * @param  array  $attributes
     * @return static|null
     */
    protected static function firstByAttributes($attributes)
    {
        return static::where($attributes)->first();
    }

    /**
     * Commencez à interroger le modèle.
     *
     * @return \Two\Database\ORM\Builder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    /**
     * Commencez à interroger le modèle sur une connexion donnée.
     *
     * @param  string  $connection
     * @return \Two\Database\ORM\Builder
     */
    public static function on($connection = null)
    {
        $instance = new static;

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Commencez à interroger le modèle sur la connexion en écriture.
     *
     * @return \Two\Database\Query\Builder
     */
    public static function onWriteConnection()
    {
        $instance = new static;

        return $instance->newQuery()->useWritePdo();
    }

    /**
     * Obtenez tous les modèles de la base de données.
     *
     * @param  array  $columns
     * @return \Two\Database\ORM\Collection|static[]
     */
    public static function all($columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->get($columns);
    }

    /**
     * Recherchez un modèle par sa clé primaire.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Two\Collection\Collection|static|null
     */
    public static function find($id, $columns = array('*'))
    {
        $instance = new static;

        if (is_array($id) && empty($id)) return $instance->newCollection();

        return $instance->newQuery()->find($id, $columns);
    }

    /**
     * Recherchez un modèle par sa clé primaire ou renvoyez une nouvelle statique.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Two\Collection\Collection|static
     */
    public static function findOrNew($id, $columns = array('*'))
    {
        if (! is_null($model = static::find($id, $columns))) return $model;

        return new static;
    }

    /**
     * Recherchez un modèle par sa clé primaire ou lancez une exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Two\Collection\Collection|static
     *
     * @throws \Two\Database\Exception\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = array('*'))
    {
        if (! is_null($model = static::find($id, $columns))) return $model;

        throw (new ModelNotFoundException)->setModel(get_called_class());
    }

    /**
     * Relations de charge impatientes sur le modèle.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $query = $this->newQuery()->with($relations);

        $query->eagerLoadRelations(array($this));

        return $this;
    }

    /**
     * Interroger un modèle avec un chargement rapide.
     *
     * @param  array|string  $relations
     * @return \Two\Database\ORM\Builder|static
     */
    public static function with($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $instance = new static;

        return $instance->newQuery()->with($relations);
    }

    /**
     * Définir une relation un-à-un.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Two\Database\ORM\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Définissez une relation de type « has-one-through ».
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @return \Two\Database\ORM\Relations\HasOneThrough
     */
    public function hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOneThrough((new $related)->newQuery(), $this, $through, $firstKey, $secondKey, $localKey);
    }

    /**
     * Définir une relation polymorphe un-à-un.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Two\Database\ORM\Relations\MorphOne
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = new $related;

        list($type, $id) = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphOne($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey);
    }

    /**
     * Définissez une relation inverse un-à-un ou plusieurs.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Two\Database\ORM\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false, 2);

            $relation = $caller['function'];
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_id';
        }

        $instance = new $related;

        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Définissez une relation de type « has-one-through ».
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @return \Two\Database\ORM\Relations\BelongsToThrough
     */
    public function belongsToThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new BelongsToThrough((new $related)->newQuery(), $this, $through, $firstKey, $secondKey, $localKey);
    }

    /**
     * Définir une relation polymorphe, inverse un-à-un ou plusieurs.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @return \Two\Database\ORM\Relations\MorphTo
     */
    public function morphTo($name = null, $type = null, $id = null)
    {
        if (is_null($name)) {
            list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $name = Str::snake($caller['function']);
        }

        list($type, $id) = $this->getMorphs($name, $type, $id);

        if (is_null($class = $this->$type)) {
            return new MorphTo(
                $this->newQuery(), $this, $id, null, $type, $name
            );
        } else {
            $instance = new $class;

            return new MorphTo(
                $instance->newQuery(), $this, $id, $instance->getKeyName(), $type, $name
            );
        }
    }

    /**
     * Définir une relation un-à-plusieurs.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Two\Database\ORM\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Définissez une relation à plusieurs.
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @return \Two\Database\ORM\Relations\HasManyThrough
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        return new HasManyThrough((new $related)->newQuery(), $this, $through, $firstKey, $secondKey);
    }

    /**
     * Définir une relation polymorphe un-à-plusieurs.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Two\Database\ORM\Relations\MorphMany
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = new $related;

        //
        list($type, $id) = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey);
    }

    /**
     * Définissez une relation plusieurs-à-plusieurs.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Two\Database\ORM\Relations\BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->getBelongsToManyCaller();
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        $query = $instance->newQuery();

        return new BelongsToMany($query, $this, $table, $foreignKey, $otherKey, $relation);
    }

    /**
     * Définir une relation polymorphe plusieurs-à-plusieurs.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  bool    $inverse
     * @return \Two\Database\ORM\Relations\MorphToMany
     */
    public function morphToMany($related, $name, $table = null, $foreignKey = null, $otherKey = null, $inverse = false)
    {
        $caller = $this->getBelongsToManyCaller();

        //
        $foreignKey = $foreignKey ?: $name.'_id';

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        //
        $query = $instance->newQuery();

        $table = $table ?: Str::plural($name);

        return new MorphToMany(
            $query, $this, $name, $table, $foreignKey,
            $otherKey, $caller, $inverse
        );
    }

    /**
     * Définissez une relation plusieurs-à-plusieurs polymorphe et inverse.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @return \Two\Database\ORM\Relations\MorphToMany
     */
    public function morphedByMany($related, $name, $table = null, $foreignKey = null, $otherKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $otherKey = $otherKey ?: $name.'_id';

        return $this->morphToMany($related, $name, $table, $foreignKey, $otherKey, true);
    }

    /**
     * Obtenez le nom de la relation appartenant à plusieurs.
     *
     * @return  string
     */
    protected function getBelongsToManyCaller()
    {
        $self = __FUNCTION__;

        $caller = array_first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function($key, $trace) use ($self)
        {
            $caller = $trace['function'];

            return (! in_array($caller, Model::$manyMethods) && $caller != $self);
        });

        return ! is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Obtenez le nom de la table de jointure pour une relation plusieurs-à-plusieurs.
     *
     * @param  string  $related
     * @return string
     */
    public function joiningTable($related)
    {
        $base = Str::snake(class_basename($this));

        $related = Str::snake(class_basename($related));

        $models = array($related, $base);

        //
        sort($models);

        return strtolower(implode('_', $models));
    }

    /**
     * Détruisez les modèles pour les identifiants donnés.
     *
     * @param  array|int  $ids
     * @return int
     */
    public static function destroy($ids)
    {
        $count = 0;

        $ids = is_array($ids) ? $ids : func_get_args();

        $instance = new static;

        //
        $key = $instance->getKeyName();

        foreach ($instance->whereIn($key, $ids)->get() as $model) {
            if ($model->delete()) $count++;
        }

        return $count;
    }

    /**
     * Supprimez le modèle de la base de données.
     *
     * @return bool|null
     * @throws \Exception
     */
    public function delete()
    {
        if (is_null($this->primaryKey)) {
            throw new \Exception("No primary key defined on model.");
        }

        if ($this->exists) {
            if ($this->fireModelEvent('deleting') === false) return false;

            $this->touchOwners();

            $this->performDeleteOnModel();

            $this->exists = false;

            $this->fireModelEvent('deleted', false);

            return true;
        }
    }

    /**
     * Forcer une suppression définitive sur un modèle supprimé de manière logicielle.
     *
     * Cette méthode empêche les développeurs d'exécuter forceDelete lorsque le trait est manquant.
     *
     * @return void
     */
    public function forceDelete()
    {
        return $this->delete();
    }

    /**
     * Effectuez la requête de suppression réelle sur cette instance de modèle.
     *
     * @return void
     */
    protected function performDeleteOnModel()
    {
        $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();
    }

    /**
     * Enregistrez un événement de modèle de sauvegarde auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function saving($callback)
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Enregistrez un événement de modèle enregistré auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function saved($callback)
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Enregistrez un événement de modèle de mise à jour auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function updating($callback)
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Enregistrez un événement de modèle mis à jour auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function updated($callback)
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Enregistrez un événement de création de modèle auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function creating($callback)
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Enregistrez un événement de modèle créé auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function created($callback)
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Enregistrez un événement de suppression de modèle auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function deleting($callback)
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Enregistrez un événement de modèle supprimé auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function deleted($callback)
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Supprimez tous les écouteurs d’événements du modèle.
     *
     * @return void
     */
    public static function flushEventListeners()
    {
        if (! isset(static::$dispatcher)) return;

        $instance = new static;

        foreach ($instance->getObservableEvents() as $event) {
            static::$dispatcher->forget("Two.database.orm.{$event}: ".get_called_class());
        }
    }

    /**
     * Enregistrez un événement modèle auprès du répartiteur.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
    {
        if (isset(static::$dispatcher)) {
            $name = get_called_class();

            static::$dispatcher->listen("Two.database.orm.{$event}: {$name}", $callback);
        }
    }

    /**
     * Obtenez les noms d’événements observables.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(
            array(
                'creating', 'created', 'updating', 'updated',
                'deleting', 'deleted', 'saving', 'saved',
                'restoring', 'restored',
            ),
            $this->observables
        );
    }

    /**
     * Définissez les noms d'événements observables.
     *
     * @param  array  $observables
     * @return void
     */
    public function setObservableEvents(array $observables)
    {
        $this->observables = $observables;
    }

    /**
     * Ajoutez un nom d'événement observable.
     *
     * @param  mixed  $observables
     * @return void
     */
    public function addObservableEvents($observables)
    {
        $observables = is_array($observables) ? $observables : func_get_args();

        $this->observables = array_unique(array_merge($this->observables, $observables));
    }

    /**
     * Supprimez un nom d'événement observable.
     *
     * @param  mixed  $observables
     * @return void
     */
    public function removeObservableEvents($observables)
    {
        $observables = is_array($observables) ? $observables : func_get_args();

        $this->observables = array_diff($this->observables, $observables);
    }

    /**
     * Incrémentez la valeur d'une colonne d'un montant donné.
     *
     * @param  string  $column
     * @param  int     $amount
     * @return int
     */
    protected function increment($column, $amount = 1)
    {
        return $this->incrementOrDecrement($column, $amount, 'increment');
    }

    /**
     * Décrémenter la valeur d'une colonne d'un montant donné.
     *
     * @param  string  $column
     * @param  int     $amount
     * @return int
     */
    protected function decrement($column, $amount = 1)
    {
        return $this->incrementOrDecrement($column, $amount, 'decrement');
    }

    /**
     * Exécutez la méthode d'incrémentation ou de décrémentation sur le modèle.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  string  $method
     * @return int
     */
    protected function incrementOrDecrement($column, $amount, $method)
    {
        $query = $this->newQuery();

        if (! $this->exists) {
            return $query->{$method}($column, $amount);
        }

        $this->incrementOrDecrementAttributeValue($column, $amount, $method);

        return $query->where($this->getKeyName(), $this->getKey())->{$method}($column, $amount);
    }

    /**
     * Incrémentez la valeur de l'attribut sous-jacent et synchronisez-la avec l'original.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  string  $method
     * @return void
     */
    protected function incrementOrDecrementAttributeValue($column, $amount, $method)
    {
        $this->{$column} = $this->{$column} + (($method == 'increment') ? $amount : $amount * -1);

        $this->syncOriginalAttribute($column);
    }

    /**
     * Mettez à jour le modèle dans la base de données.
     *
     * @param  array  $attributes
     * @return bool|int
     */
    public function update(array $attributes = array())
    {
        if (! $this->exists) {
            return $this->newQuery()->update($attributes);
        }

        return $this->fill($attributes)->save();
    }

    /**
     * Enregistrez le modèle et toutes ses relations.
     *
     * @return bool
     */
    public function push()
    {
        if (! $this->save()) return false;

        foreach ($this->relations as $models) {
            foreach (Collection::make($models) as $model) {
                if (! $model->push()) return false;
            }
        }

        return true;
    }

    /**
     * Enregistrez le modèle dans la base de données.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = array())
    {
        $query = $this->newQueryWithoutScopes();

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->performUpdate($query, $options);
        } else {
            $saved = $this->performInsert($query, $options);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Terminez le traitement sur une opération de sauvegarde réussie.
     *
     * @param  array  $options
     * @return void
     */
    protected function finishSave(array $options)
    {
        $this->fireModelEvent('saved', false);

        $this->syncOriginal();

        if (array_get($options, 'touch', true)) {
            $this->touchOwners();
        }
    }

    /**
     * Effectuez une opération de mise à jour du modèle.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  array  $options
     * @return bool|null
     */
    protected function performUpdate(Builder $query, array $options = [])
    {
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            if ($this->timestamps && array_get($options, 'timestamps', true)) {
                $this->updateTimestamps();
            }

            $dirty = $this->getDirty();

            if (count($dirty) > 0) {
                $this->setKeysForSaveQuery($query)->update($dirty);

                $this->fireModelEvent('updated', false);
            }
        }

        return true;
    }

    /**
     * Effectuez une opération d'insertion de modèle.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  array  $options
     * @return bool
     */
    protected function performInsert(Builder $query, array $options = [])
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps && array_get($options, 'timestamps', true)) {
            $this->updateTimestamps();
        }

        $attributes = $this->attributes;

        if ($this->incrementing) {
            $this->insertAndSetId($query, $attributes);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insérez les attributs donnés et définissez l'ID sur le modèle.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @param  array  $attributes
     * @return void
     */
    protected function insertAndSetId(Builder $query, $attributes)
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Touchez les relations de propriété du modèle.
     *
     * @return void
     */
    public function touchOwners()
    {
        foreach ($this->touches as $relation) {
            $this->$relation()->touch();

            if (! is_null($this->$relation)) {
                $this->$relation->touchOwners();
            }
        }
    }

    /**
     * Déterminez si le modèle touche une relation donnée.
     *
     * @param  string  $relation
     * @return bool
     */
    public function touches($relation)
    {
        return in_array($relation, $this->touches);
    }

    /**
     * Déclenche l'événement donné pour le modèle.
     *
     * @param  string  $event
     * @param  bool    $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        //
        $event = "Two.database.orm.{$event}: ".get_class($this);

        $method = $halt ? 'until' : 'dispatch';

        return call_user_func(array(static::$dispatcher,$method), $event, $this);
    }

    /**
     * Définissez les clés pour une requête de mise à jour de sauvegarde.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @return \Two\Database\ORM\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Obtenez la valeur de la clé primaire pour une requête de sauvegarde.
     *
     * @return mixed
     */
    protected function getKeyForSaveQuery()
    {
        if (isset($this->original[$this->getKeyName()])) {
            return $this->original[$this->getKeyName()];
        }

        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Mettez à jour l'horodatage de mise à jour du modèle.
     *
     * @return bool
     */
    public function touch()
    {
        if (! $this->timestamps) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Mettez à jour les horodatages de création et de mise à jour.
     *
     * @return void
     */
    protected function updateTimestamps()
    {
        $time = $this->freshTimestamp();

        if (! is_null($column = $this->getUpdatedAtColumn()) && ! $this->isDirty($column)) {
            $this->setAttribute($column, $time);
        }

        if (! $this->exists && ! is_null($column = $this->getCreatedAtColumn()) && ! $this->isDirty($column)) {
            $this->setAttribute($column, $time);
        }
    }

    /**
     * Définissez la valeur de l'attribut "créé à".
     *
     * @param  mixed  $value
     * @return void
     */
    public function setCreatedAt($value)
    {
        if (! is_null($column = $this->getCreatedAtColumn())) {
            $this->setAttribute($column, $value);
        }
    }

    /**
     * Définissez la valeur de l'attribut "mis à jour à".
     *
     * @param  mixed  $value
     * @return void
     */
    public function setUpdatedAt($value)
    {
        if (! is_null($column = $this->getUpdatedAtColumn())) {
            $this->setAttribute($column, $value);
        }
    }

    /**
     * Obtenez le nom de la colonne "créé à".
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Obtenez le nom de la colonne « mis à jour à ».
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Obtenez un nouvel horodatage pour le modèle.
     *
     * @return \Carbon\Carbon
     */
    public function freshTimestamp()
    {
        return new Carbon;
    }

    /**
     * Obtenez un nouvel horodatage pour le modèle.
     *
     * @return string
     */
    public function freshTimestampString()
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Obtenez un nouveau générateur de requêtes pour la table du modèle.
     *
     * @return \Two\Database\ORM\Builder
     */
    public function newQuery()
    {
        $builder = $this->newQueryWithoutScopes();

        return $this->applyGlobalScopes($builder);
    }

    /**
     * Obtenez une nouvelle instance de requête sans portée donnée.
     *
     * @param  \Two\Database\Contracts\ScopeInterface  $scope
     * @return \Two\Database\ORM\Builder
     */
    public function newQueryWithoutScope($scope)
    {
        $this->getGlobalScope($scope)->remove($builder = $this->newQuery(), $this);

        return $builder;
    }

    /**
     * Obtenez un nouveau générateur de requêtes qui n’a aucune étendue globale.
     *
     * @return \Two\Database\ORM\Builder|static
     */
    public function newQueryWithoutScopes()
    {
        $builder = $this->newQueryBuilder(
            $this->newBaseQueryBuilder()
        );

        return $builder->setModel($this)->with($this->with);
    }

    /**
     * Appliquez toutes les portées globales à un générateur ORM.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return \Two\Database\ORM\Builder
     */
    public function applyGlobalScopes($builder)
    {
        foreach ($this->getGlobalScopes() as $scope) {
            $scope->apply($builder, $this);
        }

        return $builder;
    }

    /**
     * Supprimez toutes les étendues globales d'un générateur ORM.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return \Two\Database\ORM\Builder
     */
    public function removeGlobalScopes($builder)
    {
        foreach ($this->getGlobalScopes() as $scope) {
            $scope->remove($builder, $this);
        }

        return $builder;
    }

    /**
     * Créez un nouveau générateur de requêtes ORM pour le modèle.
     *
     * @param  \Two\Database\Query\Builder $query
     * @return \Two\Database\ORM\Builder|static
     */
    public function newQueryBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Obtenez une nouvelle instance du générateur de requêtes pour la connexion.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Créez une nouvelle instance de collection ORM.
     *
     * @param  array  $models
     * @return \Two\Database\ORM\Collection
     */
    public function newCollection(array $models = array())
    {
        return new Collection($models);
    }

    /**
     * Créez une nouvelle instance de modèle pivot.
     *
     * @param  \Two\Database\ORM\Model  $parent
     * @param  array   $attributes
     * @param  string  $table
     * @param  bool    $exists
     * @param  string|null  $using
     * @return \Two\Database\ORM\Relations\Pivot
     */
    public function newPivot(Model $parent, array $attributes, $table, $exists, $using = null)
    {
        $className = $using ?: Pivot::class;

        return new $className($parent, $attributes, $table, $exists);
    }

    /**
     * Récupère la table associée au modèle.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $name = class_basename($this);

        return str_replace('\\', '', Str::snake(Str::plural($name)));
    }

    /**
     * Définissez la table associée au modèle.
     *
     * @param  string  $table
     * @return void
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * Obtenez la valeur de la clé primaire du modèle.
     *
     * @return mixed
     */
    public function getKey()
    {
        $key = $this->getKeyName();

        return $this->getAttribute($key);
    }

    /**
     * Obtenez la clé primaire du modèle.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Définissez la clé primaire du modèle.
     *
     * @param  string  $key
     * @return void
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;
    }

    /**
     * Obtenez le nom de la clé qualifiée de la table.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() .'.' .$this->getKeyName();
    }

    /**
     * Obtenez l'identité pouvant être mise en file d'attente pour l'entité.
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        return $this->getKey();
    }

    /**
     * Déterminez si le modèle utilise des horodatages.
     *
     * @return bool
     */
    public function usesTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * Obtenez les colonnes de relations polymorphes.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @return array
     */
    protected function getMorphs($name, $type, $id)
    {
        $type = $type ?: $name .'_type';

        $id = $id ?: $name .'_id';

        return array($type, $id);
    }

    /**
     * Obtenez le nom de la classe pour les relations polymorphes.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass ?: get_class($this);
    }

    /**
     * Obtenez le nombre de modèles à renvoyer par page.
     *
     * @return int
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * Définissez le nombre de modèles à renvoyer par page.
     *
     * @param  int   $perPage
     * @return void
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * Obtenez le nom de clé étrangère par défaut pour le modèle.
     *
     * @return string
     */
    public function getForeignKey()
    {
        $name = class_basename($this);

        return sprintf('%s_id', Str::snake($name));
    }

    /**
     * Obtenez les attributs cachés du modèle.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Définissez les attributs masqués du modèle.
     *
     * @param  array  $hidden
     * @return void
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Définissez les attributs visibles du modèle.
     *
     * @param  array  $visible
     * @return void
     */
    public function setVisible(array $visible)
    {
        $this->visible = $visible;
    }

    /**
     * Définissez les accesseurs à ajouter aux tableaux de modèles.
     *
     * @param  array  $appends
     * @return void
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;
    }

    /**
     * Obtenez les attributs à remplir pour le modèle.
     *
     * @return array
     */
    public function getFillable()
    {
        return $this->fillable;
    }

    /**
     * Définissez les attributs remplissables pour le modèle.
     *
     * @param  array  $fillable
     * @return $this
     */
    public function fillable(array $fillable)
    {
        $this->fillable = $fillable;

        return $this;
    }

    /**
     * obtenez les attributs gardés pour le modèle.
     *
     * @return array
     */
    public function getGuarded()
    {
        return $this->guarded;
    }

    /**
     * Définissez les attributs gardés pour le modèle.
     *
     * @param  array  $guarded
     * @return $this
     */
    public function guard(array $guarded)
    {
        $this->guarded = $guarded;

        return $this;
    }

    /**
     * Désactivez toutes les restrictions attribuables en masse.
     *
     * @return void
     */
    public static function unguard()
    {
        static::$unguarded = true;
    }

    /**
     * Activez les restrictions d’affectation en masse.
     *
     * @return void
     */
    public static function reguard()
    {
        static::$unguarded = false;
    }

    /**
     * Exécutez l'appelable donné sans être surveillé.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function unguarded(callable $callback)
    {
        if (static::$unguarded) {
            return call_user_func($callback);
        }

        static::unguard();

        try {
            return call_user_func($callback);
        }
        finally {
            static::reguard();
        }
    }

    /**
     * Réglez « unguard » sur un état donné.
     *
     * @param  bool  $state
     * @return void
     */
    public static function setUnguardState($state)
    {
        static::$unguarded = $state;
    }

    /**
     * Déterminez si l’attribut donné peut être attribué en masse.
     *
     * @param  string  $key
     * @return bool
     */
    public function isFillable($key)
    {
        if (static::$unguarded) {
            return true;
        } else if (in_array($key, $this->fillable)) {
            return true;
        } else if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && ! Str::startsWith($key, '_');
    }

    /**
     * Déterminez si la clé donnée est gardée.
     *
     * @param  string  $key
     * @return bool
     */
    public function isGuarded($key)
    {
        return in_array($key, $this->guarded) || ($this->guarded == array('*'));
    }

    /**
     * Déterminez si le modèle est totalement gardé.
     *
     * @return bool
     */
    public function totallyGuarded()
    {
        return (count($this->fillable) == 0) && ($this->guarded == array('*'));
    }

    /**
     * Supprime le nom de la table d'une clé donnée.
     *
     * @param  string  $key
     * @return string
     */
    protected function removeTableFromKey($key)
    {
        if (! Str::contains($key, '.')) {
            return $key;
        }

        return last(explode('.', $key));
    }

    /**
     * Enregistrez les relations touchées.
     *
     * @return array
     */
    public function getTouchedRelations()
    {
        return $this->touches;
    }

    /**
     * Définissez les relations qui sont touchées lors de la sauvegarde.
     *
     * @param  array  $touches
     * @return void
     */
    public function setTouchedRelations(array $touches)
    {
        $this->touches = $touches;
    }

    /**
     * Obtenez la valeur indiquant si les ID sont incrémentés.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return $this->incrementing;
    }

    /**
     * Définissez si les ID sont incrémentés.
     *
     * @param  bool  $value
     * @return void
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;
    }

    /**
     * Convertissez l'instance de modèle en JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convertissez l'objet en quelque chose de sérialisable JSON.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convertissez l'instance de modèle en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return array_merge($attributes, $this->relationsToArray());
    }

    /**
     * Convertissez les attributs du modèle en tableau.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->getArrayableAttributes();

        foreach ($this->getDates() as $key) {
            if (! isset($attributes[$key])) {
                continue;
            }

            $attributes[$key] = (string) $this->asDateTime($attributes[$key]);
        }

        foreach ($this->getMutatedAttributes() as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray($key, $attributes[$key]);
        }

        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Obtenez un tableau d'attributs de tous les attributs pouvant être mis en tableau.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Obtenez toutes les valeurs ajoutables qui peuvent être rangées.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (! count($this->appends)) {
            return array();
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Obtenez les relations du modèle sous forme de tableau.
     *
     * @return array
     */
    public function relationsToArray()
    {
        $attributes = array();

        foreach ($this->getArrayableRelations() as $key => $value) {
            if (in_array($key, $this->hidden)) {
                continue;
            }

            if ($value instanceof ArrayableInterface) {
                $relation = $value->toArray();
            } else if (is_null($value)) {
                $relation = $value;
            }

            if (static::$snakeAttributes) {
                $key = Str::snake($key);
            }

            if (isset($relation) || is_null($value)) {
                $attributes[$key] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    /**
     * Obtenez un tableau d'attributs de toutes les relations pouvant être rangées.
     *
     * @return array
     */
    protected function getArrayableRelations()
    {
        return $this->getArrayableItems($this->relations);
    }

    /**
     * Obtenez un tableau d'attributs de toutes les valeurs pouvant être rangées.
     *
     * @param  array  $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->visible) > 0) {
            return array_intersect_key($values, array_flip($this->visible));
        }

        return array_diff_key($values, array_flip($this->hidden));
    }

    /**
     * Obtenez un attribut du modèle.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Si la clé fait référence à un attribut, renvoie sa valeur simple à partir du modèle.
        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        // Si la clé existe déjà dans le tableau des relations...
        else if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Si « l'attribut » existe en tant que méthode sur le modèle, il s'agit d'une relation.
        $camelKey = Str::camel($key);

        if (method_exists($this, $camelKey)) {
            return $this->getRelationshipFromMethod($key, $camelKey);
        }
    }

    /**
     * Obtenez un attribut simple (pas une relation).
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        } else if (in_array($key, $this->getDates()) && ! empty($value)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Récupère un attribut du tableau $attributes.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Obtenez une valeur de relation à partir d’une méthode.
     *
     * @param  string  $key
     * @param  string  $camelKey
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function getRelationshipFromMethod($key, $camelKey)
    {
        $relation = call_user_func(array($this, $camelKey));

        if (! $relation instanceof Relation) {
            throw new LogicException('Relationship method must return an object of type [Two\Database\ORM\Relations\Relation]');
        }

        return $this->relations[$key] = $relation->getResults();
    }

    /**
     * Déterminez si un mutateur get existe pour un attribut.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        $method = 'get' .Str::studly($key) .'Attribute';

        return method_exists($this, $method);
    }

    /**
     * Obtenez la valeur d'un attribut en utilisant son mutateur.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        $method = 'get' .Str::studly($key) .'Attribute';

        return call_user_func(array($this, $method), $value);
    }

    /**
     * Obtenez la valeur d'un attribut en utilisant son mutateur pour la conversion de tableau.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        $value = $this->mutateAttribute($key, $value);

        if ($value instanceof ArrayableInterface) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Définissez un attribut donné sur le modèle.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set' .Str::studly($key) .'Attribute';

            return call_user_func(array($this, $method), $value);
        } else if (in_array($key, $this->getDates()) && $value) {
            $value = $this->fromDateTime($value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Déterminez si un mutateur défini existe pour un attribut.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        $method = 'set' .Str::studly($key) .'Attribute';

        return method_exists($this, $method);
    }

    /**
     * Obtenez les attributs qui doivent être convertis en dates.
     *
     * @return array
     */
    public function getDates()
    {
        $defaults = array(static::CREATED_AT, static::UPDATED_AT);

        return array_merge($this->dates, $defaults);
    }

    /**
     * Convertissez un DateTime en une chaîne stockable.
     *
     * @param  \DateTime|int  $value
     * @return string
     */
    public function fromDateTime($value)
    {
        $format = $this->getDateFormat();

        if ($value instanceof DateTime) {
            //
        } else if (is_numeric($value)) {
            $value = Carbon::createFromTimestamp($value);
        } else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            $value = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } else {
            $value = Carbon::createFromFormat($format, $value);
        }

        return $value->format($format);
    }

    /**
     * Renvoie un horodatage en tant qu'objet DateTime.
     *
     * @param  mixed  $value
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        } else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } else if (! $value instanceof DateTime) {
            $format = $this->getDateFormat();

            return Carbon::createFromFormat($format, $value);
        }

        return Carbon::instance($value);
    }

    /**
     * Obtenez le format des dates stockées dans la base de données.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return $this->getConnection()->getQueryGrammar()->getDateFormat();
    }

    /**
     * Clonez le modèle dans une nouvelle instance inexistante.
     *
     * @param  array  $except
     * @return \Two\Database\ORM\Model
     */
    public function replicate(array $except = null)
    {
        $except = $except ?: array(
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        );

        $attributes = array_except($this->attributes, $except);

        with($instance = new static)->setRawAttributes($attributes);

        return $instance->setRelations($this->relations);
    }

    /**
     * Obtenez tous les attributs actuels du modèle.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Définissez le tableau des attributs du modèle. Aucune vérification n'est effectuée.
     *
     * @param  array  $attributes
     * @param  bool   $sync
     * @return void
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) $this->syncOriginal();
    }

    /**
     * Obtenez les valeurs d'attribut d'origine du modèle.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return array
     */
    public function getOriginal($key = null, $default = null)
    {
        return array_get($this->original, $key, $default);
    }

    /**
     * Synchronisez les attributs d'origine avec les attributs actuels.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Synchronisez un seul attribut d'origine avec sa valeur actuelle.
     *
     * @param  string  $attribute
     * @return $this
     */
    public function syncOriginalAttribute($attribute)
    {
        $this->original[$attribute] = $this->attributes[$attribute];

        return $this;
    }

    /**
     * Déterminez si le modèle ou les attributs donnés ont été modifiés.
     *
     * @param  array|string|null  $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        } else if (! is_array($attributes)) {
            $attributes = func_get_args();
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtenez les attributs qui ont été modifiés depuis la dernière synchronisation.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = array();

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } else if (($value !== $this->original[$key]) && ! $this->originalIsNumericallyEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Determine if the new and old values for a given key are numerically equivalent.
     *
     * @param  string  $key
     * @return bool
     */
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];

        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Obtenez toutes les relations chargées pour l'instance.
     *
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Obtenez une relation spécifiée.
     *
     * @param  string  $relation
     * @return mixed
     */
    public function getRelation($relation)
    {
        return $this->relations[$relation];
    }

    /**
     * Définissez la relation spécifique dans le modèle.
     *
     * @param  string  $relation
     * @param  mixed   $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Définissez l'ensemble du tableau de relations sur le modèle.
     *
     * @param  array  $relations
     * @return $this
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Obtenez la connexion à la base de données pour le modèle.
     *
     * @return \Two\Database\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->connection);
    }

    /**
     * Obtenez le nom de connexion actuel pour le modèle.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Définissez la connexion associée au modèle.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Résolvez une instance de connexion.
     *
     * @param  string  $connection
     * @return \Two\Database\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Obtenez l’instance du résolveur de connexion.
     *
     * @return \Two\Database\Contracts\ConnectionResolverInterface
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Définissez l’instance du résolveur de connexion.
     *
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $resolver
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Désactivez le résolveur de connexion pour les modèles.
     *
     * @return void
     */
    public static function unsetConnectionResolver()
    {
        static::$resolver = null;
    }

    /**
     * Obtenez l’instance du répartiteur d’événements.
     *
     * @return \Two\Events\Dispatcher
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Définissez l’instance du répartiteur d’événements.
     *
     * @param  \Two\Events\Dispatcher  $dispatcher
     * @return void
     */
    public static function setEventDispatcher(Dispatcher $dispatcher)
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Désactivez le répartiteur d'événements pour les modèles.
     *
     * @return void
     */
    public static function unsetEventDispatcher()
    {
        static::$dispatcher = null;
    }

    /**
     * Obtenez les attributs mutés pour une instance donnée.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = get_class($this);

        if (isset(static::$mutatorCache[$class])) {
            return static::$mutatorCache[$class];
        }

        return array();
    }

    /**
     * Récupérez dynamiquement les attributs sur le modèle.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Définir dynamiquement les attributs sur le modèle.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Déterminez si l'attribut donné existe.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Obtenez la valeur pour un décalage donné.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Définissez la valeur pour un décalage donné.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Supprimez la valeur d'un décalage donné.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->$offset);
    }

    /**
     * Déterminez si un attribut existe sur le modèle.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return ((isset($this->attributes[$key]) || isset($this->relations[$key])) ||
                ($this->hasGetMutator($key) && ! is_null($this->getAttributeValue($key))));
    }

    /**
     * Désactivez un attribut sur le modèle.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * Gérez les appels de méthode dynamique dans la méthode.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, array('increment', 'decrement'))) {
            return call_user_func_array(array($this, $method), $parameters);
        }

        $query = $this->newQuery();

        return call_user_func_array(array($query, $method), $parameters);
    }

    /**
     * Gérez les appels de méthode statique dynamique dans la méthode.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array(array($instance, $method), $parameters);
    }

    /**
     * Convertissez le modèle en sa représentation sous forme de chaîne.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Lorsqu'un modèle n'est pas sérialisé, vérifiez s'il doit être démarré.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->bootIfNotBooted();
    }

}

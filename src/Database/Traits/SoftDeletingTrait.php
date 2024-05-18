<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Traits;

use Two\Database\ORM\SoftDeletingScope;


trait SoftDeletingTrait
{
    /**
     * Indique si le modèle est actuellement en cours de suppression forcée.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * Démarrez le trait de suppression logicielle pour un modèle.
     *
     * @return void
     */
    public static function bootSoftDeletingTrait()
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * Forcer une suppression définitive sur un modèle supprimé de manière logicielle.
     *
     * @return void
     */
    public function forceDelete()
    {
        $this->forceDeleting = true;

        $this->delete();

        $this->forceDeleting = false;
    }

    /**
     * Effectuez la requête de suppression réelle sur cette instance de modèle.
     *
     * @return void
     */
    protected function performDeleteOnModel()
    {
        if ($this->forceDeleting) {
            return $this->withTrashed()->where($this->getKeyName(), $this->getKey())->forceDelete();
        }

        return $this->runSoftDelete();
    }

    /**
     * Effectuez la requête de suppression réelle sur cette instance de modèle.
     *
     * @return void
     */
    protected function runSoftDelete()
    {
        $query = $this->newQuery()->where($this->getKeyName(), $this->getKey());

        $this->setAttribute($column = $this->getDeletedAtColumn(), $time = $this->freshTimestamp());

        $query->update(array($column => $this->fromDateTime($time)));
    }

    /**
     * Restaurez une instance de modèle supprimée de manière réversible.
     *
     * @return bool|null
     */
    public function restore()
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);

        //
        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Déterminez si l’instance de modèle a été supprimée de manière réversible.
     *
     * @return bool
     */
    public function trashed()
    {
        return ! is_null($this->getAttribute($this->getDeletedAtColumn()));
    }

    /**
     * Obtenez un nouveau générateur de requêtes qui inclut des suppressions logicielles.
     *
     * @return \Two\Database\ORM\Builder|static
     */
    public static function withTrashed()
    {
        return (new static)->newQueryWithoutScope(new SoftDeletingScope);
    }

    /**
     * Obtenez un nouveau générateur de requêtes qui inclut uniquement les suppressions logicielles.
     *
     * @return \Two\Database\ORM\Builder|static
     */
    public static function onlyTrashed()
    {
        $instance = new static;

        $column = $instance->getQualifiedDeletedAtColumn();

        return $instance->newQueryWithoutScope(new SoftDeletingScope)->whereNotNull($column);
    }

    /**
     * Enregistrez un événement de modèle de restauration auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restoring($callback)
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Enregistrez un événement de modèle restauré auprès du répartiteur.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restored($callback)
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Obtenez le nom de la colonne « à supprimé ».
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Obtenez la colonne complète « supprimé à ».
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getTable() .'.' .$this->getDeletedAtColumn();
    }

}

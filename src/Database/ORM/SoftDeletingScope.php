<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM;

use Two\Database\Contracts\ScopeInterface;


class SoftDeletingScope implements ScopeInterface
{
    /**
     * Toutes les extensions à ajouter au constructeur.
     *
     * @var array
     */
    protected $extensions = array('ForceDelete', 'Restore', 'WithTrashed', 'OnlyTrashed');


    /**
     * Appliquez la portée à un générateur de requêtes ORM donné.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    public function apply(Builder $builder)
    {
        $model = $builder->getModel();

        $builder->whereNull($model->getQualifiedDeletedAtColumn());

        $this->extend($builder);
    }

    /**
     * Supprimez la portée du générateur de requêtes ORM donné.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    public function remove(Builder $builder)
    {
        $column = $builder->getModel()->getQualifiedDeletedAtColumn();

        $query = $builder->getQuery();

        foreach ((array) $query->wheres as $key => $where) {
            if ($this->isSoftDeleteConstraint($where, $column)) {
                unset($query->wheres[$key]);

                $query->wheres = array_values($query->wheres);
            }
        }
    }

    /**
     * Étendez le générateur de requêtes avec les fonctions nécessaires.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function(Builder $builder)
        {
            $column = $this->getDeletedAtColumn($builder);

            return $builder->update(array(
                $column => $builder->getModel()->freshTimestampString()
            ));
        });
    }

    /**
     * Obtenez la colonne "supprimé à" pour le constructeur.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return string
     */
    protected function getDeletedAtColumn(Builder $builder)
    {
        if (count($builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        } else {
            return $builder->getModel()->getDeletedAtColumn();
        }
    }

    /**
     * Ajoutez l’extension de suppression forcée au générateur.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    protected function addForceDelete(Builder $builder)
    {
        $builder->macro('forceDelete', function(Builder $builder)
        {
            return $builder->getQuery()->delete();
        });
    }

    /**
     * Ajoutez l'extension de restauration au générateur.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    protected function addRestore(Builder $builder)
    {
        $builder->macro('restore', function(Builder $builder)
        {
            $builder->withTrashed();

            return $builder->update(array($builder->getModel()->getDeletedAtColumn() => null));
        });
    }

    /**
     * Ajoutez l'extension with-trashed au constructeur.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    protected function addWithTrashed(Builder $builder)
    {
        $builder->macro('withTrashed', function(Builder $builder)
        {
            $this->remove($builder);

            return $builder;
        });
    }

    /**
     * Ajoutez l'extension uniquement mise à la corbeille au générateur.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    protected function addOnlyTrashed(Builder $builder)
    {
        $builder->macro('onlyTrashed', function(Builder $builder)
        {
            $this->remove($builder);

            $builder->getQuery()->whereNotNull($builder->getModel()->getQualifiedDeletedAtColumn());

            return $builder;
        });
    }

    /**
     * Déterminez si la clause Where donnée est une contrainte de suppression logicielle.
     *
     * @param  array   $where
     * @param  string  $column
     * @return bool
     */
    protected function isSoftDeleteConstraint(array $where, $column)
    {
        return ($where['type'] == 'Null') && ($where['column'] == $column);
    }

}

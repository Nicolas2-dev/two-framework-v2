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


class Pivot extends Model
{
    /**
     * Le modèle parent de la relation.
     *
     * @var \Two\Database\ORM\Model
     */
    protected $parent;

    /**
     * Le nom de la colonne de clé étrangère.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * Le nom de la colonne « autre clé ».
     *
     * @var string
     */
    protected $otherKey;

    /**
     * Les attributs qui ne sont pas attribuables en masse.
     *
     * @var array
     */
    protected $guarded = array();

    /**
     * Créez une nouvelle instance de modèle pivot.
     *
     * @param  \Two\Database\ORM\Model  $parent
     * @param  array   $attributes
     * @param  string  $table
     * @param  bool    $exists
     * @return void
     */
    public function __construct(Model $parent, $attributes, $table, $exists = false)
    {
        parent::__construct();

        //
        $this->setRawAttributes($attributes, true);

        $this->setTable($table);

        $this->setConnection($parent->getConnectionName());

        //
        $this->parent = $parent;

        $this->exists = $exists;

        $this->timestamps = $this->hasTimestampAttributes();
    }

    /**
     * Définissez les clés pour une requête de mise à jour de sauvegarde.
     *
     * @param  \Two\Database\ORM\Builder
     * @return \Two\Database\ORM\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->foreignKey, $this->getAttribute($this->foreignKey));

        return $query->where($this->otherKey, $this->getAttribute($this->otherKey));
    }

    /**
     * Supprimez l'enregistrement du modèle pivot de la base de données.
     *
     * @return int
     */
    public function delete()
    {
        return $this->getDeleteQuery()->delete();
    }

    /**
     * Obtenez le générateur de requêtes pour une opération de suppression sur le pivot.
     *
     * @return \Two\Database\ORM\Builder
     */
    protected function getDeleteQuery()
    {
        $foreign = $this->getAttribute($this->foreignKey);

        $query = $this->newQuery()->where($this->foreignKey, $foreign);

        return $query->where($this->otherKey, $this->getAttribute($this->otherKey));
    }

    /**
     * Obtenez le nom de la colonne de clé étrangère.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Obtenez le nom de la colonne « autre clé ».
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->otherKey;
    }

    /**
     * Définissez les noms de clé pour l'instance de modèle pivot.
     *
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @return $this
     */
    public function setPivotKeys($foreignKey, $otherKey)
    {
        $this->foreignKey = $foreignKey;

        $this->otherKey = $otherKey;

        return $this;
    }

    /**
     * Déterminez si le modèle pivot possède des attributs d'horodatage.
     *
     * @return bool
     */
    public function hasTimestampAttributes()
    {
        return array_key_exists($this->getCreatedAtColumn(), $this->attributes);
    }

    /**
     * Obtenez le nom de la colonne "créé à".
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Obtenez le nom de la colonne « mis à jour à ».
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return $this->parent->getUpdatedAtColumn();
    }

}

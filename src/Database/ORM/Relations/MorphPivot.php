<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM\Relations;

use Two\Database\ORM\Builder;


class MorphPivot extends Pivot
{
    /**
     * Le type de relation polymorphe.
     *
     * Définissez-le explicitement afin qu'il ne soit pas inclus dans les attributs enregistrés.
     *
     * @var string
     */
    protected $morphType;

    /**
     * La valeur de la relation polymorphe.
     *
     * Définissez-le explicitement afin qu'il ne soit pas inclus dans les attributs enregistrés.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Définissez les clés pour une requête de mise à jour de sauvegarde.
     *
     * @param  \Two\Database\ORM\Builder  $query
     * @return \Two\Database\ORM\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->morphType, $this->morphClass);

        return parent::setKeysForSaveQuery($query);
    }

    /**
     * Supprimez l'enregistrement du modèle pivot de la base de données.
     *
     * @return int
     */
    public function delete()
    {
        $query = $this->getDeleteQuery();

        $query->where($this->morphType, $this->morphClass);

        return $query->delete();
    }

    /**
     * Définissez le type de morphing pour le pivot.
     *
     * @param  string  $morphType
     * @return $this
     */
    public function setMorphType($morphType)
    {
        $this->morphType = $morphType;

        return $this;
    }

    /**
     * Définissez la classe de morphing pour le pivot.
     *
     * @param  string  $morphClass
     * @return \Two\Database\ORM\Relations\MorphPivot
     */
    public function setMorphClass($morphClass)
    {
            $this->morphClass = $morphClass;

            return $this;
    }


}

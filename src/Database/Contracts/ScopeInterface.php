<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Contracts;

use Two\Database\ORM\Builder;


interface ScopeInterface
{
    /**
     * Appliquez la portée à un générateur de requêtes ORM donné.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    public function apply(Builder $builder);

    /**
     * Supprimez la portée du générateur de requêtes ORM donné.
     *
     * @param  \Two\Database\ORM\Builder  $builder
     * @return void
     */
    public function remove(Builder $builder);

}

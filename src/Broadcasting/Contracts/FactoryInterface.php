<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Contracts;


interface FactoryInterface
{
    /**
     * Obtenez une implémentation de diffuseur par son nom.
     *
     * @param  string  $name
     * @return void
     */
    public function connection($name = null);
}

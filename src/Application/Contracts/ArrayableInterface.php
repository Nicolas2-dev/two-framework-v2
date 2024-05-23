<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Contracts;


interface ArrayableInterface
{
    
    /**
     * Obtenez l'instance sous forme de tableau.
     *
     * @return array
     */
    public function toArray();
}

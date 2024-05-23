<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Contracts;


interface JsonableInterface
{
    
    /**
     * Convertissez l'objet en sa représentation JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0);
}

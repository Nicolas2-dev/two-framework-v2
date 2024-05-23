<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Contracts;


interface RenderableInterface
{
    
    /**
     * Obtenez le contenu évalué de l'objet.
     *
     * @return string
     */
    public function render();
}

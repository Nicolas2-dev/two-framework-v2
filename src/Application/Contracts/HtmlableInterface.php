<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Contracts;


interface HtmlableInterface
{
    
    /**
     * Obtenez le contenu sous forme de chaîne HTML.
     *
     * @return string
     */
    public function toHtml();
}

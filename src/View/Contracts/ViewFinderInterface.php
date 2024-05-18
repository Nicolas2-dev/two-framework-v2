<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Contracts;


interface ViewFinderInterface
{

    /**
     * Obtenez l’emplacement complet de la vue.
     *
     * @param  string  $view
     * @return string
     */
    public function find($view);

    /**
     * Ajoutez une extension de vue valide au Finder.
     *
     * @param  string  $extension
     * @return void
     */
    public function addExtension($extension);

}

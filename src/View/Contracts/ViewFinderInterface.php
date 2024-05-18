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

    /**
     * Ajoutez un emplacement au chercheur.
     *
     * @param  string  $location
     * @return void
     */
    public function addLocation($location);

    /**
     * Ajoutez un indice d'espace de noms au chercheur.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return void
     */
    public function addNamespace($namespace, $hints);

    /**
     * Ajoutez un chemin spécifié par son espace de noms.
     *
     * @param  string  $namespace
     * @return void
     */
    public function overridesFrom($namespace);

}

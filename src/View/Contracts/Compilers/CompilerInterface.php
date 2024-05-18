<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Contracts\Compilers;


interface CompilerInterface
{

    /**
     * Obtenez le chemin d'accès à la version compilée d'une vue.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path);

    /**
     * Déterminez si la vue donnée a expiré.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path);

    /**
     * Compilez la vue sur le chemin donné.
     *
     * @param  string  $path
     * @return void
     */
    public function compile($path);

}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Config\Contracts;


interface LoaderInterface
{

    /**
     * Chargez le groupe de configuration donné.
     *
     * @param  string  $environment
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($environment, $group, $namespace = null);

    /**
     * Déterminez si le groupe de configuration donné existe.
     *
     * @param  string  $group
     * @param  string  $namespace
     * @return bool
     */
    public function exists($group, $namespace = null);

    /**
     * Ajoutez un nouvel espace de noms au chargeur.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint);

    /**
     * Renvoie tous les espaces de noms enregistrés avec la configuration chargeur.
     *
     * @return array
     */
    public function getNamespaces();

    /**
     * Appliquez toutes les cascades à un tableau d'options de package.
     *
     * @param  string  $environment
     * @param  string  $package
     * @param  string  $group
     * @param  array   $items
     * @return array
     */
    public function cascadePackage($environment, $package, $group, $items);
}

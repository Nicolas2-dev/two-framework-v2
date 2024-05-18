<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Environment\Contracts;


interface EnvironmentVariablesLoaderInterface
{
    
    /**
     * Chargez les variables d'environnement pour l'environnement donné.
     *
     * @param  string  $environment
     * @return array
     */
    public function load($environment = null);

}
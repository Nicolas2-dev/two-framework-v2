<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Contracts;


interface ConnectionResolverInterface
{
    /**
     * Obtenez une instance de connexion à la base de données.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    public function connection($name = null);

    /**
     * Obtenez le nom de connexion par défaut.
     *
     * @return string
     */
    public function getDefaultConnection();

    /**
     * Définissez le nom de connexion par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name);

}

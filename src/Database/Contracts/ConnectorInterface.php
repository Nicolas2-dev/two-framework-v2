<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Contracts;


interface ConnectorInterface
{
    /**
     * Établissez une connexion à la base de données.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config);

}

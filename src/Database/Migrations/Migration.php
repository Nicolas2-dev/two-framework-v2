<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Migrations;


abstract class Migration
{
    /**
     * Le nom de la connexion à la base de données à utiliser.
     *
     * @var string
     */
    protected $connection;

    /**
     * Obtenez le nom de la connexion de migration.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

}

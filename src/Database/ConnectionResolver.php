<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use Two\Database\Contracts\ConnectionResolverInterface;


class ConnectionResolver implements ConnectionResolverInterface
{
    /**
     * Toutes les connexions enregistrées.
     *
     * @var array
     */
    protected $connections = array();

    /**
     * Le nom de connexion par défaut.
     *
     * @var string
     */
    protected $default;

    /**
     * Créez une nouvelle instance de résolveur de connexion.
     *
     * @param  array  $connections
     * @return void
     */
    public function __construct(array $connections = array())
    {
        foreach ($connections as $name => $connection) {
            $this->addConnection($name, $connection);
        }
    }

    /**
     * Obtenez une instance de connexion à la base de données.
     *
     * @param  string  $name
     * @return \Two\Database\Connection
     */
    public function connection($name = null)
    {
        if (is_null($name)) $name = $this->getDefaultConnection();

        return $this->connections[$name];
    }

    /**
     * Ajoutez une connexion au résolveur.
     *
     * @param  string  $name
     * @param  \Two\Database\Connection  $connection
     * @return void
     */
    public function addConnection($name, Connection $connection)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * Vérifiez si une connexion a été enregistrée.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasConnection($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * Obtenez le nom de connexion par défaut.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->default;
    }

    /**
     * Définissez le nom de connexion par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->default = $name;
    }

}

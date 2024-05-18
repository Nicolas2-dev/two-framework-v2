<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use PDO;


abstract class Connector
{
    /**
     * Les options de connexion PDO par défaut.
     *
     * @var array
     */
    protected $options = array(
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    );


    public function __construct()
    {
        //
    }

    /**
     * Obtenez les options PDO en fonction de la configuration.
     *
     * @param  array  $config
     * @return array
     */
    public function getOptions(array $config)
    {
        $options = array_get($config, 'options', array());

        return array_diff_key($this->options, $options) + $options;
    }

    /**
     * Créez une nouvelle connexion PDO.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return PDO
     */
    public function createConnection($dsn, array $config, array $options)
    {
        $username = array_get($config, 'username');

        $password = array_get($config, 'password');

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Obtenez les options de connexion PDO par défaut.
     *
     * @return array
     */
    public function getDefaultOptions()
    {
        return $this->options;
    }

    /**
     * Définissez les options de connexion PDO par défaut.
     *
     * @param  array  $options
     * @return void
     */
    public function setDefaultOptions(array $options)
    {
        $this->options = $options;
    }

}

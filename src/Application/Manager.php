<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application;

use Closure;
use InvalidArgumentException;


abstract class Manager
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Les créateurs de pilotes personnalisés enregistrés.
     *
     * @var array
     */
    protected $customCreators = array();

    /**
     * Le tableau des "pilotes" créés.
     *
     * @var array
     */
    protected $drivers = array();

    /**
     * Créez une nouvelle instance de gestionnaire.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Obtenez une instance de pilote.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Créez une nouvelle instance de pilote.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        $method = 'create' .ucfirst($driver) .'Driver';

        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        } else if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Appelez un créateur de pilotes personnalisés.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->app);
    }

    /**
     * Enregistrez un créateur de pilote personnalisé Closure.
     *
     * @param  string   $driver
     * @param  Closure  $callback
     * @return \Application\Manager|static
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Obtenez tous les "pilotes" créés.
     *
     * @return array
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * Appelez dynamiquement l’instance de pilote par défaut.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->driver(), $method), $parameters);
    }

}

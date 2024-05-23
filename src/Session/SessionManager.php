<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use Two\Application\Manager;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;


class SessionManager extends Manager
{
    /**
     * Appelez un créateur de pilotes personnalisés.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->buildSession(parent::callCustomCreator($driver));
    }

    /**
     * Créez une instance du pilote de session "array".
     *
     * @return \Two\Session\Store
     */
    protected function createArrayDriver()
    {
        return new Store($this->app['config']['session.cookie'], new NullSessionHandler);
    }

    /**
     * Créez une instance du pilote de session « cookie ».
     *
     * @return \Two\Session\Store
     */
    protected function createCookieDriver()
    {
        $lifetime = $this->app['config']['session.lifetime'];

        return $this->buildSession(new CookieSessionHandler($this->app['cookie'], $lifetime));
    }

    /**
     * Créez une instance du pilote de session de fichiers.
     *
     * @return \Two\Session\Store
     */
    protected function createFileDriver()
    {
        return $this->createNativeDriver();
    }

    /**
     * Créez une instance du pilote de session de fichiers.
     *
     * @return \Two\Session\Store
     */
    protected function createNativeDriver()
    {
        $path = $this->app['config']['session.files'];

        $lifetime = $this->app['config']['session.lifetime'];

        return $this->buildSession(new FileSessionHandler($this->app['files'], $path, $lifetime));
    }

    /**
     * Créez une instance du pilote de session de base de données.
     *
     * @return \Two\Session\Store
     */
    protected function createDatabaseDriver()
    {
        $connection = $this->getDatabaseConnection();

        $table = $this->app['config']['session.table'];

        return $this->buildSession(new DatabaseSessionHandler($connection, $table));
    }

    /**
     * Obtenez la connexion à la base de données pour le pilote de base de données.
     *
     * @return \Two\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->app['config']['session.connection'];

        return $this->app['db']->connection($connection);
    }

    /**
     * Créez une instance du pilote de session APC.
     *
     * @return \Two\Session\Store
     */
    protected function createApcDriver()
    {
        return $this->createCacheBased('apc');
    }

    /**
     * Créez une instance du pilote de session Memcached.
     *
     * @return \Two\Session\Store
     */
    protected function createMemcachedDriver()
    {
        return $this->createCacheBased('memcached');
    }

    /**
     * Créez une instance du pilote de session Wincache.
     *
     * @return \Two\Session\Store
     */
    protected function createWincacheDriver()
    {
        return $this->createCacheBased('wincache');
    }

    /**
     * Créez une instance du pilote de session Redis.
     *
     * @return \Two\Session\Store
     */
    protected function createRedisDriver()
    {
        $handler = $this->createCacheHandler('redis');

        $handler->getCache()->getStore()->setConnection($this->app['config']['session.connection']);

        return $this->buildSession($handler);
    }

    /**
     * Créez une instance d'un pilote piloté par cache.
     *
     * @param  string  $driver
     * @return \Two\Session\Store
     */
    protected function createCacheBased($driver)
    {
        return $this->buildSession($this->createCacheHandler($driver));
    }

    /**
     * Créez l'instance de gestionnaire de session basée sur le cache.
     *
     * @param  string  $driver
     * @return \Two\Session\CacheBasedSessionHandler
     */
    protected function createCacheHandler($driver)
    {
        $minutes = $this->app['config']['session.lifetime'];

        return new CacheBasedSessionHandler($this->app['cache']->driver($driver), $minutes);
    }

    /**
     * Créez l'instance de session.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Two\Session\Store
     */
    protected function buildSession($handler)
    {
        return new Store($this->app['config']['session.cookie'], $handler);
    }

    /**
     * Obtenez la configuration de la session.
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->app['config']['session'];
    }

    /**
     * Obtenez le nom du pilote de session par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['session.driver'];
    }

    /**
     * Définissez le nom du pilote de session par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['session.driver'] = $name;
    }

}

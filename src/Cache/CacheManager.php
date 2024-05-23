<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache;

use Two\Cache\Apc\ApcStore;
use Two\Cache\Apc\ApcWrapper;
use Two\Cache\file\FileStore;
use Two\Cache\Store\NullStore;
use Two\Cache\Redis\RedisStore;
use Two\Cache\Store\ArrayStore;
use Two\Cache\Datanase\DatabaseStore;
use Two\Cache\Contracts\StoreInterface;
use Two\Cache\Memcached\MemcachedStore;
use Two\Application\Manager;


class CacheManager extends Manager
{
    /**
     * Créez une instance du pilote de cache APC.
     *
     * @return \Two\Cache\Apc\ApcStore
     */
    protected function createApcDriver()
    {
        return $this->repository(new ApcStore(new ApcWrapper, $this->getPrefix()));
    }

    /**
     * Créez une instance du pilote de cache de tableau.
     *
     * @return \Two\Cache\Store\ArrayStore
     */
    protected function createArrayDriver()
    {
        return $this->repository(new ArrayStore);
    }

    /**
     * Créez une instance du pilote de cache de fichiers.
     *
     * @return \Two\Cache\File\FileStore
     */
    protected function createFileDriver()
    {
        $path = $this->app['config']['cache.path'];

        return $this->repository(new FileStore($this->app['files'], $path));
    }

    /**
     * Créez une instance du pilote de cache Memcached.
     *
     * @return \Two\Cache\Memcached\MemcachedStore
     */
    protected function createMemcachedDriver()
    {
        $servers = $this->app['config']['cache.memcached'];

        $memcached = $this->app['memcached.connector']->connect($servers);

        return $this->repository(new MemcachedStore($memcached, $this->getPrefix()));
    }

    /**
     * Créez une instance du pilote de cache Null.
     *
     * @return \Two\Cache\Store\NullStore
     */
    protected function createNullDriver()
    {
        return $this->repository(new NullStore);
    }

    /**
     * Créez une instance du pilote de cache Redis.
     *
     * @return \Two\Cache\Redis\RedisStore
     */
    protected function createRedisDriver()
    {
        $redis = $this->app['redis'];

        return $this->repository(new RedisStore($redis, $this->getPrefix()));
    }

    /**
     * Créez une instance du pilote de cache de base de données.
     *
     * @return \Two\Cache\Database\DatabaseStore
     */
    protected function createDatabaseDriver()
    {
        $connection = $this->getDatabaseConnection();

        $encrypter = $this->app['encrypter'];

        // Nous permettons au développeur de spécifier quelle connexion et quelle table doivent être utilisées
        // pour stocker les éléments mis en cache. Nous devons également récupérer un préfixe au cas où une table
        // est utilisé par plusieurs applications bien que cela soit très improbable.
        $table = $this->app['config']['cache.table'];

        $prefix = $this->getPrefix();

        return $this->repository(new DatabaseStore($connection, $encrypter, $table, $prefix));
    }

    /**
     * Obtenez la connexion à la base de données pour le pilote de base de données.
     *
     * @return \Two\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->app['config']['cache.connection'];

        return $this->app['db']->connection($connection);
    }

    /**
     * Obtenez la valeur du "préfixe" du cache.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->app['config']['cache.prefix'];
    }

    /**
     * Définissez la valeur du « préfixe » du cache.
     *
     * @param  string  $name
     * @return void
     */
    public function setPrefix($name)
    {
        $this->app['config']['cache.prefix'] = $name;
    }

    /**
     * Créez un nouveau référentiel de cache avec l'implémentation donnée.
     *
     * @param  \Two\Cache\Contracts\StoreInterface  $store
     * @return \Two\Cache\Repository
     */
    public function repository(StoreInterface $store)
    {
        return new Repository($store);
    }

    /**
     * Obtenez le nom du pilote de cache par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['cache.driver'];
    }

    /**
     * Définissez le nom du pilote de cache par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['cache.driver'] = $name;
    }

}

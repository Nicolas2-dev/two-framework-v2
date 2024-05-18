<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Redis;

use Two\Cache\Tag\TagSet;
use Two\Cache\Tag\TaggableStore;
use Two\Cache\Redis\RedisTaggedCache;
use Two\Cache\Contracts\StoreInterface;
use Two\Redis\Database as Redis;


class RedisStore extends TaggableStore implements StoreInterface
{
    /**
     * La connexion à la base de données Redis.
     *
     * @var \Two\Redis\Database
     */
    protected $redis;

    /**
     * Une chaîne qui doit être ajoutée aux clés.
     *
     * @var string
     */
    protected $prefix;

    /**
     * La connexion Redis qui doit être utilisée.
     *
     * @var string
     */
    protected $connection;


    /**
     * Créez une nouvelle boutique Redis.
     *
     * @param  \Redis\Database  $redis
     * @param  string  $prefix
     * @param  string  $connection
     * @return void
     */
    public function __construct(Redis $redis, $prefix = '', $connection = 'default')
    {
        $this->redis = $redis;

        $this->connection = $connection;

        $this->prefix = (strlen($prefix) > 0) ? $prefix .':' : '';
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        if (! is_null($value = $this->connection()->get($this->prefix.$key))) {
            return is_numeric($value) ? $value : unserialize($value);
        }
    }

    /**
     * Stockez un élément dans le cache pendant un nombre de minutes donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $minutes
     * @return void
     */
    public function put($key, $value, $minutes)
    {
        $value = is_numeric($value) ? $value : serialize($value);

        $minutes = max(1, $minutes);

        $this->connection()->setex($this->prefix.$key, $minutes * 60, $value);
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix.$key, $value);
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix.$key, $value);
    }

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        $value = is_numeric($value) ? $value : serialize($value);

        $this->connection()->set($this->prefix.$key, $value);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key)
    {
        $this->connection()->del($this->prefix.$key);
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->connection()->flushdb();
    }

    /**
     * Commencez à exécuter une nouvelle opération de balises.
     *
     * @param  array|mixed  $names
     * @return \Two\Cache\Redis\RedisTaggedCache
     */
    public function tags($names)
    {
        return new RedisTaggedCache($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
    }

    /**
     * Obtenez l'instance de connexion Redis.
     *
     * @return \Predis\ClientInterface
     */
    public function connection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * Définissez le nom de connexion à utiliser.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Obtenez l'instance de base de données Redis.
     *
     * @return \Redis\Database
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * Obtenez le préfixe de la clé de cache.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }
}

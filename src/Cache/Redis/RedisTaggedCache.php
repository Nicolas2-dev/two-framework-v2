<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Redis;

use Two\Cache\Tag\TaggedCache;


class RedisTaggedCache extends TaggedCache
{

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        $this->pushForeverKeys($namespace = $this->tags->getNamespace(), $key);

        $this->store->forever(sha1($namespace).':'.$key, $value);
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->deleteForeverKeys();

        parent::flush();
    }

    /**
     * Stockez une copie de la clé complète pour chaque segment d'espace de noms.
     *
     * @param  string  $namespace
     * @param  string  $key
     * @return void
     */
    protected function pushForeverKeys($namespace, $key)
    {
        $fullKey = $this->getPrefix().sha1($namespace).':'.$key;

        foreach (explode('|', $namespace) as $segment) {
            $this->store->connection()->lpush($this->foreverKey($segment), $fullKey);
        }
    }

    /**
     * Supprimez tous les éléments stockés pour toujours.
     *
     * @return void
     */
    protected function deleteForeverKeys()
    {
        foreach (explode('|', $this->tags->getNamespace()) as $segment) {
            $this->deleteForeverValues($segment = $this->foreverKey($segment));

            $this->store->connection()->del($segment);
        }
    }

    /**
     * Supprimez toutes les clés qui ont été stockées pour toujours.
     *
     * @param  string  $foreverKey
     * @return void
     */
    protected function deleteForeverValues($foreverKey)
    {
        $forever = array_unique($this->store->connection()->lrange($foreverKey, 0, -1));

        if (count($forever) > 0) {
            call_user_func_array(array($this->store->connection(), 'del'), $forever);
        }
    }

    /**
     * Obtenez la clé de référence permanente pour le segment.
     *
     * @param  string  $segment
     * @return string
     */
    protected function foreverKey($segment)
    {
        return $this->getPrefix().$segment.':forever';
    }
}

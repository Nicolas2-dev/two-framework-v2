<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Apc;


class ApcWrapper
{
    /**
     * Indique si APC est pris en charge.
     *
     * @var bool
     */
    protected $apcu = false;


    /**
     * Créez une nouvelle instance de wrapper APC.
     *
     * @return void
     */
    public function __construct()
    {
        $this->apcu = function_exists('apcu_fetch');
    }

    /**
     * Récupérez un élément du cache.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->apcu ? apcu_fetch($key) : apc_fetch($key);
    }

    /**
     * Stocker un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $seconds
     * @return array|bool
     */
    public function put($key, $value, $seconds)
    {
        return $this->apcu ? apcu_store($key, $value, $seconds) : apc_store($key, $value, $seconds);
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value)
    {
        return $this->apcu ? apcu_inc($key, $value) : apc_inc($key, $value);
    }

    /**
     * Décrémenter la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value)
    {
        return $this->apcu ? apcu_dec($key, $value) : apc_dec($key, $value);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return array|bool
     */
    public function delete($key)
    {
        return $this->apcu ? apcu_delete($key) : apc_delete($key);
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->apcu ? apcu_clear_cache() : apc_clear_cache('user');
    }

}

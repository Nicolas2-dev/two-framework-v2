<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Contracts;


interface StoreInterface
{

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key);

    /**
     * Stockez un élément dans le cache pendant un nombre de minutes donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $minutes
     * @return void
     */
    public function put($key, $value, $minutes);

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * Décrémenter la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value);

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key);

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush();

    /**
     * Obtenez le préfixe de la clé de cache.
     *
     * @return string
     */
    public function getPrefix();

}

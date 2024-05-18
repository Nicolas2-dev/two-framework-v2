<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Apc;

use Two\Cache\Apc\ApcWrapper;
use Two\Cache\Tag\TaggableStore;
use Two\Cache\Contracts\StoreInterface;


class ApcStore extends TaggableStore implements StoreInterface
{
    /**
     * L'instance du wrapper APC.
     *
     * @var \Two\Cache\Apc\ApcWrapper
     */
    protected $apc;

    /**
     * Une chaîne qui doit être ajoutée aux clés.
     *
     * @var string
     */
    protected $prefix;


    /**
     * Créez un nouveau magasin APC.
     *
     * @param  \Two\Cache\Apc\ApcWrapper  $apc
     * @param  string  $prefix
     * @return void
     */
    public function __construct(ApcWrapper $apc, $prefix = '')
    {
        $this->apc = $apc;

        $this->prefix = $prefix;
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $value = $this->apc->get($this->prefix.$key);

        if ($value !== false) {
            return $value;
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
        $this->apc->put($this->prefix.$key, $value, $minutes * 60);
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->apc->increment($this->prefix.$key, $value);
    }

    /**
     * Décrémenter la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->apc->decrement($this->prefix.$key, $value);
    }

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return array|bool
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key)
    {
        $this->apc->delete($this->prefix.$key);
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->apc->flush();
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

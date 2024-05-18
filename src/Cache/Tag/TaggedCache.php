<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Tag;

use Closure;
use DateTime;

use Two\Cache\Tag\TagSet;
use Two\Cache\Contracts\StoreInterface;

use Carbon\Carbon;


class TaggedCache implements StoreInterface
{
    /**
     * L’implémentation du cache store.
     *
     * @var \Two\Cache\Contracts\StoreInterface
     */
    protected $store;

    /**
     * Instance du jeu de balises.
     *
     * @var \Two\Cache\Tag\TagSet
     */
    protected $tags;


    /**
     * Créez une nouvelle instance de cache balisée.
     *
     * @param  \Two\Cache\Contracts\StoreInterface  $store
     * @param  \Two\Cache\Tag\TagSet  $tags
     * @return void
     */
    public function __construct(StoreInterface $store, TagSet $tags)
    {
        $this->tags = $tags;
        $this->store = $store;
    }

    /**
     * Déterminez si un élément existe dans le cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        return ! is_null($this->get($key));
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = $this->store->get($this->taggedItemKey($key));

        return ! is_null($value) ? $value : value($default);
    }

    /**
     * Stockez un élément dans le cache pendant un nombre de minutes donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTime|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes)
    {
        $minutes = $this->getMinutes($minutes);

        if (! is_null($minutes)) {
            $this->store->put($this->taggedItemKey($key), $value, $minutes);
        }
    }

    /**
     * Stockez un élément dans le cache si la clé n'existe pas.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTime|int  $minutes
     * @return bool
     */
    public function add($key, $value, $minutes)
    {
        if (is_null($this->get($key))) {
            $this->put($key, $value, $minutes); return true;
        }

        return false;
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function increment($key, $value = 1)
    {
        $this->store->increment($this->taggedItemKey($key), $value);
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function decrement($key, $value = 1)
    {
        $this->store->decrement($this->taggedItemKey($key), $value);
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
        $this->store->forever($this->taggedItemKey($key), $value);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return $this->store->forget($this->taggedItemKey($key));
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->tags->reset();
    }

    /**
     * Récupérez un élément du cache ou stockez la valeur par défaut.
     *
     * @param  string  $key
     * @param  \DateTime|int  $minutes
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $minutes, Closure $callback)
    {
        // Si l'élément existe dans le cache, nous le renverrons immédiatement
        // sinon nous exécuterons la fermeture donnée et mettrons en cache le résultat
        // de cette exécution pendant le nombre de minutes de stockage donné.

        if (! is_null($value = $this->get($key))) {
            return $value;
        }

        $value = call_user_func($callback);

        $this->put($key, $value, $minutes);

        return $value;
    }

    /**
     * Récupérez un élément du cache ou stockez la valeur par défaut pour toujours.
     *
     * @param  string    $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function sear($key, Closure $callback)
    {
        return $this->rememberForever($key, $callback);
    }

    /**
     * Récupérez un élément du cache ou stockez la valeur par défaut pour toujours.
     *
     * @param  string    $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function rememberForever($key, Closure $callback)
    {
        // Si l'élément existe dans le cache, nous le renverrons immédiatement
        // sinon nous exécuterons la fermeture donnée et mettrons en cache le résultat
        // de cette exécution pendant le nombre de minutes donné. C'est facile.

        if (! is_null($value = $this->get($key))) {
            return $value;
        }

        $this->forever($key, call_user_func($callback));

        return $value;
    }

    /**
     * Obtenez une clé complète pour un élément balisé.
     *
     * @param  string  $key
     * @return string
     */
    public function taggedItemKey($key)
    {
        return sha1($this->tags->getNamespace()) .':' .$key;
    }

    /**
     * Obtenez le préfixe de la clé de cache.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->store->getPrefix();
    }

    /**
     * Calculez le nombre de minutes avec la durée donnée.
     *
     * @param  \DateTime|int  $duration
     * @return int|null
     */
    protected function getMinutes($duration)
    {
        if ($duration instanceof DateTime) {
            $fromNow = Carbon::instance($duration)->diffInMinutes();

            return $fromNow > 0 ? $fromNow : null;
        }

        return is_string($duration) ? (int) $duration : $duration;
    }
}

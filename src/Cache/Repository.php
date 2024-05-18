<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache;

use Closure;
use DateTime;
use ArrayAccess;

use Two\Support\Traits\MacroableTrait;
use Two\Cache\Contracts\StoreInterface;

use Carbon\Carbon;


class Repository implements ArrayAccess
{
    use MacroableTrait {
        __call as macroCall;
    }

    /**
     * L’implémentation du cache store.
     *
     * @var \Two\Cache\Contracts\StoreInterface
     */
    protected $store;

    /**
     * Le nombre de minutes par défaut pour stocker les éléments.
     *
     * @var int
     */
    protected $default = 60;


    /**
     * Créez une nouvelle instance de référentiel de cache.
     *
     * @param  \Two\Cache\Contracts\StoreInterface  $store
     */
    public function __construct(StoreInterface $store)
    {
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
        $value = $this->store->get($key);

        if (! is_null($value)) {
            return $value;
        }

        return value($default);
    }

    /**
     * Récupérez un élément du cache et supprimez-le.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    /**
     * Stocker un élément dans le cache.
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
            $this->store->put($key, $value, $minutes);
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
        if (! is_null($this->get($key))) {
            return false;
        }

        $this->put($key, $value, $minutes);

        return true;
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
     * @param  string   $key
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
     * @param  string   $key
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

        $this->forever($key, $value = call_user_func($callback));

        return $value;
    }

    /**
     * Obtenez l'heure du cache par défaut.
     *
     * @return int
     */
    public function getDefaultCacheTime()
    {
        return $this->default;
    }

    /**
     * Définissez la durée du cache par défaut en minutes.
     *
     * @param  int   $minutes
     * @return void
     */
    public function setDefaultCacheTime($minutes)
    {
        $this->default = $minutes;
    }

    /**
     * Obtenez l’implémentation du magasin de cache.
     *
     * @return \Two\Cache\Contracts\StoreInterface
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * Déterminez si une valeur mise en cache existe.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed 
    {
        return $this->get($key);
    }

    /**
     * Stockez un élément dans le cache pendant la durée par défaut.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($key, $value): void 
    {
        $this->put($key, $value, $this->default);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void 
    {
         $this->forget($key);
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

    /**
     * Gérez les appels dynamiques dans les macros ou transmettez les méthodes manquantes au magasin.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return call_user_func_array(array($this->store, $method), $parameters);
    }
}

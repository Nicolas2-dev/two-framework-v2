<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache;

use Two\Cache\Repository;


class RateLimiter
{
    /**
     * L’implémentation du cache store.
     *
     * @var \Two\Cache\Repository
     */
    protected $cache;


    /**
     * Créez une nouvelle instance de limiteur de débit.
     *
     * @param  \Two\Cache\Repository  $cache
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Déterminez si la clé donnée a été « accédée » trop de fois.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return bool
     */
    public function tooManyAttempts($key, $maxAttempts, $decayMinutes = 1)
    {
        $lockedOut = $this->cache->has($key .':lockout');

        if ($this->attempts($key) > $maxAttempts || $lockedOut) {
            if (! $lockedOut) {
                $this->cache->add($key.':lockout', time() + ($decayMinutes * 60), $decayMinutes);
            }

            return true;
        }

        return false;
    }

    /**
     * Incrémentez le compteur pour une clé donnée pendant un temps de décroissance donné.
     *
     * @param  string  $key
     * @param  int  $decayMinutes
     * @return int
     */
    public function hit($key, $decayMinutes = 1)
    {
        $this->cache->add($key, 1, $decayMinutes);

        return (int) $this->cache->increment($key);
    }

    /**
     * Obtenez le nombre de tentatives pour la clé donnée.
     *
     * @param  string  $key
     * @return mixed
     */
    public function attempts($key)
    {
        return $this->cache->get($key, 0);
    }

    /**
     * Obtenez le nombre de tentatives restantes pour la clé donnée.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    public function retriesLeft($key, $maxAttempts)
    {
        $attempts = $this->attempts($key);

        return ($attempts === 0) ? $maxAttempts : $maxAttempts - $attempts + 1;
    }

    /**
     * Effacez les hits et le verrouillage pour la clé donnée.
     *
     * @param  string  $key
     * @return void
     */
    public function clear($key)
    {
        $this->cache->forget($key);

        $this->cache->forget($key .':lockout');
    }

    /**
     * Obtenez le nombre de secondes jusqu'à ce que la "clé" soit à nouveau accessible.
     *
     * @param  string  $key
     * @return int
     */
    public function availableIn($key)
    {
        return $this->cache->get($key .':lockout') - time();
    }
}

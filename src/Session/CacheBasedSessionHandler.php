<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use SessionHandlerInterface;

use Two\Cache\Repository;


class CacheBasedSessionHandler implements SessionHandlerInterface
{
    /**
     * L'instance du référentiel de cache.
     *
     * @var \Two\Cache\Repository
     */
    protected $cache;

    /**
     * Le nombre de minutes pendant lesquelles stocker les données dans le cache.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Créez une nouvelle instance de gestionnaire piloté par le cache.
     *
     * @param  \Two\Cache\Repository  $cache
     * @param  int  $minutes
     * @return void
     */
    public function __construct(Repository $cache, $minutes)
    {
        $this->cache = $cache;
        $this->minutes = $minutes;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId): string|false
    {
        return $this->cache->get($sessionId, '');
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data): bool
    {
        $this->cache->put($sessionId, $data, $this->minutes);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId): bool
    {
        return $this->cache->forget($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime): bool
    {
        return true;
    }

    /**
     * Obtenez le référentiel de cache sous-jacent.
     *
     * @return \Two\Cache\Repository
     */
    public function getCache()
    {
        return $this->cache;
    }

}

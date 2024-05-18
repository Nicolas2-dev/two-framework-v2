<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Memcached;

use Memcached;
use RuntimeException;


class MemcachedConnector
{

    /**
     * Créez une nouvelle connexion Memcached.
     *
     * @param  array  $servers
     * @return \Memcached
     *
     * @throws \RuntimeException
     */
    public function connect(array $servers)
    {
        $memcached = $this->getMemcached();

        // Pour chaque serveur du tableau, nous allons simplement extraire la configuration et ajouter
        // le serveur à la connexion Memcached. Une fois que nous avons ajouté tout cela
        // serveurs, nous vérifierons que la connexion est réussie et la renverrons.
        foreach ($servers as $server) {
            $memcached->addServer(
                $server['host'],
                $server['port'],
                $server['weight']
            );
        }

        if ($memcached->getVersion() === false) {
            throw new RuntimeException("Could not establish Memcached connection.");
        }

        return $memcached;
    }

    /**
     * Obtenez une nouvelle instance Memcached.
     *
     * @return \Memcached
     */
    protected function getMemcached()
    {
        return new Memcached;
    }
}

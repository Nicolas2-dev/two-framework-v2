<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Redis;

use Predis\Client;


class Database
{
    /**
     * L'adresse hôte de la base de données.
     *
     * @var array
     */
    protected $clients;

    /**
     * Créez une nouvelle instance de connexion Redis.
     *
     * @param  array  $servers
     * @return void
     */
    public function __construct(array $servers = array())
    {
        if (isset($servers['cluster']) && $servers['cluster']) {
            $this->clients = $this->createAggregateClient($servers);
        } else {
            $this->clients = $this->createSingleClients($servers);
        }
    }

    /**
     * Créez un nouveau client agrégé prenant en charge le partitionnement.
     *
     * @param  array  $servers
     * @return array
     */
    protected function createAggregateClient(array $servers)
    {
        $servers = array_except($servers, array('cluster'));

        return array('default' => new Client(array_values($servers)));
    }

    /**
     * Créez un tableau de clients à connexion unique.
     *
     * @param  array  $servers
     * @return array
     */
    protected function createSingleClients(array $servers)
    {
        $clients = array();

        foreach ($servers as $key => $server) {
            $clients[$key] = new Client($server);
        }

        return $clients;
    }

    /**
     * Obtenez une instance de connexion Redis spécifique.
     *
     * @param  string  $name
     * @return \Predis\Connection\SingleConnectionInterface
     */
    public function connection($name = 'default')
    {
        return $this->clients[$name ?: 'default'];
    }

    /**
     * Exécutez une commande sur la base de données Redis.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function command($method, array $parameters = array())
    {
        return call_user_func_array(array($this->clients['default'], $method), $parameters);
    }

    /**
     * Créez dynamiquement une commande Redis.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->command($method, $parameters);
    }

}

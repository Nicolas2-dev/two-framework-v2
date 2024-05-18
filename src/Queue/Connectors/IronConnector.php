<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Connectors;

use IronMQ;

use Two\Http\Request;
use Two\Queue\Contracts\Connectors\ConnectorInterface;
use Two\Queue\Queues\IronQueue;
use Two\Encryption\Encrypter;


class IronConnector implements ConnectorInterface
{

    /**
     * L'instance du chiffreur.
     *
     * @var \Two\Encryption\Encrypter
     */
    protected $crypt;

    /**
     * L’instance de requête actuelle.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * Créez une nouvelle instance de connecteur Iron.
     *
     * @param  \Two\Encryption\Encrypter  $crypt
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function __construct(Encrypter $crypt, Request $request)
    {
        $this->crypt = $crypt;
        $this->request = $request;
    }

    /**
     * Établissez une connexion à la file d'attente.
     *
     * @param  array  $config
     * @return \Two\Queue\Contracts\QueueInterface
     */
    public function connect(array $config)
    {
        $ironConfig = array('token' => $config['token'], 'project_id' => $config['project']);

        if (isset($config['host'])) $ironConfig['host'] = $config['host'];

        $iron = new IronMQ($ironConfig);

        if (isset($config['ssl_verifypeer']))
        {
            $iron->ssl_verifypeer = $config['ssl_verifypeer'];
        }

        return new IronQueue($iron, $this->request, $config['queue'], $config['encrypt']);
    }

}

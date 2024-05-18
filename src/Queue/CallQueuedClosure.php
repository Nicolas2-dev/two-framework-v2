<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Two\Encryption\Encrypter;
use Two\Queue\Job;


class CallQueuedClosure
{
    /**
     * L'instance du chiffreur.
     *
     * @var \Two\Encryption\Encrypter  $crypt
     */
    protected $crypt;


    /**
     * Créez une nouvelle tâche de fermeture en file d'attente.
     *
     * @param  \Two\Encryption\Encrypter  $crypt
     * @return void
     */
    public function __construct(Encrypter $crypt)
    {
        $this->crypt = $crypt;
    }

    /**
     * Lancez le travail de file d'attente basé sur la fermeture.
     *
     * @param  \Two\Queue\Job  $job
     * @param  array  $data
     * @return void
     */
    public function call(Job $job, array $data)
    {
        $payload = $this->crypt->decrypt(
            $data['closure']
        );

        $closure = unserialize($payload);

        call_user_func($closure, $job);
    }
}

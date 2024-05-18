<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus\Contracts;


interface DispatcherInterface
{
    /**
     * Envoyez une commande à son gestionnaire approprié.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command);

    /**
     * Envoyez une commande à son gestionnaire approprié dans le processus en cours.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatchNow($command, $handler = null);

    /**
     * Récupérez le gestionnaire d’une commande.
     *
     * @param  mixed  $command
     * @return bool|mixed
     */
    public function getCommandHandler($command);

    /**
     * Définissez les commandes de tuyaux qui doivent être transmises avant l'expédition.
     *
     * @param  array  $pipes
     * @return $this
     */
    public function pipeThrough(array $pipes);
}

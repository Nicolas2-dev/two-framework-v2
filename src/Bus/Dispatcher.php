<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus;

use Closure;
use RuntimeException;

use Two\Container\Container;
use Two\Application\Pipeline;
use Two\Queue\contracts\QueueInterface;
use Two\Queue\Contracts\ShouldQueueInterface;
use Two\Bus\Contracts\QueueingDispatcherInterface;


class Dispatcher implements QueueingDispatcherInterface
{
    /**
     * L’implémentation du conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Les canaux par lesquels envoyer les commandes avant la distribution.
     *
     * @var array
     */
    protected $pipes = array();

    /**
     * La commande pour gérer le mappage pour les événements non auto-gérés.
     *
     * @var array
     */
    protected $handlers = array();

    /**
     * Le rappel du résolveur de file d’attente.
     *
     * @var \Closure|null
     */
    protected $queueResolver;


    /**
     * Créez une nouvelle instance de répartiteur de commandes.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Closure|null  $queueResolver
     * @return void
     */
    public function __construct(Container $container, Closure $queueResolver = null)
    {
        $this->container = $container;

        $this->queueResolver = $queueResolver;
    }

    /**
     * Envoyez une commande à son gestionnaire approprié.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command)
    {
        if (! is_null($this->queueResolver) && $this->commandShouldBeQueued($command)) {
            return $this->dispatchToQueue($command);
        } else {
            return $this->dispatchNow($command);
        }
    }

    /**
     * Envoyez une commande à son gestionnaire approprié dans le processus en cours.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatchNow($command, $handler = null)
    {
        if (! is_null($handler) || ! is_null($handler = $this->getCommandHandler($command))) {
            $callback = function ($command) use ($handler)
            {
                return $handler->handle($command);
            };
        }

        // La commande est auto-gérée.
        else {
            $callback = function ($command)
            {
                return $this->container->call(array($command, 'handle'));
            };
        }

        $pipeline = new Pipeline($this->container, $this->pipes);

        return $pipeline->handle($command, $callback);
    }

    /**
     * Déterminez si la commande donnée a un gestionnaire.
     *
     * @param  mixed  $command
     * @return bool
     */
    public function hasCommandHandler($command)
    {
        $key = get_class($command);

        return array_key_exists($key, $this->handlers);
    }

    /**
     * Récupérez le gestionnaire d’une commande.
     *
     * @param  mixed  $command
     * @return bool|mixed
     */
    public function getCommandHandler($command)
    {
        $key = get_class($command);

        if (array_key_exists($key, $this->handlers)) {
            $handler = $this->handlers[$key];

            return $this->container->make($handler);
        }
    }

    /**
     * Déterminez si la commande donnée doit être mise en file d'attente.
     *
     * @param  mixed  $command
     * @return bool
     */
    protected function commandShouldBeQueued($command)
    {
        return ($command instanceof ShouldQueueInterface);
    }

    /**
     * Envoyez une commande à son gestionnaire approprié derrière une file d'attente.
     *
     * @param  mixed  $command
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function dispatchToQueue($command)
    {
        $connection = isset($command->connection) ? $command->connection : null;

        $queue = call_user_func($this->queueResolver, $connection);

        if (! $queue instanceof QueueInterface) {
            throw new RuntimeException('Queue resolver did not return a Queue implementation.');
        }

        if (method_exists($command, 'queue')) {
            return $command->queue($queue, $command);
        } else {
            return $this->pushCommandToQueue($queue, $command);
        }
    }

    /**
     * Poussez la commande sur l’instance de file d’attente donnée.
     *
     * @param  \Two\Queue\Contracts\QueueInterface  $queue
     * @param  mixed  $command
     * @return mixed
     */
    protected function pushCommandToQueue($queue, $command)
    {
        if (isset($command->queue, $command->delay)) {
            return $queue->laterOn($command->queue, $command->delay, $command);
        }

        if (isset($command->queue)) {
            return $queue->pushOn($command->queue, $command);
        }

        if (isset($command->delay)) {
            return $queue->later($command->delay, $command);
        }

        return $queue->push($command);
    }

    /**
     * Définissez les canaux par lesquels les commandes doivent être acheminées avant leur expédition.
     *
     * @param  array  $pipes
     * @return $this
     */
    public function pipeThrough(array $pipes)
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**Mappez une commande à un gestionnaire.
     * 
     *
     * @param  array  $map
     * @return $this
     */
    public function map(array $map)
    {
        $this->handlers = array_merge($this->handlers, $map);

        return $this;
    }
}

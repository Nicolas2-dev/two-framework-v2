<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Events;

use Exception;
use ReflectionClass;

use Two\Support\Str;
use Two\Container\Container;
use Two\Events\Contracts\DispatcherInterface;
use Two\Broadcasting\Contracts\ShouldBroadcastInterface;
use Two\Broadcasting\Contracts\ShouldBroadcastNowInterface;


class Dispatcher implements DispatcherInterface
{
    
    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Les auditeurs d'événements enregistrés.
     *
     * @var array
     */
    protected $listeners = array();

    /**
     * Les auditeurs génériques.
     *
     * @var array
     */
    protected $wildcards = array();

    /**
     * Les écouteurs d'événements triés.
     *
     * @var array
     */
    protected $sorted = array();

    /**
     * La pile de répartition des événements.
     *
     * @var array
     */
    protected $dispatching = array();

    /**
     * L'instance du résolveur de file d'attente.
     *
     * @var callable
     */
    protected $queueResolver;


    /**
     * Créez une nouvelle instance de répartiteur d'événements.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container = null)
    {
        $this->container = $container ?: new Container();
    }

    /**
     * Enregistrez un écouteur d'événement auprès du répartiteur.
     *
     * @param  string|array  $events
     * @param  mixed   $listener
     * @param  int     $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0)
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][$priority][] = $this->makeListener($listener);

                unset($this->sorted[$event]);
            }
        }
    }

    /**
     * Configurez un rappel d'écouteur générique.
     *
     * @param  string  $event
     * @param  mixed   $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener)
    {
        $this->wildcards[$event][] = $this->makeListener($listener);
    }

    /**
     * Déterminer si un événement donné a des auditeurs.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]) || isset($this->wildcards[$eventName]);
    }

    /**
     * Enregistrez un événement et une charge utile à déclencher plus tard.
     *
     * @param  string  $event
     * @param  array   $payload
     * @return void
     */
    public function push($event, $payload = array())
    {
        $this->listen($event .'_pushed', function() use ($event, $payload)
        {
            $this->dispatch($event, $payload);
        });
    }

    /**
     * Enregistrez un abonné à l'événement auprès du répartiteur.
     *
     * @param  string  $subscriber
     * @return void
     */
    public function subscribe($subscriber)
    {
        $subscriber = $this->resolveSubscriber($subscriber);

        $subscriber->subscribe($this);
    }

    /**
     * Résoudre l'instance d'abonné.
     *
     * @param  mixed  $subscriber
     * @return mixed
     */
    protected function resolveSubscriber($subscriber)
    {
        if (is_string($subscriber)) {
            return $this->container->make($subscriber);
        }

        return $subscriber;
    }

    /**
     * Lance un événement jusqu'à ce que la première réponse non nulle soit renvoyée.
     *
     * @param  string  $event
     * @param  array   $payload
     * @return mixed
     */
    public function until($event, $payload = array())
    {
        return $this->dispatch($event, $payload, true);
    }

    /**
     * Vider un ensemble d'événements en file d'attente.
     *
     * @param  string  $event
     * @return void
     */
    public function flush($event)
    {
        $this->dispatch($event .'_pushed');
    }

    /**
     * Obtenez l'événement qui est actuellement distribué.
     *
     * @return string
     */
    public function dispatching()
    {
        return last($this->dispatching);
    }

    /**
     * Envoyez un événement et appelez les auditeurs.
     *
     * @param  string  $event
     * @param  mixed   $payload
     * @param  bool    $halt
     * @return array|null
     */
    public function dispatch($event, $payload = array(), $halt = false)
    {
        $responses = array();

        // Lorsque "l'événement" donné est en fait un objet, nous supposerons qu'il s'agit 
        // d'un événement objet et utilise la classe comme nom d'événement et cet événement 
        // lui-même comme charge utile au gestionnaire, ce qui rend les événements basés sur 
        // des objets assez simples.
        if (is_object($event)) {
            list($payload, $event) = array(array($event), get_class($event));
        }

        // Si un tableau ne nous est pas donné comme charge utile, nous le transformerons en 
        // un seul afin nous pouvons facilement utiliser call_user_func_array sur les écouteurs, 
        // en passant le charge utile à chacun d'eux afin qu'ils reçoivent chacun de ces arguments.
        else if (! is_array($payload)) {
            $payload = array($payload);
        }

        $this->dispatching[] = $event;

        if (isset($payload[0]) && ($payload[0] instanceof ShouldBroadcastInterface)) {
            $this->broadcastEvent($payload[0]);
        }

        foreach ($this->getListeners($event) as $listener) {
            $response = call_user_func_array($listener, $payload);

            // Si une réponse est renvoyée par l'écouteur et que l'arrêt de l'événement est activé
            // nous renverrons simplement cette réponse, et n'appellerons pas le reste de l'événement
            // les auditeurs. Sinon, nous ajouterons la réponse sur la liste de réponses.
            if (! is_null($response) && $halt) {
                array_pop($this->dispatching);

                return $response;
            }

            // Si un booléen false est renvoyé par un écouteur, nous arrêterons de propager
            // l'événement à tous les autres auditeurs dans la chaîne, sinon nous continuons
            // boucle à travers les écouteurs et répartit chacun dans notre séquence.
            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        array_pop($this->dispatching);

        if (! $halt) {
            return $responses;
        }
    }

    /**
     * Diffusez la classe d'événement donnée.
     *
     * @param  \Two\Broadcasting\Contracts\ShouldBroadcastInterface  $event
     * @return void
     */
    protected function broadcastEvent($event)
    {
        $connection = ($event instanceof ShouldBroadcastNowInterface) ? 'sync' : null;

        $queue = method_exists($event, 'onQueue') ? $event->onQueue() : null;

        $this->resolveQueue()->connection($connection)->pushOn($queue, 'Two\Broadcasting\BroadcastEvent', array(
            'event' => serialize(clone $event),
        ));
    }

    /**
     * Obtenez tous les auditeurs pour un nom d'événement donné.
     *
     * @param  string  $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        $wildcards = $this->getWildcardListeners($eventName);

        if (! isset($this->sorted[$eventName])) {
            $this->sortListeners($eventName);
        }

        return array_merge($this->sorted[$eventName], $wildcards);
    }

    /**
     * Obtenez les auditeurs génériques pour l'événement.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function getWildcardListeners($eventName)
    {
        $wildcards = array();

        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }

        return $wildcards;
    }

    /**
     * Trier les auditeurs d'un événement donné par priorité.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function sortListeners($eventName)
    {
        $this->sorted[$eventName] = array();

        // Si des écouteurs existent pour l'événement donné, nous les trions par priorité
        // afin que nous puissions les appeler dans le bon ordre. Nous les mettrons en cache
        // a trié les écouteurs d'événements afin que nous n'ayons pas à trier à nouveau 
        // tous les événements.
        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);

            $this->sorted[$eventName] = call_user_func_array(
                'array_merge', $this->listeners[$eventName]
            );
        }
    }

    /**
     * Enregistrez un écouteur d'événement auprès du répartiteur.
     *
     * @param  mixed   $listener
     * @return mixed
     */
    public function makeListener($listener)
    {
        if (is_string($listener)) {
            return $this->createClassListener($listener);
        }

        return $listener;
    }

    /**
     * Créez un écouteur basé sur une classe à l'aide du conteneur IoC.
     *
     * @param  mixed    $listener
     * @return \Closure
     */
    public function createClassListener($listener)
    {
        return function() use ($listener)
        {
            $callable = $this->createClassCallable($listener);

            // Nous allons créer un appelable de l'instance de l'écouteur et une méthode qui devrait
            // être appelé sur cette instance, alors nous passerons les arguments que nous
            // reçu dans cette méthode dans les méthodes de cette instance de classe d'écouteur.
            $data = func_get_args();

            return call_user_func_array($callable, $data);
        };
    }

    /**
     * Créez l'appel d'événement basé sur la classe.
     *
     * @param  string  $listener
     * @return callable
     */
    protected function createClassCallable($listener)
    {
        list($className, $method) = $this->parseClassCallable($listener);

        if ($this->handlerShouldBeQueued($className)) {
            return $this->createQueuedHandlerCallable($className, $method);
        }

        $instance = $this->container->make($className);

        return array($instance, $method);
    }

    /**
     * Analyser l'écouteur de classe en classe et méthode.
     *
     * @param  string  $listener
     * @return array
     */
    protected function parseClassCallable($listener)
    {
        // Si l'écouteur a un signe @, nous supposerons qu'il est utilisé pour délimiter
        // le nom de la classe à partir du nom de la méthode du handle. Cela permet aux gestionnaires
        // pour exécuter plusieurs méthodes de gestionnaire dans une seule classe pour plus 
        // de commodité.
        return array_pad(explode('@', $listener, 2), 2, 'handle');
    }

    /**
     * Déterminez si la classe de gestionnaire d'événements doit être mise en file d'attente.
     *
     * @param  string  $className
     * @return bool
     */
    protected function handlerShouldBeQueued($className)
    {
        try {
            return with(new ReflectionClass($className))->implementsInterface('Two\Queue\Contracts\ShouldQueueInterface');
        }
        catch (Exception $e) {
            return false;
        }
    }

    /**
     * Créer un appelable pour mettre un gestionnaire d'événements dans la file d'attente.
     *
     * @param  string  $className
     * @param  string  $method
     * @return \Closure
     */
    protected function createQueuedHandlerCallable($className, $method)
    {
        return function () use ($className, $method)
        {
            // Clone les arguments donnés pour la mise en file d'attente.
            $arguments = array_map(function ($a)
            {
                return is_object($a) ? clone $a : $a;

            }, func_get_args());

            if (method_exists($className, 'queue')) {
                $this->callQueueMethodOnHandler($className, $method, $arguments);
            } else {
                $this->resolveQueue()->push('Two\Events\CallQueuedHandler@call', array(
                    'class'  => $className,
                    'method' => $method,
                    'data'   => serialize($arguments),
                ));
            }
        };
    }

    /**
     * Appelez la méthode de file d'attente sur la classe de gestionnaire.
     *
     * @param  string  $className
     * @param  string  $method
     * @param  array  $arguments
     * @return void
     */
    protected function callQueueMethodOnHandler($className, $method, $arguments)
    {
        $handler = with(new ReflectionClass($className))->newInstanceWithoutConstructor();

        $handler->queue($this->resolveQueue(), 'Two\Events\CallQueuedHandler@call', array(
            'class'  => $className,
            'method' => $method,
            'data'   => serialize($arguments),
        ));
    }

    /**
     * Supprimer un ensemble d'auditeurs du répartiteur.
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event)
    {
        unset($this->listeners[$event], $this->sorted[$event]);
    }

    /**
     * Oubliez tous les auditeurs poussés.
     *
     * @return void
     */
    public function forgetPushed()
    {
        foreach ($this->listeners as $key => $value) {
            if (Str::endsWith($key, '_pushed')) {
                $this->forget($key);
            }
        }
    }

    /**
     * Obtenez l'implémentation de la file d'attente à partir du résolveur.
     *
     * @return \Two\Queue\Contracts\QueueInterface
     */
    protected function resolveQueue()
    {
        return call_user_func($this->queueResolver);
    }

    /**
     * Définissez l'implémentation du résolveur de file d'attente.
     *
     * @param  callable  $resolver
     * @return $this
     */
    public function setQueueResolver(callable $resolver)
    {
        $this->queueResolver = $resolver;

        return $this;
    }
}

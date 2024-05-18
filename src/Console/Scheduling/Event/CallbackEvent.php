<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling\Event;

use LogicException;
use InvalidArgumentException;

use Two\Container\Container;
use Two\Console\Scheduling\Event\Event;
use Two\Console\Scheduling\Contracts\MutexInterface as Mutex;


class CallbackEvent extends Event
{
    /**
     * Le rappel pour appeler.
     *
     * @var string
     */
    protected $callback;

    /**
     * Les paramètres à transmettre à la méthode.
     *
     * @var array
     */
    protected $parameters;


    /**
     * Créez une nouvelle instance d'événement.
     *
     * @param  \Two\Console\Scheduling\Contracts\MutexInterface  $mutex
     * @param  string  $callback
     * @param  array  $parameters
     * @return void
     */
    public function __construct(Mutex $mutex, $callback, array $parameters = array())
    {
        if (! is_string($this->callback) && ! is_callable($this->callback)) {
            throw new InvalidArgumentException(
                "Invalid scheduled callback event. Must be string or callable."
            );
        }

        $this->mutex = $mutex;
        $this->callback = $callback;
        $this->parameters = $parameters;
    }

    /**
     * Exécutez l'événement donné.
     *
     * @param  \Two\Container\Container  $container
     * @return mixed
     *
     * @throws \Exception
     */
    public function run(Container $container)
    {
        if ($this->description && $this->withoutOverlapping && ! $this->mutex->create($this)) {
            return;
        }

        register_shutdown_function(function ()
        {
            $this->removeMutex();
        });

        parent::callBeforeCallbacks($container);

        try {
            $response = $container->call($this->callback, $this->parameters);
        }
        finally {
            $this->removeMutex();

            parent::callAfterCallbacks($container);
        }

        return $response;
    }

    /**
     * Supprimez le fichier mutex du disque.
     *
     * @return void
     */
    protected function removeMutex()
    {
        if (isset($this->description)) {
            $this->mutex->forget($this);
        }
    }

    /**
     * Ne laissez pas les événements se chevaucher.
     *
     * @param  int  $expiresAt
     * @return $this
     */
    public function withoutOverlapping($expiresAt = 1440)
    {
        if (! isset($this->description)) {
            throw new LogicException(
                "A scheduled event name is required to prevent overlapping. Use the 'name' method before 'withoutOverlapping'."
            );
        }

        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->skip(function ()
        {
            return $this->mutex->exists($this);
        });
    }

    /**
     * Obtenez le chemin mutex pour la commande planifiée.
     *
     * @return string
     */
    public function mutexName()
    {
        return storage_path('schedule-' .sha1($this->description));
    }

    /**
     * Obtenez le résumé de l’événement pour l’afficher.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return is_string($this->callback) ? $this->callback : 'Closure';
    }
}

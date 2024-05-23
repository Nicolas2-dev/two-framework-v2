<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling;

use Two\Container\Container;
use Two\Support\ProcessUtils;
use Two\Application\Two;
use Two\Console\Scheduling\Event\Event;
use Two\Console\Scheduling\Cache\CacheMutex;
use Two\Console\Scheduling\Event\CallbackEvent;
use Two\Console\Scheduling\Contracts\MutexInterface as Mutex;

use Symfony\Component\Process\PhpExecutableFinder;


class Schedule
{
    /**
     * Tous les événements au programme.
     *
     * @var array
     */
    protected $events = array();

    /**
     * L'implémentation du mutex.
     *
     * @var \Two\Console\Scheduling\Contracts\MutexInterface
     */
    protected $mutex;


    /**
     * Créez une nouvelle instance de planification.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $class = $container->bound(Mutex::class) ? Mutex::class : CacheMutex::class;

        $this->mutex = $container->make($class);
    }

    /**
     * Ajoutez un nouvel événement de rappel à la planification.
     *
     * @param  string  $callback
     * @param  array   $parameters
     * @return \Two\Console\Scheduling\Event
     */
    public function call($callback, array $parameters = array())
    {
        $this->events[] = $event = new CallbackEvent($this->mutex, $callback, $parameters);

        return $event;
    }

    /**
     * Ajoutez un nouvel événement de commande Forge au planning.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Two\Console\Scheduling\Event
     */
    public function command($command, array $parameters = array())
    {
        $binary = ProcessUtils::escapeArgument((new PhpExecutableFinder)->find(false));

        if (defined('HHVM_VERSION')) {
            $binary .= ' --php';
        }

        if (defined('FORGE_BINARY')) {
            $forge = ProcessUtils::escapeArgument(FORGE_BINARY);
        } else {
            $forge = 'forge';
        }

        return $this->exec("{$binary} {$forge} {$command}", $parameters);
    }

    /**
     * Ajoutez un nouvel événement de commande à la planification.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Two\Console\Scheduling\Event
     */
    public function exec($command, array $parameters = array())
    {
        if (count($parameters)) {
            $command .= ' ' .$this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($this->mutex, $command);

        return $event;
    }

    /**
     * Compilez les paramètres d’une commande.
     *
     * @param  array  $parameters
     * @return string
     */
    protected function compileParameters(array $parameters)
    {
        return collect($parameters)->map(function ($value, $key)
        {
            if (is_numeric($key)) {
                return $value;
            }

            return $key .'=' .(is_numeric($value) ? $value : ProcessUtils::escapeArgument($value));

        })->implode(' ');
    }

    /**
     * Obtenez tous les événements inscrits au calendrier.
     *
     * @return array
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Obtenez tous les événements du calendrier qui sont dus.
     *
     * @param  \Two\Application\Two  $app
     * @return array
     */
    public function dueEvents(Two $app)
    {
        return array_filter($this->events, function ($event) use ($app)
        {
            return $event->isDue($app);
        });
    }
}

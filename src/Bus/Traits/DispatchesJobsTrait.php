<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus\Traits;

use Two\Bus\Contracts\DispatcherInterface as Dispatcher;
use Two\Support\Facades\App;


trait DispatchesJobsTrait
{

    /**
     * Envoyez une tâche à son gestionnaire approprié.
     *
     * @param  mixed  $job
     * @return mixed
     */
    protected function dispatch($job)
    {
        $dispatcher = App::make(Dispatcher::class);

        return $dispatcher->dispatch($job);
    }

    /**
     * Distribuez une tâche à son gestionnaire approprié dans le processus en cours.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function dispatchNow($job)
    {
        $dispatcher = App::make(Dispatcher::class);

        return $dispatcher->dispatchNow($job);
    }
}

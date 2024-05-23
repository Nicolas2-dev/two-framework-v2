<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Providers;

use Two\Events\Dispatcher;

use Two\Application\Providers\ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    
    /**
     * Les mappages de gestionnaires d'événements pour l'application.
     *
     * @var array
     */
    protected $listen = array();

    /**
     * Les classes d'abonnés à inscrire.
     *
     * @var array
     */
    protected $subscribe = array();


    /**
     * Enregistrez les écouteurs d'événements de l'application.
     *
     * @param  \Two\Events\Dispatcher  $events
     * @return void
     */
    public function boot(Dispatcher $events)
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }

        foreach ($this->subscribe as $subscriber) {
            $events->subscribe($subscriber);
        }
    }

    /**
     * Chargez le fichier d'événements standard pour l'application.
     *
     * @param  string  $path
     * @return mixed
     */
    protected function loadEventsFrom($path)
    {
        $events = $this->app['events'];

        return require $path;
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        //
    }

    /**
     * Obtenez les événements et les gestionnaires.
     *
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }
}

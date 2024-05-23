<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console;

use Two\Console\TwoConsole;
use Two\Application\Two;


class Forge
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * L'instance de console Forge.
     *
     * @var  \Two\Console\Console
     */
    protected $forge;


    /**
     * Créez une nouvelle instance d'exécuteur de commandes Forge.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    public function __construct(Two $app)
    {
        $this->app = $app;
    }

    /**
     * Obtenez l'instance de console Forge.
     *
     * @return \Two\Console\Console
     */
    protected function getForge()
    {
        if (isset($this->forge)) {
            return $this->forge;
        }

        $this->app->loadDeferredProviders();

        $this->forge = TwoConsole::make($this->app);

        return $this->forge->boot();
    }

    /**
     * Transmettez dynamiquement toutes les méthodes manquantes à console forge.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $instance = $this->getForge();

        return call_user_func_array(array($instance, $method), $parameters);
    }

}

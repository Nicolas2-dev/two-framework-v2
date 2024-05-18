<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console;

use Two\Console\TwoConsole;
use Two\TwoApplication\TwoApplication;


class Forge
{
    /**
     * The application instance.
     *
     * @var Two\TwoApplication\TwoApplication
     */
    protected $app;

    /**
     * The forge console instance.
     *
     * @var  \Two\Console\Console
     */
    protected $forge;


    /**
     * Create a new forge command runner instance.
     *
     * @param  Two\TwoApplication\TwoApplication  $app
     * @return void
     */
    public function __construct(TwoApplication $app)
    {
        $this->app = $app;
    }

    /**
     * Get the forge console instance.
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
     * Dynamically pass all missing methods to console forge.
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

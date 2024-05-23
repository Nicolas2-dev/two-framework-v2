<?php

namespace Two\Environment\Contracts;


interface EnvironmentVariablesLoaderInterface
{
    
    /**
     * Load the environment variables for the given environment.
     *
     * @param  string  $environment
     * @return array
     */
    public function load($environment = null);

}
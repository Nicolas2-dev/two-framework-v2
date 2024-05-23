<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Providers;

use Two\Application\Providers\ServiceProvider;
use Two\Auth\Contracts\GateInterface as GateContract;


class AuthServiceProvider extends ServiceProvider
{
    
    /**
     * Les mappages de stratégie pour l'application.
     *
     * @var array
     */
    protected $policies = array();

    
    /**
     * Enregistrez les stratégies de l'application.
     *
     * @param  \Two\Auth\Contracts\GateInterface  $gate
     * @return void
     */
    public function registerPolicies(GateContract $gate)
    {
        foreach ($this->policies as $key => $value) {
            $gate->policy($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        //
    }
}

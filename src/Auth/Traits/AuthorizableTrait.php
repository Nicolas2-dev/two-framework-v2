<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Traits;

use Two\Support\Facades\App;


trait AuthorizableTrait
{
    /**
     * Déterminez si l’entité a une capacité donnée.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($ability, $arguments = array())
    {
        $gate = App::make('Two\Auth\Contracts\GateInterface')->forUser($this);

        return $gate->check($ability, $arguments);
    }

    /**
     * Déterminez si l’entité n’a pas une capacité donnée.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cannot($ability, $arguments = array())
    {
        return ! $this->can($ability, $arguments);
    }
}

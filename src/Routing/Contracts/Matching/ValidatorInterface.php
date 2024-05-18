<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Contracts\Matching;

use Two\Http\Request;
use Two\Routing\Route;


interface ValidatorInterface
{
    /**
     * Validez une règle donnée par rapport à un itinéraire et une demande.
     *
     * @param  \Two\Routing\Route  $route
     * @param  \Two\Http\Request  $request
     * @return bool
     */
    public function matches(Route $route, Request $request);

}

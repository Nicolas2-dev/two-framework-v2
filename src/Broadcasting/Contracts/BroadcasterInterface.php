<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Contracts;

use Two\Http\Request;


interface BroadcasterInterface
{
    /**
     * Authentifiez la demande entrante pour un canal donné.
     *
     * @param  \Two\Http\Request  $request
     * @return mixed
     */
    public function authenticate(Request $request);

    /**
     * Renvoie la réponse d'authentification valide.
     *
     * @param  \Two\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse(Request $request, $result);

    /**
     * Diffusez l’événement donné.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = array());
}

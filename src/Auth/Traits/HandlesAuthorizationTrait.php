<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Traits;

use Two\Auth\Access\Response;
use Two\Auth\Exception\UnAuthorizedException;


trait HandlesAuthorizationTrait
{
    /**
     * Créez une nouvelle réponse d’accès.
     *
     * @param  string|null  $message
     * @return \Two\Auth\Access\Response
     */
    protected function allow($message = null)
    {
        return new Response($message);
    }

    /**
     * Lève une exception non autorisée.
     *
     * @param  string  $message
     * @return void
     *
     * @throws \Two\Auth\Exception\UnauthorizedException
     */
    protected function deny($message = null)
    {
        $message = $message ?: __d('Two', 'This action is unauthorized.');

        throw new UnAuthorizedException($message);
    }
}

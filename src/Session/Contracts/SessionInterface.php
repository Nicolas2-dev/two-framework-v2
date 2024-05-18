<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session\Contracts;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface as BaseSessionInterface;


interface SessionInterface extends BaseSessionInterface
{
    /**
     * Obtenez l’instance du gestionnaire de session.
     *
     * @return \SessionHandlerInterface
     */
    public function getHandler();

    /**
     * Déterminez si le gestionnaire de session a besoin d’une demande.
     *
     * @return bool
     */
    public function handlerNeedsRequest();

    /**
     * Définissez la requête sur l'instance du gestionnaire.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function setRequestOnHandler(Request $request);

    /**
     * Définissez l'URL "précédente" dans la session.
     *
     * @param  string  $url
     * @return void
     */
    public function setPreviousUrl($url);

}

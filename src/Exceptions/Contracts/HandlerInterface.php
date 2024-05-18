<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\Contracts;

use Exception;

use Two\Http\Request;


interface HandlerInterface
{

    /**
     * Signaler ou consignateur une exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e);

    /**
     * Rendre une exception dans une réponse HTTP.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render(Request $request, Exception $e);

}

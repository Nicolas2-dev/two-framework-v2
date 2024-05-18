<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Facades;

use Two\Support\Facades\Facade;


/**
 * @see \Two\Encryption\Encrypter
 */
class Crypt extends Facade
{
    /**
     * Obtenez le nom enregistré du composant.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'encrypter'; }

}

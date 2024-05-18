<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\Contracts;

use Two\Exceptions\Exception\FatalErrorException;


/**
 * Tente de convertir les erreurs fatales en exceptions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface FatalErrorHandlerInterface
{
    /**
     * Tente de convertir une erreur en exception.
     *
     * @param array               $error    Un tableau tel que renvoyé par error_get_last()
     * @param FatalErrorException $exception Une instance de FatalErrorException
     *
     * @return FatalErrorException|null Une instance de FatalErrorException si la classe est capable de convertir l'erreur, null sinon
     */
    public function handleError(array $error, FatalErrorException $exception);
}

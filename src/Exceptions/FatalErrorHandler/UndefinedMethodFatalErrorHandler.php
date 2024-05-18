<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\FatalErrorHandler;

use Two\Exceptions\Exception\FatalErrorException;
use Two\Exceptions\Exception\UndefinedMethodException;
use Two\Exceptions\Contracts\FatalErrorHandlerInterface;


/**
 * ErrorHandler pour les méthodes non définies.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UndefinedMethodFatalErrorHandler implements FatalErrorHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleError(array $error, FatalErrorException $exception)
    {
        preg_match('/^Call to undefined method (.*)::(.*)\(\)$/', $error['message'], $matches);
        if (!$matches) {
            return;
        }

        $className = $matches[1];
        $methodName = $matches[2];

        $message = sprintf('Attempted to call an undefined method named "%s" of class "%s".', $methodName, $className);

        if (!class_exists($className) || null === $methods = get_class_methods($className)) {
            // échec d'obtention de la classe ou de ses méthodes sur lesquelles une méthode inconnue a été appelée (par exemple sur une classe anonyme)
            return new UndefinedMethodException($message, $exception);
        }

        $candidates = [];
        foreach ($methods as $definedMethodName) {
            $lev = levenshtein($methodName, $definedMethodName);
            if ($lev <= \strlen($methodName) / 3 || false !== strpos($definedMethodName, $methodName)) {
                $candidates[] = $definedMethodName;
            }
        }

        if ($candidates) {
            sort($candidates);
            $last = array_pop($candidates).'"?';
            if ($candidates) {
                $candidates = 'e.g. "'.implode('", "', $candidates).'" or "'.$last;
            } else {
                $candidates = '"'.$last;
            }

            $message .= "\nDid you mean to call ".$candidates;
        }

        return new UndefinedMethodException($message, $exception);
    }
}

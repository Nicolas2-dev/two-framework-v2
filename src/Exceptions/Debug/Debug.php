<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\Debug;

use Two\Exceptions\Debug\DebugClassLoader;
use Two\Exceptions\Debug\DebugErrorHandler;
use Two\Exceptions\Buffering\BufferingLogger;
use Two\Exceptions\Debug\DebugExceptionHandler;


/**
 * Enregistre tous les outils de débogage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Debug
{
    private static $enabled = false;

    /**
     * Active les outils de débogage.
     *
     * Cette méthode enregistre un gestionnaire d'erreurs et un gestionnaire d'exceptions.
     *
     * @param int  $errorReportingLevel Le niveau de rapport d'erreurs souhaité
     * @param bool $displayErrors Que ce soit pour afficher les erreurs (pour le développement) ou simplement les enregistrer (pour la production)
      
     */
    public static function enable($errorReportingLevel = E_ALL, $displayErrors = true)
    {
        if (static::$enabled) {
            return;
        }

        static::$enabled = true;

        if (null !== $errorReportingLevel) {
            error_reporting($errorReportingLevel);
        } else {
            error_reporting(E_ALL);
        }

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            ini_set('display_errors', 0);
            DebugExceptionHandler::register();
        } elseif ($displayErrors && (!filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN) || ini_get('error_log'))) {
            // CLI - afficher les erreurs uniquement si elles ne sont pas déjà enregistrées sur STDERR
            ini_set('display_errors', 1);
        }
        if ($displayErrors) {
            DebugErrorHandler::register(new DebugErrorHandler(new BufferingLogger()));
        } else {
            DebugErrorHandler::register()->throwAt(0, true);
        }

        DebugClassLoader::enable();
    }
}

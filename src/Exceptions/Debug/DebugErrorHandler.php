<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\Debug;

use Throwable;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Two\Exceptions\Buffering\BufferingLogger;
use Two\Exceptions\Exception\FatalErrorException;
use Two\Exceptions\Exception\FatalThrowableError;

use Two\Exceptions\Exception\OutOfMemoryException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

use Symfony\Component\ErrorHandler\Exception\SilencedErrorContext;
use Two\Exceptions\FatalErrorHandler\ClassNotFoundFatalErrorHandler;
use Two\Exceptions\FatalErrorHandler\UndefinedMethodFatalErrorHandler;
use Two\Exceptions\FatalErrorHandler\UndefinedFunctionFatalErrorHandler;


/**
* Un ErrorHandler générique pour le moteur PHP.
 *
 * Fournit des champs de cinq bits qui contrôlent la façon dont les erreurs sont traitées :
 * - throwErrors : erreurs renvoyées sous la forme \ErrorException
 * - logErrors : erreurs enregistrées, lorsqu'elles ne sont pas @-silenced
 * - scopedErrors : erreurs générées ou enregistrées avec leur contexte local
 * - tracedErrors : erreurs enregistrées avec leur trace de pile
 * - screamedErrors : jamais d'erreurs @-silenced
 *
 * Chaque niveau d'erreur peut être enregistré par un objet enregistreur PSR-3 dédié.
 * Les cris ne s'appliquent qu'à la journalisation.
 * Le lancer a priorité sur la journalisation.
 * Les exceptions non interceptées sont enregistrées sous le nom E_ERROR.
 * Les niveaux E_DEPRECATED et E_USER_DEPRECATED ne lancent jamais.
 * Les niveaux E_RECOVERABLE_ERROR et E_USER_ERROR sont toujours lancés.
 * Les erreurs non détectables qui peuvent être détectées au moment de l'arrêt sont enregistrées lorsque le champ du bit de cri le permet.
 * Comme les erreurs ont un coût en termes de performances, les erreurs répétées sont toutes enregistrées afin que le développeur puisse
 * peut les considérer et les évaluer comme étant plus importants à corriger que d'autres du même niveau.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DebugErrorHandler
{
    private $levels = [
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
        E_NOTICE => 'Notice',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_WARNING => 'Warning',
        E_USER_WARNING => 'User Warning',
        E_COMPILE_WARNING => 'Compile Warning',
        E_CORE_WARNING => 'Core Warning',
        E_USER_ERROR => 'User Error',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_PARSE => 'Parse Error',
        E_ERROR => 'Error',
        E_CORE_ERROR => 'Core Error',
    ];

    private $loggers = [
        E_DEPRECATED => [null, LogLevel::INFO],
        E_USER_DEPRECATED => [null, LogLevel::INFO],
        E_NOTICE => [null, LogLevel::WARNING],
        E_USER_NOTICE => [null, LogLevel::WARNING],
        E_STRICT => [null, LogLevel::WARNING],
        E_WARNING => [null, LogLevel::WARNING],
        E_USER_WARNING => [null, LogLevel::WARNING],
        E_COMPILE_WARNING => [null, LogLevel::WARNING],
        E_CORE_WARNING => [null, LogLevel::WARNING],
        E_USER_ERROR => [null, LogLevel::CRITICAL],
        E_RECOVERABLE_ERROR => [null, LogLevel::CRITICAL],
        E_COMPILE_ERROR => [null, LogLevel::CRITICAL],
        E_PARSE => [null, LogLevel::CRITICAL],
        E_ERROR => [null, LogLevel::CRITICAL],
        E_CORE_ERROR => [null, LogLevel::CRITICAL],
    ];

    private $thrownErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private $scopedErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private $tracedErrors = 0x77FB; // E_ALL - E_STRICT - E_PARSE
    private $screamedErrors = 0x55; // E_ERROR + E_CORE_ERROR + E_COMPILE_ERROR + E_PARSE
    private $loggedErrors = 0;
    private $traceReflector;

    private $isRecursive = 0;
    private $isRoot = false;
    private $exceptionHandler;
    private $bootstrappingLogger;

    private static $reservedMemory;
    private static $toStringException = null;
    private static $silencedErrorCache = [];
    private static $silencedErrorCount = 0;
    private static $exitCode = 0;

    /**
     * Enregistre le gestionnaire d'erreurs.
     *
     * @param self|null $handler Le gestionnaire à inscrire
     * @param bool      $replace S'il faut remplacer ou non un gestionnaire existant
     *
     * @return self Le gestionnaire d'erreurs enregistré
     */
    public static function register(self $handler = null, $replace = true)
    {
        if (null === self::$reservedMemory) {
            self::$reservedMemory = str_repeat('x', 10240);
            register_shutdown_function(__CLASS__.'::handleFatalError');
        }

        if ($handlerIsNew = null === $handler) {
            $handler = new static();
        }

        if (null === $prev = set_error_handler([$handler, 'handleError'])) {
            restore_error_handler();
            // Spécifier les types d'erreurs plus tôt nous exposerait à https://bugs.php.net/63206
            set_error_handler([$handler, 'handleError'], $handler->thrownErrors | $handler->loggedErrors);
            $handler->isRoot = true;
        }

        if ($handlerIsNew && \is_array($prev) && $prev[0] instanceof self) {
            $handler = $prev[0];
            $replace = false;
        }
        if (!$replace && $prev) {
            restore_error_handler();
            $handlerIsRegistered = \is_array($prev) && $handler === $prev[0];
        } else {
            $handlerIsRegistered = true;
        }
        if (\is_array($prev = set_exception_handler([$handler, 'handleException'])) && $prev[0] instanceof self) {
            restore_exception_handler();
            if (!$handlerIsRegistered) {
                $handler = $prev[0];
            } elseif ($handler !== $prev[0] && $replace) {
                set_exception_handler([$handler, 'handleException']);
                $p = $prev[0]->setExceptionHandler(null);
                $handler->setExceptionHandler($p);
                $prev[0]->setExceptionHandler($p);
            }
        } else {
            $handler->setExceptionHandler($prev);
        }

        $handler->throwAt(E_ALL & $handler->thrownErrors, true);

        return $handler;
    }

    public function __construct(BufferingLogger $bootstrappingLogger = null)
    {
        if ($bootstrappingLogger) {
            $this->bootstrappingLogger = $bootstrappingLogger;
            $this->setDefaultLogger($bootstrappingLogger);
        }
        $this->traceReflector = new \ReflectionProperty('Exception', 'trace');
        $this->traceReflector->setAccessible(true);
    }

    /**
     * Définit un enregistreur sur des niveaux d'erreurs non attribués.
     *
     * @param LoggerInterface $logger Un enregistreur PSR-3 à mettre par défaut pour les niveaux donnés 
     * @param array|int       $levels  Une carte de tableau de E_* vers LogLevel::* ou un champ de bits entier de constantes E_*
     * @param bool            $replace S'il faut remplacer ou non un enregistreur existant
     */
    public function setDefaultLogger(LoggerInterface $logger, $levels = E_ALL, $replace = false)
    {
        $loggers = [];

        if (\is_array($levels)) {
            foreach ($levels as $type => $logLevel) {
                if (empty($this->loggers[$type][0]) || $replace || $this->loggers[$type][0] === $this->bootstrappingLogger) {
                    $loggers[$type] = [$logger, $logLevel];
                }
            }
        } else {
            if (null === $levels) {
                $levels = E_ALL;
            }
            foreach ($this->loggers as $type => $log) {
                if (($type & $levels) && (empty($log[0]) || $replace || $log[0] === $this->bootstrappingLogger)) {
                    $log[0] = $logger;
                    $loggers[$type] = $log;
                }
            }
        }

        $this->setLoggers($loggers);
    }

    /**
     * Définit un enregistreur pour chaque niveau d'erreur.
     *
     * @param array $loggers Niveaux d'erreur sur la carte [LoggerInterface|null, LogLevel::*]
     *
     * @return array La carte précédente
     *
     * @throws \InvalidArgumentException
     */
    public function setLoggers(array $loggers)
    {
        $prevLogged = $this->loggedErrors;
        $prev = $this->loggers;
        $flush = [];

        foreach ($loggers as $type => $log) {
            if (!isset($prev[$type])) {
                throw new InvalidArgumentException('Unknown error type: '.$type);
            }
            if (!\is_array($log)) {
                $log = [$log];
            } elseif (!\array_key_exists(0, $log)) {
                throw new InvalidArgumentException('No logger provided');
            }
            if (null === $log[0]) {
                $this->loggedErrors &= ~$type;
            } elseif ($log[0] instanceof LoggerInterface) {
                $this->loggedErrors |= $type;
            } else {
                throw new InvalidArgumentException('Invalid logger provided');
            }
            $this->loggers[$type] = $log + $prev[$type];

            if ($this->bootstrappingLogger && $prev[$type][0] === $this->bootstrappingLogger) {
                $flush[$type] = $type;
            }
        }
        $this->reRegister($prevLogged | $this->thrownErrors);

        if ($flush) {
            foreach ($this->bootstrappingLogger->cleanLogs() as $log) {
                $type = $log[2]['exception'] instanceof \ErrorException ? $log[2]['exception']->getSeverity() : E_ERROR;
                if (!isset($flush[$type])) {
                    $this->bootstrappingLogger->log($log[0], $log[1], $log[2]);
                } elseif ($this->loggers[$type][0]) {
                    $this->loggers[$type][0]->log($this->loggers[$type][1], $log[1], $log[2]);
                }
            }
        }

        return $prev;
    }

    /**
     * Définit un gestionnaire d'exceptions utilisateur.
     *
     * @param callable $handler Un gestionnaire qui sera appelé sur Exception
     *
     * @return callable|null Le gestionnaire d'exceptions précédent
     */
    public function setExceptionHandler(callable $handler = null)
    {
        $prev = $this->exceptionHandler;
        $this->exceptionHandler = $handler;

        return $prev;
    }

    /**
     * Définit les niveaux d'erreur PHP qui lèvent une exception lorsqu'une erreur PHP se produit.
     *
     * @param int  $levels  Un champ binaire de constantes E_* pour les erreurs générées
     * @param bool $replace Remplacer ou modifier la valeur précédente
     *
     * @return int La valeur précédente
     */
    public function throwAt($levels, $replace = false)
    {
        $prev = $this->thrownErrors;
        $this->thrownErrors = ($levels | E_RECOVERABLE_ERROR | E_USER_ERROR) & ~E_USER_DEPRECATED & ~E_DEPRECATED;
        if (!$replace) {
            $this->thrownErrors |= $prev;
        }
        $this->reRegister($prev | $this->loggedErrors);

        return $prev;
    }

    /**
     * Définit les niveaux d'erreur PHP pour lesquels les variables locales sont conservées.
     *
     * @param int  $levels  Un champ binaire de constantes E_* pour les erreurs de portée
     * @param bool $replace Remplacer ou modifier la valeur précédente
     *
     * @return int La valeur précédente
     */
    public function scopeAt($levels, $replace = false)
    {
        $prev = $this->scopedErrors;
        $this->scopedErrors = (int) $levels;
        if (!$replace) {
            $this->scopedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Définit les niveaux d'erreur PHP pour lesquels la trace de pile est préservée.
     *
     * @param int  $levels  Un champ binaire de constantes E_* pour les erreurs tracées
     * @param bool $replace Remplacer ou modifier la valeur précédente
     *
     * @return int La valeur précédente
     */
    public function traceAt($levels, $replace = false)
    {
        $prev = $this->tracedErrors;
        $this->tracedErrors = (int) $levels;
        if (!$replace) {
            $this->tracedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Définit les niveaux d'erreur où l'opérateur @ est ignoré.
     *
     * @param int  $levels  Un champ binaire de constantes E_* pour les erreurs criées
     * @param bool $replace Remplacer ou modifier la valeur précédente
     *
     * @return int La valeur précédente
     */
    public function screamAt($levels, $replace = false)
    {
        $prev = $this->screamedErrors;
        $this->screamedErrors = (int) $levels;
        if (!$replace) {
            $this->screamedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Se réinscrit en tant que gestionnaire d'erreurs PHP si les niveaux changent.
     */
    private function reRegister($prev)
    {
        if ($prev !== $this->thrownErrors | $this->loggedErrors) {
            $handler = set_error_handler('var_dump');
            $handler = \is_array($handler) ? $handler[0] : null;
            restore_error_handler();
            if ($handler === $this) {
                restore_error_handler();
                if ($this->isRoot) {
                    set_error_handler([$this, 'handleError'], $this->thrownErrors | $this->loggedErrors);
                } else {
                    set_error_handler([$this, 'handleError']);
                }
            }
        }
    }

    /**
     * Gère les erreurs en les filtrant puis en les enregistrant en fonction des champs de bits configurés.
     *
     * @param int    $type    Une des constantes E_*
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @return bool Renvoie false lorsqu'aucune gestion n'a lieu afin que le moteur PHP puisse gérer l'erreur lui-même
     *
     * @throws \ErrorException Lorsque $this->thrownErrors le demande
     *
     * @internal
     */
    public function handleError($type, $message, $file, $line)
    {
        // @deprecated to be removed in Symfony 5.0
        if (\PHP_VERSION_ID >= 70300 && $message && '"' === $message[0] && 0 === strpos($message, '"continue') && preg_match('/^"continue(?: \d++)?" targeting switch is equivalent to "break(?: \d++)?"\. Did you mean to use "continue(?: \d++)?"\?$/', $message)) {
            $type = E_DEPRECATED;
        }

        // Level est le niveau de rapport d’erreurs actuel pour gérer les erreurs silencieuses.
        $level = error_reporting();
        $silenced = 0 === ($level & $type);
        // Les erreurs graves ne peuvent pas être réduites au silence.
        $level |= E_RECOVERABLE_ERROR | E_USER_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
        $log = $this->loggedErrors & $type;
        $throw = $this->thrownErrors & $type & $level;
        $type &= $level | $this->screamedErrors;

        if (!$type || (!$log && !$throw)) {
            return !$silenced && $type && $log;
        }
        $scope = $this->scopedErrors & $type;

        if (4 < $numArgs = \func_num_args()) {
            $context = $scope ? (func_get_arg(4) ?: []) : [];
        } else {
            $context = [];
        }

        if (isset($context['GLOBALS']) && $scope) {
            $e = $context;                  // Quelle que soit la signature de la méthode,
            unset($e['GLOBALS'], $context); // $context est toujours une référence en 5.3
            $context = $e;
        }

        if (false !== strpos($message, "class@anonymous\0")) {
            $logMessage = $this->levels[$type].': '.(new FlattenException())->setMessage($message)->getMessage();
        } else {
            $logMessage = $this->levels[$type].': '.$message;
        }

        if (null !== self::$toStringException) {
            $errorAsException = self::$toStringException;
            self::$toStringException = null;
        } elseif (!$throw && !($type & $level)) {
            if (!isset(self::$silencedErrorCache[$id = $file.':'.$line])) {
                $lightTrace = $this->tracedErrors & $type ? $this->cleanTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), $type, $file, $line, false) : [];
                $errorAsException = new SilencedErrorContext($type, $file, $line, isset($lightTrace[1]) ? [$lightTrace[0]] : $lightTrace);
            } elseif (isset(self::$silencedErrorCache[$id][$message])) {
                $lightTrace = null;
                $errorAsException = self::$silencedErrorCache[$id][$message];
                ++$errorAsException->count;
            } else {
                $lightTrace = [];
                $errorAsException = null;
            }

            if (100 < ++self::$silencedErrorCount) {
                self::$silencedErrorCache = $lightTrace = [];
                self::$silencedErrorCount = 1;
            }
            if ($errorAsException) {
                self::$silencedErrorCache[$id][$message] = $errorAsException;
            }
            if (null === $lightTrace) {
                return;
            }
        } else {
            $errorAsException = new \ErrorException($logMessage, 0, $type, $file, $line);

            if ($throw || $this->tracedErrors & $type) {
                $backtrace = $errorAsException->getTrace();
                $lightTrace = $this->cleanTrace($backtrace, $type, $file, $line, $throw);
                $this->traceReflector->setValue($errorAsException, $lightTrace);
            } else {
                $this->traceReflector->setValue($errorAsException, []);
                $backtrace = [];
            }
        }

        if ($throw) {
            if (E_USER_ERROR & $type) {
                for ($i = 1; isset($backtrace[$i]); ++$i) {
                    if (isset($backtrace[$i]['function'], $backtrace[$i]['type'], $backtrace[$i - 1]['function'])
                        && '__toString' === $backtrace[$i]['function']
                        && '->' === $backtrace[$i]['type']
                        && !isset($backtrace[$i - 1]['class'])
                        && ('trigger_error' === $backtrace[$i - 1]['function'] || 'user_error' === $backtrace[$i - 1]['function'])
                    ) {
                        // Ici, nous savons que trigger_error() a été appelé depuis __toString().
                        // PHP déclenche une erreur fatale lors du lancement depuis __toString().
                        // Une petite convention permet de contourner la limitation :
                        // étant donné une exception $e interceptée dans __toString(), quittant la méthode avec
                        // `return trigger_error($e, E_USER_ERROR);` autorise ce gestionnaire d'erreurs
                        // pour que $e passe la barrière __toString().

                        foreach ($context as $e) {
                            if ($e instanceof Throwable && $e->__toString() === $message) {
                                self::$toStringException = $e;

                                return true;
                            }
                        }

                        // Afficher le message d'erreur d'origine au lieu de celui par défaut.
                        $this->handleException($errorAsException);

                        // Arrêtez le processus en renvoyant l'erreur au gestionnaire natif.
                        return false;
                    }
                }
            }

            throw $errorAsException;
        }

        if ($this->isRecursive) {
            $log = 0;
        } else {
            if (!\defined('HHVM_VERSION')) {
                $currentErrorHandler = set_error_handler('var_dump');
                restore_error_handler();
            }

            try {
                $this->isRecursive = true;
                $level = ($type & $level) ? $this->loggers[$type][1] : LogLevel::DEBUG;
                $this->loggers[$type][0]->log($level, $logMessage, $errorAsException ? ['exception' => $errorAsException] : []);
            } finally {
                $this->isRecursive = false;

                if (!\defined('HHVM_VERSION')) {
                    set_error_handler($currentErrorHandler);
                }
            }
        }

        return !$silenced && $type && $log;
    }

    /**
     * Gère une exception en la journalisant puis en la transmettant à un autre gestionnaire.
     *
     * @param \Exception|\Throwable $exception Une exception à gérer
     * @param array                 $error     Un tableau renvoyé par error_get_last()  
     *
     * @internal
     */
    public function handleException($exception, array $error = null)
    {
        if (null === $error) {
            self::$exitCode = 255;
        }
        if (!$exception instanceof \Exception) {
            $exception = new FatalThrowableError($exception);
        }
        $type = $exception instanceof FatalErrorException ? $exception->getSeverity() : E_ERROR;
        $handlerException = null;

        if (($this->loggedErrors & $type) || $exception instanceof FatalThrowableError) {
            if (false !== strpos($message = $exception->getMessage(), "class@anonymous\0")) {
                $message = (new FlattenException())->setMessage($message)->getMessage();
            }
            if ($exception instanceof FatalErrorException) {
                if ($exception instanceof FatalThrowableError) {
                    $error = [
                        'type' => $type,
                        'message' => $message,
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ];
                } else {
                    $message = 'Fatal '.$message;
                }
            } elseif ($exception instanceof \ErrorException) {
                $message = 'Uncaught '.$message;
            } else {
                $message = 'Uncaught Exception: '.$message;
            }
        }
        if ($this->loggedErrors & $type) {
            try {
                $this->loggers[$type][0]->log($this->loggers[$type][1], $message, ['exception' => $exception]);
            } catch (Throwable $handlerException) {
            }
        }
        if ($exception instanceof FatalErrorException && !$exception instanceof OutOfMemoryException && $error) {
            foreach ($this->getFatalErrorHandlers() as $handler) {
                if ($e = $handler->handleError($error, $exception)) {
                    $exception = $e;
                    break;
                }
            }
        }
        $exceptionHandler = $this->exceptionHandler;
        $this->exceptionHandler = null;
        try {
            if (null !== $exceptionHandler) {
                return $exceptionHandler($exception);
            }
            $handlerException = $handlerException ?: $exception;
        } catch (\Throwable $handlerException) {
        }
        if ($exception === $handlerException) {
            self::$reservedMemory = null; // Désactivez le gestionnaire d'erreur fatale
            throw $exception; // Redonner $exception au gestionnaire natif
        }
        $this->handleException($handlerException);
    }

    /**
     * Fonction enregistrée d'arrêt pour gérer les erreurs fatales PHP.
     *
     * @param array $error Un tableau renvoyé par error_get_last()
     *
     * @internal
     */
    public static function handleFatalError(array $error = null)
    {
        if (null === self::$reservedMemory) {
            return;
        }

        $handler = self::$reservedMemory = null;
        $handlers = [];
        $previousHandler = null;
        $sameHandlerLimit = 10;

        while (!\is_array($handler) || !$handler[0] instanceof self) {
            $handler = set_exception_handler('var_dump');
            restore_exception_handler();

            if (!$handler) {
                break;
            }
            restore_exception_handler();

            if ($handler !== $previousHandler) {
                array_unshift($handlers, $handler);
                $previousHandler = $handler;
            } elseif (0 === --$sameHandlerLimit) {
                $handler = null;
                break;
            }
        }
        foreach ($handlers as $h) {
            set_exception_handler($h);
        }
        if (!$handler) {
            return;
        }
        if ($handler !== $h) {
            $handler[0]->setExceptionHandler($h);
        }
        $handler = $handler[0];
        $handlers = [];

        if ($exit = null === $error) {
            $error = error_get_last();
        }

        if ($error && $error['type'] &= E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR) {
            // Ne lançons plus mais continuons à nous connecter
            $handler->throwAt(0, true);
            $trace = isset($error['backtrace']) ? $error['backtrace'] : null;

            if (0 === strpos($error['message'], 'Allowed memory') || 0 === strpos($error['message'], 'Out of memory')) {
                $exception = new OutOfMemoryException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, false, $trace);
            } else {
                $exception = new FatalErrorException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, true, $trace);
            }
        } else {
            $exception = null;
        }

        try {
            if (null !== $exception) {
                self::$exitCode = 255;
                $handler->handleException($exception, $error);
            }
        } catch (FatalErrorException $e) {
            // Ignorez ce nouveau lancer
        }

        if ($exit && self::$exitCode) {
            $exitCode = self::$exitCode;
            register_shutdown_function('register_shutdown_function', function () use ($exitCode) { exit($exitCode); });
        }
    }

    /**
     * Obtient les gestionnaires d’erreurs fatales.
     *
     * Remplacez cette méthode si vous souhaitez définir davantage de gestionnaires d'erreurs fatales.
     *
     * @return FatalErrorHandlerInterface[] Un tableau de FatalErrorHandlerInterface
     */
    protected function getFatalErrorHandlers()
    {
        return [
            new UndefinedFunctionFatalErrorHandler(),
            new UndefinedMethodFatalErrorHandler(),
            new ClassNotFoundFatalErrorHandler(),
        ];
    }

    /**
     * Nettoie la trace en supprimant les arguments de fonction et les frames ajoutés par le gestionnaire d'erreurs et DebugClassLoader.
     */
    private function cleanTrace($backtrace, $type, $file, $line, $throw)
    {
        $lightTrace = $backtrace;

        for ($i = 0; isset($backtrace[$i]); ++$i) {
            if (isset($backtrace[$i]['file'], $backtrace[$i]['line']) && $backtrace[$i]['line'] === $line && $backtrace[$i]['file'] === $file) {
                $lightTrace = \array_slice($lightTrace, 1 + $i);
                break;
            }
        }
        if (class_exists(DebugClassLoader::class, false)) {
            for ($i = \count($lightTrace) - 2; 0 < $i; --$i) {
                if (DebugClassLoader::class === ($lightTrace[$i]['class'] ?? null)) {
                    array_splice($lightTrace, --$i, 2);
                }
            }
        }
        if (!($throw || $this->scopedErrors & $type)) {
            for ($i = 0; isset($lightTrace[$i]); ++$i) {
                unset($lightTrace[$i]['args'], $lightTrace[$i]['object']);
            }
        }

        return $lightTrace;
    }
}

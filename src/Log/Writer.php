<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Log;

use Closure;
use RuntimeException;
use InvalidArgumentException;

use Two\Events\Contracts\DispatcherInterface as EventsDispatcher;
use Two\Application\Contracts\JsonableInterface as Jsonable;
use Two\Application\Contracts\ArrayableInterface as Arrayable;

use Monolog\Handler\SyslogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;

use Psr\Log\LoggerInterface as PsrLoggerInterface;


class Writer implements PsrLoggerInterface
{

    /**
     * L'instance de l'enregistreur Monolog.
     *
     * @var \Monolog\Logger
     */
    protected $monolog;

    /**
     * Instance du répartiteur d’événements.
     *
     * @var \Two\Events\Contracts\DispatcherInterface
     */
    protected $events;

    /**
     * Les niveaux de journalisation.
     *
     * @var array
     */
    protected $levels = array(
        'debug'     => MonologLogger::DEBUG,
        'info'      => MonologLogger::INFO,
        'notice'    => MonologLogger::NOTICE,
        'warning'   => MonologLogger::WARNING,
        'error'     => MonologLogger::ERROR,
        'critical'  => MonologLogger::CRITICAL,
        'alert'     => MonologLogger::ALERT,
        'emergency' => MonologLogger::EMERGENCY,
    );


    /**
     * Créez une nouvelle instance de rédacteur de journal.
     *
     * @param  \Monolog\Logger  $monolog
     * @param  \Two\Events\Contracts\DispatcherInterface  $dispatcher
     * @return void
     */
    public function __construct(MonologLogger $monolog, EventsDispatcher $events = null)
    {
        $this->monolog = $monolog;

        if (! is_null($events)) {
            $this->events = $events;
        }
    }

    /**
     * Enregistrez un message d'urgence dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = array()): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message d'alerte dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = array()): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message critique dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message d'erreur dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function error(string|\Stringable$message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message d'avertissement dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function warning(string|\Stringable$message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un avis dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message d'information dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message de débogage dans les journaux.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Enregistrez un message dans les journaux.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Transmettez dynamiquement les appels de journal au rédacteur.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function write($level, string|\Stringable $message, array $context = array()): void 
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Écrivez un message à Monologue.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    protected function writeLog($level, $message, $context)
    {
        $this->fireLogEvent($level, $message = $this->formatMessage($message), $context);

        $this->monolog->{$level}($message, $context);
    }

    /**
     * Enregistrez un gestionnaire de journaux de fichiers.
     *
     * @param  string  $path
     * @param  string  $level
     * @return void
     */
    public function useFiles($path, $level = 'debug')
    {
        $this->monolog->pushHandler($handler = new StreamHandler($path, $this->parseLevel($level)));

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Enregistrez un gestionnaire de journaux de fichiers quotidien.
     *
     * @param  string  $path
     * @param  int     $days
     * @param  string  $level
     * @return void
     */
    public function useDailyFiles($path, $days = 0, $level = 'debug')
    {
        $this->monolog->pushHandler(
            $handler = new RotatingFileHandler($path, $days, $this->parseLevel($level))
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Enregistrez un gestionnaire Syslog.
     *
     * @param  string  $name
     * @param  string  $level
     * @return \Psr\Log\LoggerInterface
     */
    public function useSyslog($name = 'Two', $level = 'debug')
    {
        return $this->monolog->pushHandler(new SyslogHandler($name, LOG_USER, $level));
    }

    /**
     * Enregistrez un gestionnaire error_log.
     *
     * @param  string  $level
     * @param  int  $messageType
     * @return void
     */
    public function useErrorLog($level = 'debug', $messageType = ErrorLogHandler::OPERATING_SYSTEM)
    {
        $this->monolog->pushHandler(
            $handler = new ErrorLogHandler($messageType, $this->parseLevel($level))
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Enregistrez un nouveau gestionnaire de rappel lorsqu'un événement de journal est déclenché.
     *
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \RuntimeException
     */
    public function listen(Closure $callback)
    {
        if (! isset($this->events)) {
            throw new RuntimeException('Events dispatcher has not been set.');
        }

        $this->events->listen('framework.log', $callback);
    }

    /**
     * Déclenche un événement de journal.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array   $context
     * @return void
     */
    protected function fireLogEvent($level, $message, array $context = array())
    {
        if (isset($this->events)) {
            $this->events->dispatch('framework.log', compact('level', 'message', 'context'));
        }
    }

    /**
     * Formatez les paramètres de l'enregistreur.
     *
     * @param  mixed  $message
     * @return mixed
     */
    protected function formatMessage($message)
    {
        if (is_array($message)) {
            return var_export($message, true);
        } elseif ($message instanceof Jsonable) {
            return $message->toJson();
        } elseif ($message instanceof Arrayable) {
            return var_export($message->toArray(), true);
        }

        return $message;
    }

    /**
     * Analysez le niveau de chaîne en une constante Monolog.
     *
     * @param  string  $level
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    protected function parseLevel($level)
    {
        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new InvalidArgumentException('Invalid log level.');
    }

    /**
     * Obtenez l'instance Monolog sous-jacente.
     *
     * @return \Monolog\Logger
     */
    public function getMonolog()
    {
        return $this->monolog;
    }

    /**
     * Obtenez une instance de formateur Monolog par défaut.
     *
     * @return \Monolog\Formatter\LineFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter(null, null, true, true);
    }

    /**
     * Obtenez l’instance du répartiteur d’événements.
     *
     * @return \Two\Events\Contracts\DispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->events;
    }

    /**
     * Définissez l’instance du répartiteur d’événements.
     *
     * @param  \Two\Events\Contracts\DispatcherInterface  $events
     * @return void
     */
    public function setEventDispatcher(EventsDispatcher $events)
    {
        $this->events = $events;
    }
}

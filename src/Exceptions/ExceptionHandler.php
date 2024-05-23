<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions;

use Exception;
use ErrorException;

use Two\Application\Two;
use Two\Exceptions\Exception\FatalErrorException;

use Two\Exceptions\Exception\FatalThrowableError;
use Symfony\Component\Console\Output\ConsoleOutput;


class ExceptionHandler
{
    /**
     * La mise en œuvre du préparateur de réponses.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Indique si l'application est en mode débogage.
     *
     * @var bool
     */
    protected $debug;


    /**
     * Créez une nouvelle instance de gestionnaire d'erreurs.
     *
     * @param  \Two\Application\Two  $app
     * @param  bool  $debug
     * @return void
     */
    public function __construct(Two $app,  $debug = true)
    {
        $this->app = $app;

        $this->debug = $debug;
    }

    /**
     * Enregistrez les gestionnaires d’exceptions/d’erreurs pour l’application.
     *
     * @param  string  $environment
     * @return void
     */
    public function register($environment)
    {
        set_error_handler(array($this, 'handleError'));

        set_exception_handler(array($this, 'handleException'));

        register_shutdown_function(array($this, 'handleShutdown'));
    }

    /**
     * Gérer une erreur PHP pour l'application.
     *
     * @param  int     $level
     * @param  string  $message
     * @param  string  $file
     * @param  int     $line
     * @param  array   $context
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = array())
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Gérez une exception pour l’application.
     *
     * @param  \Exception  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleException($exception)
    {
        if (! $exception instanceof Exception) {
            $exception = new FatalThrowableError($exception);
        }

        $this->getExceptionHandler()->report($exception);

        if ($this->app->runningInConsole()) {
            $this->renderForConsole($exception);
        } else {
            $this->renderHttpResponse($exception);
        }
    }

    /**
     * Gérez l'événement d'arrêt de PHP.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalExceptionFromError($error, 0));
        }
    }

    /**
     * Déterminez si le type d’erreur est fatal.
     *
     * @param  int   $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE));
    }

    /**
     * Créez une nouvelle instance d'exception fatale à partir d'un tableau d'erreurs.
     *
     * @param  array  $error
     * @param  int|null  $traceOffset
     * @return \Symfony\Component\Debug\Exception\FatalErrorException
     */
    protected function fatalExceptionFromError(array $error, $traceOffset = null)
    {
        return new FatalErrorException(
            $error['message'], $error['type'], 0, $error['file'], $error['line'], $traceOffset
        );
    }

    /**
     * Afficher une exception à la console.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function renderForConsole($e, ConsoleOutput $output = null)
    {
        if (is_null($output)) {
            $output = new ConsoleOutput();
        }

        $this->getExceptionHandler()->renderForConsole($output, $e);
    }

    /**
     * Renvoyez une exception sous forme de réponse HTTP et envoyez-la.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function renderHttpResponse($e)
    {
        $this->getExceptionHandler()->render($this->app['request'], $e)->send();
    }

    /**
     * Définissez le niveau de débogage pour le gestionnaire.
     *
     * @param  bool  $debug
     * @return void
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * Obtenez une instance du gestionnaire d'exceptions.
     *
     * @return \Two\Exceptions\Contracts\HandlerInterface
     */
    protected function getExceptionHandler()
    {
        return $this->app->make('Two\Exceptions\Contracts\HandlerInterface');
    }
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Closure;
use Exception;
use Throwable;

use Two\Http\Request;

use Two\Application\Pipeline as BasePipeline;
use Two\Exceptions\Exception\FatalThrowableError;
use Two\Exceptions\Contracts\HandlerInterface as ExceptionHandler;


class Pipeline extends BasePipeline
{
    /**
     * Obtenez le dernier morceau de l'oignon de fermeture.
     *
     * @param  \Closure  $callback
     * @return \Closure
     */
    protected function prepareDestination(Closure $callback)
    {
        return function ($passable) use ($callback)
        {
            try {
                return call_user_func($callback, $passable);
            }
            catch (Exception $e) {
                return $this->handleException($passable, $e);
            }
            catch (Throwable $e) {
                return $this->handleException($passable, new FatalThrowableError($e));
            }
        };
    }

    /**
     * Obtenez une fermeture qui représente une tranche de l'oignon de l'application.
     *
     * @param  \Closure  $stack
     * @param  mixed  $pipe
     * @return \Closure
     */
    protected function createSlice($stack, $pipe)
    {
        return function ($passable) use ($stack, $pipe)
        {
            try {
                return $this->call($pipe, $passable, $stack);
            }
            catch (Exception $e) {
                return $this->handleException($passable, $e);
            }
            catch (Throwable $e) {
                return $this->handleException($passable, new FatalThrowableError($e));
            }
        };
    }

    /**
     * Gérez l’exception donnée.
     *
     * @param  mixed  $passable
     * @param  \Exception  $e
     * @return mixed
     *
     * @throws \Exception
     */
    protected function handleException($passable, Exception $e)
    {
        if ($this->shouldThrowExceptions() || (! $passable instanceof Request)) {
            throw $e;
        }

        $handler = $this->container->make(ExceptionHandler::class);

        $handler->report($e);

        $response = $handler->render($passable, $e);

        if (method_exists($response, 'withException')) {
            $response->withException($e);
        }

        return $response;
    }

    /**
     * Détermine si des exceptions doivent être levées pendant l'exécution.
     *
     * @return bool
     */
    protected function shouldThrowExceptions()
    {
        return ! $this->container->bound(ExceptionHandler::class);
    }
}

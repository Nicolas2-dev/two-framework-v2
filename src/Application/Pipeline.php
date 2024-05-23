<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application;

use Closure;
use RuntimeException;

use Two\Container\Container;


class Pipeline
{
    
    /**
     * La mise en œuvre du conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Le tableau des canaux de classe.
     *
     * @var array
     */
    protected $pipes = array();

    /**
     * La méthode à appeler sur chaque pipe.
     *
     * @var string
     */
    protected $method = 'handle';


    /**
     * Créez une nouvelle instance de classe.
     *
     * @param  \Two\Container\Container  $container
     * @param  mixed|array  $pipes
     * @param  string|null  $method
     * @return void
     */
    public function __construct(Container $container, $pipes = array(), $method = null)
    {
        $this->container = $container;

        $this->pipes = is_array($pipes) ? $pipes : array($pipes);

        if (! is_null($method)) {
            $this->method = $method;
        }
    }

    /**
     * Exécutez le pipeline avec un rappel de destination finale.
     *
     * @param  mixed  $passable
     * @param  \Closure  $callback
     * @return mixed
     */
    public function handle($passable, Closure $callback)
    {
        $pipes = array_reverse($this->pipes);

        $pipeline = array_reduce($pipes, function ($stack, $pipe)
        {
            return $this->createSlice($stack, $pipe);

        }, $this->prepareDestination($callback));

        return call_user_func($pipeline, $passable);
    }

    /**
     * Obtenez la tranche initiale pour commencer l'appel de pile.
     *
     * @param  \Closure  $callback
     * @return \Closure
     */
    protected function prepareDestination(Closure $callback)
    {
        return function ($passable) use ($callback)
        {
            return call_user_func($callback, $passable);
        };
    }

    /**
     * Obtenez une fermeture qui représente une tranche de l'oignon d'application.
     *
     * @return \Closure
     */
    protected function createSlice($stack, $pipe)
    {
        return function ($passable) use ($stack, $pipe)
        {
            return $this->call($pipe, $passable, $stack);
        };
    }

    /**
     * Appelez le tuyau Closure ou la méthode 'handle' dans son instance de classe.
     *
     * @param  mixed  $pipe
     * @param  mixed  $passable
     * @param  \Closure  $stack
     * @return \Closure
     * @throws \BadMethodCallException
     */
    protected function call($pipe, $passable, $stack)
    {
        if ($pipe instanceof Closure) {
            return call_user_func($pipe, $passable, $stack);
        }

        $parameters = array($passable, $stack);

        if (is_string($pipe)) {
            list ($name, $payload) = array_pad(explode(':', $pipe, 2), 2, null);

            if (! empty($payload)) {
                $parameters = array_merge($parameters, explode(',', $payload));
            }

            $pipe = $this->container->make($name);
        }

        // Les pipes doivent être soit une Closure, une chaîne ou une instance d'objet.
        else if (! is_object($pipe)) {
            throw new RuntimeException('An invalid pipe has been passed to the Pipeline.');
        }

        return call_user_func_array(array($pipe, $this->method), $parameters);
    }
}

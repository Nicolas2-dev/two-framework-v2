<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Engines;

use Closure;


class EngineResolver
{

    /**
     * La gamme de résolveurs Engine.
     *
     * @var array
     */
    protected $resolvers = array();

    /**
     * Les instances de moteur résolues.
     *
     * @var array
     */
    protected $resolved = array();

    /**
     * Enregistrez un nouveau résolveur de moteur.
     *
     * La chaîne Engine correspond généralement à une extension de fichier.
     *
     * @param  string   $engine
     * @param  Closure  $resolver
     * @return void
     */
    public function register($engine, Closure $resolver)
    {
        $this->resolvers[$engine] = $resolver;
    }

    /**
     * Résolvez une instance de moteur par son nom.
     *
     * @param  string  $engine
     * @return \Two\View\Contracts\Engines\EngineInterface
     */
    public function resolve($engine)
    {
        if (! isset($this->resolved[$engine])) {
            $resolver = $this->resolvers[$engine];

            $this->resolved[$engine] = call_user_func($resolver);
        }

        return $this->resolved[$engine];
    }

}

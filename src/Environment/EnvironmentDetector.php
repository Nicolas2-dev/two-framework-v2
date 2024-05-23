<?php

namespace Two\Environment;


use Closure;


class EnvironmentDetector
{

    /**
     * Détecter l'environnement actuel de l'application.
     *
     * @param  array|string  $environments
     * @param  array|null  $consoleArgs
     * @return string
     */
    public function detect($environments, $consoleArgs = null)
    {
        if ($consoleArgs) {
            return $this->detectConsoleEnvironment($environments, $consoleArgs);
        }

        return $this->detectWebEnvironment($environments);
    }

    /**
     * Définissez l'environnement d'application pour une requête Web.
     *
     * @param  array|string  $environments
     * @return string
     */
    protected function detectWebEnvironment($environments)
    {
        // Si l'environnement donné n'est qu'une fermeture, nous reporterons la vérification de 
        // l'environnement à la fermeture fournie par le développeur, ce qui lui permet d'échanger 
        // totalement la logique de détection de l'environnement Web avec son propre code de fermeture personnalisé.
        if ($environments instanceof Closure) {
            return call_user_func($environments);
        }

        foreach ($environments as $environment => $hosts) {
            // Pour déterminer l'environnement actuel, nous allons simplement parcourir les
            // environnements possibles et rechercher l'hôte qui correspond à l'hôte pour cette 
            // requête que nous traitons actuellement ici, puis renvoyer les noms de ces environnements.
            foreach ((array) $hosts as $host) {
                if ($this->isMachine($host)) return $environment;
            }
        }

        return 'production';
    }

    /**
     * Définissez l'environnement de l'application à partir des arguments de ligne de commande.
     *
     * @param  mixed   $environments
     * @param  array  $args
     * @return string
     */
    protected function detectConsoleEnvironment($environments, array $args)
    {
        // Nous allons d'abord vérifier si un argument d'environnement a été passé via les arguments 
        // de la console et s'il est automatiquement remplacé par l'environnement. Sinon, nous
        // vérifierons l'environnement comme une requête "web" comme une requête HTTP typique.
        if (! is_null($value = $this->getEnvironmentArgument($args))) {
            return head(array_slice(explode('=', $value), 1));
        }

        return $this->detectWebEnvironment($environments);
    }

    /**
     * Obtenez l'argument d'environnement à partir de la console.
     *
     * @param  array  $args
     * @return string|null
     */
    protected function getEnvironmentArgument(array $args)
    {
        return array_first($args, function($k, $v)
        {
            return starts_with($v, '--env');
        });
    }

    /**
     * Déterminez si le nom correspond au nom de la machine.
     *
     * @param  string  $name
     * @return bool
     */
    public function isMachine($name)
    {
        return str_is($name, gethostname());
    }

}
<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Traits;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionFunctionAbstract;

use Two\Support\Arr;
use Two\Container\Reflector;


trait RouteDependencyResolverTrait
{

    /**
     * Résolvez les dépendances de type de la méthode objet.
     *
     * @param  array  $parameters
     * @param  object  $instance
     * @param  string  $method
     * @return array
     */
    protected function resolveClassMethodDependencies(array $parameters, $instance, $method)
    {
        if (! method_exists($instance, $method)) {
            return $parameters;
        }

        return $this->resolveMethodDependencies(
            $parameters, new ReflectionMethod($instance, $method)
        );
    }

    /**
     * Résolvez les dépendances de type de la méthode donnée.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    public function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency($parameter, $parameters);

            if (! is_null($instance)) {
                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            }

            // Si le paramètre n'est pas défini et qu'il a une valeur par défaut.
            else if (! isset($values[$key - $instanceCount]) && $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return $parameters;
    }

    /**
     * Tentative de transformer le paramètre donné en instance de classe.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @param  array  $originalParameters
     * @return mixed
     */
    protected function transformDependency(ReflectionParameter $parameter, $parameters)
    {
        //$class = $parameter->getType();

        // Si le paramètre a une classe à indication de type, nous vérifierons s'il est déjà dans
        // la liste des paramètres. Si c'est le cas, nous l'ignorerons car il s'agit probablement d'un modèle.
        // liaison et nous ne voulons pas jouer avec ceux-là ; sinon, nous le résolvons ici.
        //if (! is_null($class) && ! $this->alreadyInParameters($className = $class, $parameters)) {
        //    return $parameter->isDefaultValueAvailable()
        //        ? $parameter->getDefaultValue()
        //        : $this->container->make($className);
        //}

        $className = Reflector::getParameterClassName($parameter);

        // Si le paramètre a une classe à indication de type, nous vérifierons s'il est déjà dans
        // la liste des paramètres. Si c'est le cas, nous l'ignorerons car il s'agit probablement d'un modèle.
        // liaison et nous ne voulons pas jouer avec ceux-là ; sinon, nous le résolvons ici.
        if ($className && ! $this->alreadyInParameters($className, $parameters)) {
            return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $this->container->make($className);
        }

    }

    /**
     * Détermine si un objet de la classe donnée se trouve dans une liste de paramètres.
     *
     * @param  string  $class
     * @param  array  $parameters
     * @return bool
     */
    protected function alreadyInParameters($class, array $parameters)
    {
        $result = Arr::first($parameters, function ($key, $value) use ($class)
        {
            return ($value instanceof $class);
        });

        return ! is_null($result);
    }

    /**
     * Insérez la valeur donnée dans la liste des paramètres.
     *
     * @param  array  $parameters
     * @param  string  $key
     * @param  mixed  $instance
     * @return void
     */
    protected function spliceIntoParameters(array &$parameters, $key, $instance)
    {
        array_splice($parameters, $key, 0, array($instance));
    }
}

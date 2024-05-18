<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */

namespace Two\Auth\Access;

use InvalidArgumentException;

use Two\Support\Str;
use Two\Container\Container;
use Two\Auth\Access\Response;
use Two\Auth\Exception\AuthorizedException;
use Two\Auth\Contracts\Access\GateInterface;
use Two\Auth\Contracts\UserInterface as User;
use Two\Auth\Traits\HandlesAuthorizationTrait;


class Gate implements GateInterface
{

    use HandlesAuthorizationTrait;

    /**
     * L'instance de conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Le résolveur utilisateur appelable.
     *
     * @var callable
     */
    protected $userResolver;

    /**
     * Toutes les capacités définies.
     *
     * @var array
     */
    protected $abilities = array();

    /**
     * Toutes les politiques définies.
     *
     * @var array
     */
    protected $policies = array();

    /**
     * Tous les inscrits avant les rappels.
     *
     * @var array
     */
    protected $beforeCallbacks = array();

    /**
     * Tous les inscrits après rappels.
     *
     * @var array
     */
    protected $afterCallbacks = array();


    /**
     * Créez une nouvelle instance de porte.
     *
     * @param  \Two\Container\Container  $container
     * @param  callable  $userResolver
     * @param  array  $abilities
     * @param  array  $policies
     * @param  array  $beforeCallbacks
     * @param  array  $afterCallbacks
     * @return void
     */
    public function __construct(Container $container,
                                callable $userResolver,
                                array $abilities = array(),
                                array $policies = array(),
                                array $beforeCallbacks = array(),
                                array $afterCallbacks = array())
    {
        $this->policies  = $policies;
        $this->container = $container;
        $this->abilities = $abilities;

        $this->userResolver = $userResolver;

        $this->afterCallbacks  = $afterCallbacks;
        $this->beforeCallbacks = $beforeCallbacks;
    }

    /**
     * Déterminez si une capacité donnée a été définie.
     *
     * @param  string  $ability
     * @return bool
     */
    public function has($ability)
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * Définir une nouvelle capacité.
     *
     * @param  string  $ability
     * @param  callable|string  $callback
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function define($ability, $callback)
    {
        if (is_callable($callback)) {
            $this->abilities[$ability] = $callback;
        } elseif (is_string($callback) && Str::contains($callback, '@')) {
            $this->abilities[$ability] = $this->buildAbilityCallback($callback);
        } else {
            throw new InvalidArgumentException("Callback must be a callable or a 'Class@method' string.");
        }

        return $this;
    }

    /**
     * Créez le rappel de capacité pour une chaîne de rappel.
     *
     * @param  string  $callback
     * @return \Closure
     */
    protected function buildAbilityCallback($callback)
    {
        return function () use ($callback)
        {
            list ($class, $method) = explode('@', $callback);

            $instance = $this->resolvePolicy($class);

            return call_user_func_array(array($instance, $method), func_get_args());
        };
    }

    /**
     * Définissez une classe de stratégie pour un type de classe donné.
     *
     * @param  string  $class
     * @param  string  $policy
     * @return $this
     */
    public function policy($class, $policy)
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    /**
     * Enregistrez un rappel à exécuter avant toutes les vérifications de Gate.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function before(callable $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Enregistrez un rappel à exécuter après toutes les vérifications de Gate.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function after(callable $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Déterminez si la capacité donnée doit être accordée à l’utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function allows($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        return $this->check($ability, $arguments);
    }

    /**
     * Déterminez si la capacité donnée doit être refusée pour l’utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function denies($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        return ! $this->check($ability, $arguments);
    }

    /**
     * Déterminez si la capacité donnée doit être accordée à l’utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function check($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        try {
            $result = $this->raw($ability, $arguments);
        }
        catch (AuthorizedException $e) {
            return false;
        }

        return (bool) $result;
    }

    /**
     * Déterminez si la capacité donnée doit être accordée à l’utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return \Two\Auth\Access\Response
     *
     * @throws \Two\Auth\Execption\AuthorizationException
     */
    public function authorize($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        $result = $this->raw($ability, $arguments);

        if ($result instanceof Response) {
            return $result;
        }

        return $result ? $this->allow() : $this->deny();
    }

    /**
     * Obtenez le résultat brut pour la capacité donnée pour l'utilisateur actuel.
     *
     * @param  string  $ability
     * @param  array  $arguments
     * @return mixed
     */
    protected function raw($ability, array $arguments)
    {
        if (is_null($user = $this->resolveUser())) {
            return false;
        }

        $result = $this->callBeforeCallbacks($user, $ability, $arguments);

        if (is_null($result)) {
            $result = $this->callAuthCallback($user, $ability, $arguments);
        }

        $this->callAfterCallbacks($user, $ability, $arguments, $result);

        return $result;
    }

    /**
     * Résolvez et appelez le rappel d’autorisation approprié.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return bool
     */
    protected function callAuthCallback(User $user, $ability, array $arguments)
    {
        $callback = $this->resolveAuthCallback($user, $ability, $arguments);

        return call_user_func_array($callback, array_merge(array($user), $arguments));
    }

    /**
     * Appelez tous les rappels avant et revenez si un résultat est donné.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return bool|null
     */
    protected function callBeforeCallbacks(User $user, $ability, array $arguments)
    {
        $arguments = array_merge(array($user, $ability), $arguments);

        foreach ($this->beforeCallbacks as $callback) {
            if (! is_null($result = call_user_func_array($callback, $arguments))) {
                return $result;
            }
        }
    }

    /**
     * Appelez tous les rappels après avec le résultat de la vérification.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @param  bool  $result
     * @return void
     */
    protected function callAfterCallbacks(User $user, $ability, array $arguments, $result)
    {
        $arguments = array_merge(array($user, $ability, $result), $arguments);

        foreach ($this->afterCallbacks as $callback) {
            call_user_func_array($callback, $arguments);
        }
    }

    /**
     * Résolvez l'appelable pour la capacité et les arguments donnés.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return callable
     */
    protected function resolveAuthCallback(User $user, $ability, array $arguments)
    {
        if ($this->firstArgumentCorrespondsToPolicy($arguments)) {
            return $this->resolvePolicyCallback($user, $ability, $arguments);
        }

        //
        else if (isset($this->abilities[$ability])) {
            return $this->abilities[$ability];
        }

        return function ()
        {
            return false;
        };
    }

    /**
     * Déterminez si le premier argument du tableau correspond à une stratégie.
     *
     * @param  array  $arguments
     * @return bool
     */
    protected function firstArgumentCorrespondsToPolicy(array $arguments)
    {
        if (! isset($arguments[0])) {
            return false;
        }

        $argument = $arguments[0];

        if (is_object($argument)) {
            $class = get_class($argument);

            return isset($this->policies[$class]);
        }

        return is_string($argument) && isset($this->policies[$argument]);
    }

    /**
     * Résolvez le rappel pour une vérification de stratégie.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return callable
     */
    protected function resolvePolicyCallback(User $user, $ability, array $arguments)
    {
        return function () use ($user, $ability, $arguments)
        {
            $class = head($arguments);

            if (method_exists($instance = $this->getPolicyFor($class), 'before')) {
                $parameters = array_merge(array($user, $ability), $arguments);

                if (! is_null($result = call_user_func_array(array($instance, 'before'), $parameters))) {
                    return $result;
                }
            }

            if (! method_exists($instance, $method = Str::camel($ability))) {
                return false;
            }

            return call_user_func_array(array($instance, $method), array_merge(array($user), $arguments));
        };
    }

    /**
     * Obtenez une instance de stratégie pour une classe donnée.
     *
     * @param  object|string  $class
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function getPolicyFor($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (! isset($this->policies[$class])) {
            throw new InvalidArgumentException("Policy not defined for [{$class}].");
        }

        return $this->resolvePolicy($this->policies[$class]);
    }

    /**
     * Créez une instance de classe de stratégie du type donné.
     *
     * @param  object|string  $class
     * @return mixed
     */
    public function resolvePolicy($class)
    {
        return $this->container->make($class);
    }

    /**
     * Obtenez une instance de garde pour l'utilisateur donné.
     *
     * @param  \Two\Auth\Contracts\UserInterface|mixed  $user
     * @return static
     */
    public function forUser($user)
    {
        $callback = function () use ($user)
        {
            return $user;
        };

        return new static(
            $this->container, $callback, $this->abilities,
            $this->policies, $this->beforeCallbacks, $this->afterCallbacks
        );
    }

    /**
     * Résolvez l'utilisateur à partir du résolveur d'utilisateur.
     *
     * @return mixed
     */
    protected function resolveUser()
    {
        return call_user_func($this->userResolver);
    }
}

<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Container;

use Closure;
use ArrayAccess;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use InvalidArgumentException;

use Two\Container\Exception\BindingResolutionException;


class Container implements ArrayAccess
{

    /**
     * Un tableau des types qui ont été résolus.
     *
     * @var array
     */
    protected $resolved = array();

    /**
     * Les fixations du conteneur.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * Les instances partagées du conteneur.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * Les alias de type enregistrés.
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * Tous les rappels de rebond enregistrés.
     *
     * @var array
     */
    protected $reboundCallbacks = array();

    /**
     * Tous les rappels de résolution enregistrés.
     *
     * @var array
     */
    protected $resolvingCallbacks = array();

    /**
     * Tous les rappels de résolution globale.
     *
     * @var array
     */
    protected $globalResolvingCallbacks = array();


    /**
     * Determine if a given string is resolvable.
     *
     * @param  string  $abstract
     * @return bool
     */
    protected function resolvable($abstract)
    {
        return $this->bound($abstract) || $this->isAlias($abstract);
    }

    /**
     * Détermine si le type abstrait donné a été lié.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Détermine si le type abstrait donné a été résolu.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function resolved($abstract)
    {
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Enregistrez une liaison avec le conteneur.
     *
     * @param  string|array  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        // Si les types donnés sont en fait un tableau, nous supposerons qu'un alias est défini et
        // saisirons ce "vrai" nom de classe abstraite et enregistrerons cet alias avec le 
        // conteneur afin qu'il puisse être utilisé comme raccourci pour celui-ci.
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        // Si aucun type concret n'a été donné, nous définirons simplement le type concret sur le 
        // type abstrait. Cela permettra au type concret d'être enregistré comme partagé sans être
        // obligé d'indiquer leurs classes dans les deux paramètres.
        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        // Si la fabrique n'est pas une Closure, cela signifie que c'est juste un nom de classe 
        // qui est lié dans ce conteneur au type abstrait et nous allons simplement l'envelopper
        // dans une Closure pour rendre les choses plus pratiques lors de l'extension.
        if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        // Si le type abstrait a déjà été résolu dans ce conteneur, nous déclencherons l'écouteur 
        // de rebond afin que tous les objets déjà résolus puissent voir leur copie de l'objet
        // mise à jour via les rappels de l'écouteur.
        if ($this->resolved($abstract))
        {
            $this->rebound($abstract);
        }
    }

    /**
     * Obtenez la fermeture à utiliser lors de la création d'un type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function($c, $parameters = array()) use ($abstract, $concrete)
        {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return $c->$method($concrete, $parameters);
        };
    }

    /**
     * Enregistrez une liaison si elle n'a pas déjà été enregistrée.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if (! $this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Inscrire une liaison partagée dans le conteneur.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Enveloppez une fermeture de telle sorte qu'elle soit partagée.
     *
     * @param  \Closure  $closure
     * @return \Closure
     */
    public function share(Closure $closure)
    {
        return function($container) use ($closure)
        {
            // Nous allons simplement déclarer une variable statique dans les Closures et si elle a
            // n'a pas été défini, nous exécuterons les fermetures données pour résoudre cette 
            // valeur et le renvoie à ces consommateurs de la méthode en tant qu'instance.
            static $object;

            if (is_null($object)) {
                $object = $closure($container);
            }

            return $object;
        };
    }

    /**
     * Liez une Closure partagée dans le conteneur.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return void
     */
    public function bindShared($abstract, Closure $closure)
    {
        $this->bind($abstract, $this->share($closure), true);
    }

    /**
     * "Étendre" un type abstrait dans le conteneur.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function extend($abstract, Closure $closure)
    {
        if (! isset($this->bindings[$abstract])) {
            throw new InvalidArgumentException("Type {$abstract} is not bound.");
        }

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $extender = $this->getExtender($abstract, $closure);

            $this->bind($abstract, $extender, $this->isShared($abstract));
        }
    }

    /**
     * Obtenir un extendeur Closure pour résoudre un type.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return \Closure
     */
    protected function getExtender($abstract, Closure $closure)
    {
        // Pour "étendre" une liaison, nous allons saisir l'ancienne Closure "résolveur" et la 
        // passer dans un nouveau. L'ancien résolveur sera appelé en premier et le résultat est
        // transmis au "nouveau" résolveur, avec cette instance de conteneur.
        $resolver = $this->bindings[$abstract]['concrete'];

        return function($container) use ($resolver, $closure)
        {
            return $closure($resolver($container), $container);
        };
    }

    /**
     * Enregistrez une instance existante comme partagée dans le conteneur.
     *
     * @param  string  $abstract
     * @param  mixed   $instance
     * @return void
     */
    public function instance($abstract, $instance)
    {
        // Tout d'abord, nous allons extraire l'alias de l'abstrait s'il s'agit d'un tableau afin 
        // d'utiliser le nom correct lors de la liaison du type. Si nous obtenons un alias, il sera
        // enregistré avec le conteneur afin que nous puissions le résoudre plus tard.
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        unset($this->aliases[$abstract]);

        // Nous vérifierons si ce type a déjà été lié, et si c'est le cas, nous déclencherons les 
        // rappels de rebond enregistrés avec le conteneur et il pourra être mis à jour avec les
        // classes consommatrices qui ont été résolues ici.
        $bound = $this->bound($abstract);

        $this->instances[$abstract] = $instance;

        if ($bound) {
            $this->rebound($abstract);
        }
    }

    /**
     * Associez un type à un nom plus court.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     */
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Extraire le type et l'alias d'une définition donnée.
     *
     * @param  array  $definition
     * @return array
     */
    protected function extractAlias(array $definition)
    {
        return array(key($definition), current($definition));
    }

    /**
     * Liez un nouveau rappel à l'événement de reliaison d'un résumé.
     *
     * @param  string    $abstract
     * @param  \Closure  $callback
     * @return mixed
     */
    public function rebinding($abstract, Closure $callback)
    {
        $this->reboundCallbacks[$abstract][] = $callback;

        if ($this->bound($abstract)) return $this->make($abstract);
    }

    /**
     * Actualiser une instance sur la cible et la méthode données.
     *
     * @param  string  $abstract
     * @param  mixed   $target
     * @param  string  $method
     * @return mixed
     */
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($abstract, function($app, $instance) use ($target, $method)
        {
            $target->{$method}($instance);
        });
    }

    /**
     * Lancez les rappels "rebound" pour le type abstrait donné.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    /**
     * Obtenez les rappels de rebond pour un type donné.
     *
     * @param  string  $abstract
     * @return array
     */
    protected function getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }

        return array();
    }

    /**
     * Enveloppez la fermeture donnée de sorte que ses dépendances soient injectées 
     * lors de l'exécution.
     *
     * @param  \Closure  $callback
     * @param  array  $parameters
     * @return \Closure
     */
    public function wrap(Closure $callback, array $parameters = array())
    {
        return function () use ($callback, $parameters) {
            return $this->call($callback, $parameters);
        };
    }

    /**
     * Appelez la Closure / class@method donnée et injectez ses dépendances.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    public function call($callback, array $parameters = array(), $defaultMethod = null)
    {
        if ($this->isCallableWithAtSign($callback) || $defaultMethod) {
            return $this->callClass($callback, $parameters, $defaultMethod);
        }

        $dependencies = $this->getMethodDependencies($callback, $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Déterminez si la chaîne donnée est dans la syntaxe Class@method.
     *
     * @param  mixed  $callback
     * @return bool
     */
    protected function isCallableWithAtSign($callback)
    {
        if (! is_string($callback)) {
            return false;
        }

        return strpos($callback, '@') !== false;
    }

    /**
     * Obtenir toutes les dépendances pour une méthode donnée.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @return array
     */
    protected function getMethodDependencies($callback, array $parameters = array())
    {
        $dependencies = array();

        //
        $reflector = $this->getCallReflector($callback);

        foreach ($reflector->getParameters() as $key => $parameter) {
            $this->addDependencyForCallParameter($parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Obtenez l'instance de réflexion appropriée pour le rappel donné.
     *
     * @param  callable|string  $callback
     * @return \ReflectionFunctionAbstract
     */
    protected function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        return new ReflectionFunction($callback);
    }

    /**
     * Obtenez la dépendance pour le paramètre d'appel donné.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @param  array  $dependencies
     * @return mixed
     */
    protected function addDependencyForCallParameter(ReflectionParameter $parameter, array &$parameters, &$dependencies)
    {
        $className = $parameter->getType()->getName();

        if (array_key_exists($className, $parameters)) {
            
            $dependencies[] = $parameters[$className];

            unset($parameters[$className]);
        } else if ($className) {
            
            $dependencies[] = $this->make($className);
        
        } else if ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

    /**
     * Appelez une référence de chaîne à une classe à l'aide de la syntaxe Class@method.
     *
     * @param  string  $target
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    protected function callClass($target, array $parameters = array(), $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // Si l'écouteur a un signe @, nous supposerons qu'il est utilisé pour délimiter
        // le nom de la classe à partir du nom de la méthode du handle. Cela permet aux 
        // gestionnaires pour exécuter plusieurs méthodes de gestionnaire dans une seule classe  
        // pour plus de commodité.
        $method = (count($segments) == 2) ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return $this->call([$this->make($segments[0]), $method], $parameters);
    }

    /**
     * Résolvez le type donné à partir du conteneur.
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);

        // Si une instance du type est actuellement gérée en tant que singleton, nous renvoyons
        // simplement une instance existante au lieu d'instancier de nouvelles instances afin que 
        // le développeur puisse continuer à utiliser la même instance d'objets à chaque fois.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // Nous sommes prêts à instancier une instance du type concret enregistrée pour la liaison.
        // Cela instancie les types et résout toutes ses dépendances "imbriquées" de manière 
        // récursive jusqu'à ce qu'elles soient toutes résolues.
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        // Si le type demandé est enregistré en tant que singleton, nous voudrons mettre en cache 
        // les instances en "mémoire" afin de pouvoir le renvoyer plus tard sans créer une toute
        // nouvelle instance d'un objet à chaque demande ultérieure.
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->fireResolvingCallbacks($abstract, $object);

        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * Obtenir le type concret d'un résumé donné.
     *
     * @param  string  $abstract
     * @return mixed   $concrete
     */
    protected function getConcrete($abstract)
    {
        // Si nous n'avons pas de résolveur enregistré ou concret pour le type, nous allons 
        // simplement suppose que chaque type est un nom concret et tentera de le résoudre tel quel
        // puisque le conteneur devrait être capable de résoudre automatiquement les bétons.
        if (! isset($this->bindings[$abstract])) {
            if ($this->missingLeadingSlash($abstract) && isset($this->bindings['\\'.$abstract])) {
                $abstract = '\\'.$abstract;
            }

            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Déterminez si le résumé donné a une barre oblique au début.
     *
     * @param  string  $abstract
     * @return bool
     */
    protected function missingLeadingSlash($abstract)
    {
        return is_string($abstract) && strpos($abstract, '\\') !== 0;
    }

    /**
     * Instancie une instance concrète du type donné.
     *
     * @param  string  $concrete
     * @param  array   $parameters
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    public function build($concrete, $parameters = array())
    {
        // Si le type concret est en fait une fermeture, nous allons simplement l'exécuter et
        // rend les résultats des fonctions, ce qui permet aux fonctions d'être
        // utilisé comme résolveurs pour une résolution plus fine de ces objets.
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        // Si le type n'est pas instanciable, le développeur tente de résoudre
        // un type abstrait tel qu'une interface de classe abstraite et il y a
        // aucune liaison n'est enregistrée pour les abstractions, nous devons donc renflouer.
        if (! $reflector->isInstantiable()) {
            $message = "Target [$concrete] is not instantiable.";

            throw new BindingResolutionException($message);
        }

        $constructor = $reflector->getConstructor();

        // S'il n'y a pas de constructeurs, cela signifie qu'il n'y a pas de dépendances alors
        // nous pouvons simplement résoudre les instances des objets tout de suite, sans
        // résolution de tout autre type ou dépendance à partir de ces conteneurs.
        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        // Une fois que nous avons tous les paramètres du constructeur, nous pouvons créer chacun 
        // des instances de dépendance, puis utilisez les instances de réflexion pour créer un
        // nouvelle instance de cette classe, en injectant les dépendances créées dans.
        $parameters = $this->keyParametersByArgument(
            $dependencies, $parameters
        );

        $instances = $this->getDependencies(
            $dependencies, $parameters
        );

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Résolvez toutes les dépendances de ReflectionParameters.
     *
     * @param  array  $parameters
     * @param  array  $primitives
     * @return array
     */
    protected function getDependencies($parameters, array $primitives = array())
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();

            // Si la classe est nulle, cela signifie que la dépendance est une chaîne ou une autre
            // type primitif que nous ne pouvons pas résoudre car ce n'est pas une classe et
            // nous allons juste bombarder avec une erreur puisque nous n'avons nulle part où
            // aller.
            if (array_key_exists($className = $parameter->getType()->getName(), $primitives)) {
                $dependencies[] = $primitives[$className];
            } else if (is_null($dependency)) {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }

        return (array) $dependencies;
    }

    /**
     * Résoudre une dépendance indicatrice non-classe.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected function resolveNonClass(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new BindingResolutionException($message);
    }

    /**
     * Résolvez une dépendance basée sur une classe à partir du conteneur.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $this->make($parameter->getType()->getName());
        }

        // Si nous ne pouvons pas résoudre l'instance de classe, nous vérifierons si la valeur
        // est facultatif, et si c'est le cas, nous renverrons la valeur du paramètre facultatif 
        // comme la valeur de la dépendance, de la même manière que nous le faisons avec les
        // scalaires.
        catch (BindingResolutionException $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * Si des paramètres supplémentaires sont passés par ID numérique, retapez-les par nom d'argument.
     *
     * @param  array  $dependencies
     * @param  array  $parameters
     * @return array
     */
    protected function keyParametersByArgument(array $dependencies, array $parameters)
    {
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                unset($parameters[$key]);

                $parameters[$dependencies[$key]->getName()] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Enregistrez un nouveau rappel de résolution.
     *
     * @param  string    $abstract
     * @param  \Closure|null  $callback
     * @return void
     */
    public function resolving($abstract, Closure $callback = null)
    {
        if (($callback === null) && $abstract instanceof Closure) {
            $this->resolvingCallback($abstract);
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Enregistrez un nouveau rappel après résolution pour tous les types.
     *
     * @param  string   $abstract
     * @param  \Closure|null $callback
     * @return void
     */
    public function afterResolving($abstract, Closure $callback = null)
    {
        if ($abstract instanceof Closure && $callback === null) {
            $this->afterResolvingCallback($abstract);
        } else {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Enregistrez un nouveau rappel de résolution pour tous les types.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function resolvingAny(Closure $callback)
    {
        $this->globalResolvingCallbacks[] = $callback;
    }

    /**
     * Enregistrez un nouveau rappel de résolution par type de son premier argument.
     *
     * @param  \Closure  $callback
     * @return void
     */
    protected function resolvingCallback(Closure $callback)
    {
        $abstract = $this->getFunctionHint($callback);

        if ($abstract) {
            $this->resolvingCallbacks[$abstract][] = $callback;
        } else {
            $this->globalResolvingCallbacks[] = $callback;
        }
    }

    /**
     * Enregistrez un nouveau après avoir résolu le rappel par type de son premier argument.
     *
     * @param  \Closure  $callback
     * @return void
     */
    protected function afterResolvingCallback(Closure $callback)
    {
        $abstract = $this->getFunctionHint($callback);

        if ($abstract) {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        } else {
            $this->globalAfterResolvingCallbacks[] = $callback;
        }
    }

    /**
     * Obtenez l'indice de type pour le premier argument de cette fermeture.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    protected function getFunctionHint(Closure $callback)
    {
        $function = new ReflectionFunction($callback);

        if ($function->getNumberOfParameters() == 0) {
            return;
        }

        $expected = $function->getParameters()[0];
        
        if (! $expected->getType()) {
            return;
        }

        return $expected->getType()->getName();
    }

    /**
     * Lancez tous les rappels de résolution.
     *
     * @param  string  $abstract
     * @param  mixed   $object
     * @return void
     */
    protected function fireResolvingCallbacks($abstract, $object)
    {
        if (isset($this->resolvingCallbacks[$abstract])) {
            $this->fireCallbackArray($object, $this->resolvingCallbacks[$abstract]);
        }

        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);
    }

    /**
     * Lancez un tableau de rappels avec un objet.
     *
     * @param  mixed  $object
     * @param  array  $callbacks
     */
    protected function fireCallbackArray($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $object, $this);
        }
    }

    /**
     * Détermine si un type donné est partagé.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function isShared($abstract)
    {
        if (isset($this->bindings[$abstract]['shared'])) {
            $shared = $this->bindings[$abstract]['shared'];
        } else {
            $shared = false;
        }

        return isset($this->instances[$abstract]) || $shared === true;
    }

    /**
     * Déterminez si le béton donné est constructible.
     *
     * @param  mixed   $concrete
     * @param  string  $abstract
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return ($concrete === $abstract) || ($concrete instanceof Closure);
    }

    /**
     * Obtenez l'alias d'un résumé si disponible.
     *
     * @param  string  $abstract
     * @return string
     */
    protected function getAlias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }

    /**
     * Obtenez les liaisons du conteneur.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Supprimez toutes les instances et alias obsolètes.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Supprimez une instance résolue du cache d'instance.
     *
     * @param  string  $abstract
     * @return void
     */
    public function forgetInstance($abstract)
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Effacez toutes les instances du conteneur.
     *
     * @return void
     */
    public function forgetInstances()
    {
        $this->instances = array();
    }

    /**
     * Détermine si un décalage donné existe.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return isset($this->bindings[$key]);
    }

    /**
     * Obtenez la valeur à un décalage donné.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }

    /**
     * Définissez la valeur à un décalage donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        // Si la valeur n'est pas une Closure, nous en ferons une. Cela donne simplement
        // plus de fonctionnalités de remplacement "drop-in" pour le Pimple que cela
        // Les fonctions les plus simples du conteneur sont modélisées de base et construites après.
        if (! $value instanceof Closure) {
            $value = function() use ($value)
            {
                return $value;
            };
        }

        $this->bind($key, $value);
    }

    /**
     * Annuler la valeur à un décalage donné.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void 
    {
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }

    /**
     * Accédez dynamiquement aux services de conteneur.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Services de conteneur définis dynamiquement.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }

}

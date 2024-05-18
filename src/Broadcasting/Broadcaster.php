<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionFunctionAbstract;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Http\Request;
use Two\Database\ORM\Model;
use Two\Container\Container;
use Two\Broadcasting\Channel;
use Two\Broadcasting\Auth\Guest as GuestUser;
use Two\Broadcasting\Contracts\BroadcasterInterface;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


abstract class Broadcaster implements BroadcasterInterface
{
    /**
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Les authentificateurs de chaîne enregistrés.
     *
     * @var array
     */
    protected $channels = array();

    /**
     * L'instance d'invité mise en cache, le cas échéant.
     *
     * @var \Two\Broadcasting\Auth\Guest|null
     */
    protected $authGuest;


    /**
     * Créez une nouvelle instance de diffuseur.
     *
     * @param  \Two\Container\Container $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Enregistrez un authentificateur de chaîne.
     *
     * @param  string  $channel
     * @param  callable  $callback
     * @return $this
     */
    public function channel($channel, callable $callback)
    {
        $this->channels[$channel] = $callback;

        return $this;
    }

    /**
     * Authentifiez la demande entrante pour un canal donné.
     *
     * @param  \Two\Http\Request  $request
     * @return mixed
     */
    public function authenticate(Request $request)
    {
        $channelName = $request->input('channel_name');

        $channel = preg_replace('/^(private|presence)\-/', '', $channelName, 1, $count);

        if (($count == 1) && is_null($user = $request->user())) {
            
            // Pour les canaux privés et de présence, la Diffusion a besoin d'une instance Utilisateur valide,
            // mais il n'est pas disponible pour les utilisateurs non authentifiés (invités) dans Auth System.
            // Pour les invités, nous utiliserons une instance GuestUser en cache, avec une chaîne aléatoire comme ID.
            $request->setUserResolver(function ()
            {
                return $this->resolveGuestUser();
            });
        }

        return $this->verifyUserCanAccessChannel($request, $channel);
    }

    /**
     * Résolvez l’instance d’utilisateur invité avec la mise en cache locale.
     *
     * @return \Two\Broadcasting\Auth\Guest
     */
    protected function resolveGuestUser()
    {
        if (isset($this->authGuest)) {
            return $this->authGuest;
        }

        $session = $this->container['session'];

        if (empty($id = $session->get('broadcasting.guest'))) {
            $id = dechex(time()) . Str::random(16);

            $session->set('broadcasting.guest', $id);
        }

        return $this->authGuest = new GuestUser($id);
    }

    /**
     * Authentifiez la demande entrante pour un canal donné.
     *
     * @param  \Two\Http\Request  $request
     * @param  string  $channel
     * @return mixed
     */
    protected function verifyUserCanAccessChannel(Request $request, $channel)
    {
        foreach ($this->channels as $pattern => $callback) {
            $regexp = preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern);

            if (preg_match('/^' .$regexp .'$/', $channel, $matches) !== 1) {
                continue;
            }

            $parameters = array_filter($matches, function ($value, $key)
            {
                return is_string($key) && ! empty($value);

            }, ARRAY_FILTER_USE_BOTH);

            // Le premier paramètre est toujours l’instance d’utilisateur authentifié.
            array_unshift($parameters, $request->user());

            if ($result = $this->callChannelCallback($callback, $parameters)) {
                return $this->validAuthenticationResponse($request, $result);
            }
        }

        throw new AccessDeniedHttpException;
    }

    /**
     * Appelez un rappel de canal avec les dépendances.
     *
     * @param  mixed  $callback
     * @param  array  $parameters
     * @return mixed
     */
    protected function callChannelCallback($callback, $parameters)
    {
        if (is_string($callback)) {
            list ($className, $method) = Str::parseCallback($callback, 'join');

            $callback = array(
                $instance = $this->container->make($className), $method
            );

            $reflector = new ReflectionMethod($instance, $method);
        } else {
            $reflector = new ReflectionFunction($callback);
        }

        return call_user_func_array(
            $callback, $this->resolveCallDependencies($parameters, $reflector)
        );
    }

    /**
     * Résolvez les dépendances de type de la méthode donnée.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    public function resolveCallDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        foreach ($reflector->getParameters() as $key => $parameter) {
            if ($key === 0) {
                // Le premier paramètre est toujours l’instance d’utilisateur authentifié.
                continue;
            }

            $instance = $this->transformDependency($parameter, $parameters);

            if (! is_null($instance)) {
                array_splice($parameters, $key, 0, array($instance));
            }
        }

        return $parameters;
    }

    /**
     * Tentative de transformer le paramètre donné en instance de classe.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  string  $name
     * @param  array  $parameters
     * @return mixed
     */
    protected function transformDependency(ReflectionParameter $parameter, $parameters)
    {
        if (is_null($class = $parameter->getType())) {
            return;
        }

        // Le paramètre fait référence à une classe.
        else if (! $class->isSubclassOf(Model::class)) {
            return $this->container->make($class->getName());
        }

        $identifier = Arr::first($parameters, function ($parameterKey) use ($parameter)
        {
            return $parameterKey === $parameter->name;
        });

        if (! is_null($identifier)) {
            $instance = $class->newInstance();

            return call_user_func(array($instance, 'find'), $identifier);
        }
    }

    /**
     * Formatez le tableau de canaux en un tableau de chaînes.
     *
     * @param  array  $channels
     * @return array
     */
    protected function formatChannels(array $channels)
    {
        return array_map(function ($channel)
        {
            if ($channel instanceof Channel) {
                return $channel->getName();
            }

            return $channel;

        }, $channels);
    }
}

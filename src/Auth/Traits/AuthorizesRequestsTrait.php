<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Traits;

use Two\Support\Facades\App;
use Two\Auth\Contracts\GateInterface;
use Two\Auth\Exception\UnAuthorizedException;

use Symfony\Component\HttpKernel\Exception\HttpException;


trait AuthorizesRequestsTrait
{
    /**
     * Autoriser une action donnée contre un ensemble d’arguments.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Two\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorize($ability, $arguments = array())
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        $gate = App::make('Two\Auth\Contracts\GateInterface');

        return $this->authorizeAtGate($gate, $ability, $arguments);
    }

    /**
     * Autoriser une action donnée pour un utilisateur.
     *
     * @param  \Two\Auth\Contracts\UserInterface|mixed  $user
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Two\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeForUser($user, $ability, $arguments = array())
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        $gate = App::make('Two\Auth\Contracts\GateInterface')->forUser($user);

        return $this->authorizeAtGate($gate, $ability, $arguments);
    }

    /**
     * Autorisez la demande à la porte indiquée.
     *
     * @param  \Two\Auth\Contracts\Access\GateInterface  $gate
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Two\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeAtGate(GateInterface $gate, $ability, $arguments)
    {
        try {
            return $gate->authorize($ability, $arguments);
        }
        catch (UnAuthorizedException $e) {
            $exception = $this->createGateUnauthorizedException($ability, $arguments, $e->getMessage(), $e);

            throw $exception;
        }
    }

    /**
     * Devine le nom de la capacité si elle n'a pas été fournie.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return array
     */
    protected function parseAbilityAndArguments($ability, $arguments)
    {
        if (is_string($ability) && (strpos($ability, '\\') === false)) {
            return array($ability, $arguments);
        }

        list(,, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return array($this->normalizeGuessedAbilityName($caller['function']), $ability);
    }

    /**
     * Normalisez le nom de la capacité qui a été deviné à partir du nom de la méthode.
     *
     * @param  string  $ability
     * @return string
     */
    protected function normalizeGuessedAbilityName($ability)
    {
        $map = $this->resourceAbilityMap();

        return isset($map[$ability]) ? $map[$ability] : $ability;
    }

    /**
     * Obtenez la carte des méthodes de ressources vers les noms de capacités.
     *
     * @return array
     */
    protected function resourceAbilityMap()
    {
        return array(
            'index'   => 'lists',
            'show'    => 'view',
            'create'  => 'create',
            'store'   => 'create',
            'edit'    => 'update',
            'update'  => 'update',
            'destroy' => 'delete',
        );
    }

    /**
     * Lancez une exception non autorisée basée sur les résultats de la porte.
     *
     * @param  string  $ability
     * @param  mixed|array  $arguments
     * @param  string  $message
     * @param  \Exception  $previousException
     * @return \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function createGateUnauthorizedException($ability, $arguments, $message = null, $previousException = null)
    {
        $message = $message ?: __d('Two', 'This action is unauthorized.');

        return new HttpException(403, $message, $previousException);
    }

}

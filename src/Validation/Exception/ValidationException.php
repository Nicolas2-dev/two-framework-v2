<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation\Exception;

use RuntimeException;

use Two\Application\Contracts\MessageProviderInterface;


class ValidationException extends RuntimeException
{
    /**
     * Implémentation du fournisseur de messages.
     *
     * @var \Two\Application\Contracts\MessageProviderInterface
     */
    protected $provider;

    /**
     * Créez une nouvelle instance d'exception de validation.
     *
     * @param  \Two\Application\Contracts\MessageProviderInterface  $provider
     * @return void
     */
    public function __construct(MessageProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Obtenez le fournisseur de messages d’erreur de validation.
     *
     * @return \Two\Support\MessageBag
     */
    public function errors()
    {
        return $this->provider->getMessageBag();
    }

    /**
     * Obtenez le fournisseur de messages d’erreur de validation.
     *
     * @return \Two\Application\Contracts\MessageProviderInterface
     */
    public function getMessageProvider()
    {
        return $this->provider;
    }
}

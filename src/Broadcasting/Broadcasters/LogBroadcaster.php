<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Broadcasters;

use Two\Broadcasting\Broadcaster;
use Two\Container\Container;
use Two\Http\Request;

use Psr\Log\LoggerInterface;


class LogBroadcaster extends Broadcaster
{
    /**
     * L'implémentation de l'enregistreur.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;


    /**
     * Créez une nouvelle instance de diffuseur.
     *
     * @param  \Psr\Log\LoggerInterface  $logger
     * @return void
     */
    public function __construct(Container $container, LoggerInterface $logger)
    {
        parent::__construct($container);

        //
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function validAuthenticationResponse(Request $request, $result)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = array())
    {
        $channels = implode(', ', $this->formatChannels($channels));

        $message = 'Broadcasting [' .$event .'] on channels [' .$channels .'] with payload:' .PHP_EOL .json_encode($payload, JSON_PRETTY_PRINT);

        $this->logger->info($message);
    }
}

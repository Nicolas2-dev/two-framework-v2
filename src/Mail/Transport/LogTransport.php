<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Mail\Transport;

use Swift_Transport;
use Swift_Mime_Message;
use Swift_Mime_MimeEntity;
use Swift_Events_EventListener;

use Psr\Log\LoggerInterface;


class LogTransport implements Swift_Transport
{
    /**
     * L'instance de l'enregistreur.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Créez une nouvelle instance de transport de journaux.
     *
     * @param  \Psr\Log\LoggerInterface  $logger
     * @return void
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->logger->debug($this->getMimeEntityString($message));
    }

    /**
     * Obtenez une chaîne journalisable à partir d'une entité Swiftmailer.
     *
     * @param  \Swift_Mime_MimeEntity $entity
     * @return string
     */
    protected function getMimeEntityString(Swift_Mime_MimeEntity $entity)
    {
        $string = (string) $entity->getHeaders() .PHP_EOL .$entity->getBody();

        foreach ($entity->getChildren() as $children) {
            $string .= PHP_EOL .PHP_EOL .$this->getMimeEntityString($children);
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        //
    }

}

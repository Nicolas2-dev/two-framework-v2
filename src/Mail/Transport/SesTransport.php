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
use Swift_Events_EventListener;

use Aws\Ses\SesClient;


class SesTransport implements Swift_Transport
{

    /**
     * L'instance Amazon SES.
     *
     * @var \Aws\Ses\SesClient
     */
    protected $ses;

    /**
     * Créez une nouvelle instance de transport SES.
     *
     * @param  \Aws\Ses\SesClient  $ses
     * @return void
     */
    public function __construct(SesClient $ses)
    {
        $this->ses = $ses;
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
        return $this->ses->sendRawEmail([
            'Source' => $message->getSender(),
            'Destinations' => $this->getTo($message),
            'RawMessage' => [
                'Data' => base64_encode((string) $message),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        //
    }

    /**
     * Obtenez le champ de charge utile « à » pour la requête API.
     *
     * @param  \Swift_Mime_Message  $message
     * @return array
     */
    protected function getTo(Swift_Mime_Message $message)
    {
        $destinations = [];

        $contacts = array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        );

        foreach ($contacts as $address => $display)
        {
            $destinations[] = $address;
        }

        return $destinations;
    }

}

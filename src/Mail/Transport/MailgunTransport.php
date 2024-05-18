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

use GuzzleHttp\Client;
use GuzzleHttp\Post\PostFile;


class MailgunTransport implements Swift_Transport
{
    /**
     * La clé API Mailgun.
     *
     * @var string
     */
    protected $key;

    /**
     * Le domaine Mailgun.
     *
     * @var string
     */
    protected $domain;

    /**
     * Le point final de l'API Mailgun.
     *
     * @var string
     */
    protected $url;

    /**
     * Créez une nouvelle instance de transport Mailgun.
     *
     * @param  string  $key
     * @param  string  $domain
     * @return void
     */
    public function __construct($key, $domain)
    {
        $this->key = $key;

        $this->setDomain($domain);
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
        $client = $this->getHttpClient();

        $client->post($this->url, ['auth' => ['api', $this->key],
            'body' => [
                'to' => $this->getTo($message),
                'message' => new PostFile('message', (string) $message),
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
        $formatted = [];

        $contacts = array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        );

        foreach ($contacts as $address => $display)
        {
            $formatted[] = $display ? $display." <$address>" : $address;
        }

        return implode(',', $formatted);
    }

    /**
     * Obtenez une nouvelle instance de client HTTP.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        return new Client;
    }

    /**
     * Obtenez la clé API utilisée par le transport.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Définissez la clé API utilisée par le transport.
     *
     * @param  string  $key
     * @return void
     */
    public function setKey($key)
    {
        return $this->key = $key;
    }

    /**
     * Obtenez le domaine utilisé par le transport.
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Définissez le domaine utilisé par le transport.
     *
     * @param  string  $domain
     * @return void
     */
    public function setDomain($domain)
    {
        $this->url = 'https://api.mailgun.net/v2/'.$domain.'/messages.mime';

        return $this->domain = $domain;
    }

}

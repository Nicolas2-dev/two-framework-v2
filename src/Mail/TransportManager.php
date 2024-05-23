<?php 
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Mail;

use Two\Mail\Transport\LogTransport;
use Two\Mail\Transport\MailgunTransport;
use Two\Mail\Transport\MandrillTransport;
use Two\Mail\Transport\SesTransport;
use Two\Application\Manager;

use Aws\Ses\SesClient;

use Swift_SendmailTransport as SendmailTransport;
use Swift_SmtpTransport as SmtpTransport;
use Swift_MailTransport as MailTransport;


class TransportManager extends Manager
{

    /**
     * Créez une instance du pilote SMTP Swift Transport.
     *
     * @return \Swift_SmtpTransport
     */
    protected function createSmtpDriver()
    {
        $config = $this->app['config']['mail'];

        // L'instance de transport Swift SMTP nous permettra d'utiliser n'importe quel backend SMTP
        // pour distribuer du courrier tel que Sendgrid, Amazon SES ou un serveur personnalisé
        // un développeur dispose de. Nous allons simplement transmettre cet hôte configuré.
        $transport = SmtpTransport::newInstance(
            $config['host'], $config['port']
        );

        if (isset($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        // Une fois que nous aurons le transport, nous vérifierons la présence d'un nom d'utilisateur
        // et mot de passe. Si nous l'avons, nous définirons les informations d'identification sur le Swift
        // instance de transporteur afin que nous authentifiions correctement la livraison.
        if (isset($config['username'])) {
            $transport->setUsername($config['username']);

            $transport->setPassword($config['password']);
        }

        return $transport;
    }

    /**
     * Créez une instance du pilote Sendmail Swift Transport.
     *
     * @return \Swift_SendmailTransport
     */
    protected function createSendmailDriver()
    {
        $command = $this->app['config']['mail']['sendmail'];

        return SendmailTransport::newInstance($command);
    }

    /**
     * Créez une instance du pilote Amazon SES Swift Transport.
     *
     * @return \Swift_SendmailTransport
     */
    protected function createSesDriver()
    {
        $sesClient = SesClient::factory($this->app['config']->get('services.ses', []));

        return new SesTransport($sesClient);
    }

    /**
     * Créez une instance du pilote Mail Swift Transport.
     *
     * @return \Swift_MailTransport
     */
    protected function createMailDriver()
    {
        return MailTransport::newInstance();
    }

    /**
     * Créez une instance du pilote Mailgun Swift Transport.
     *
     * @return \Two\Mail\Transport\MailgunTransport
     */
    protected function createMailgunDriver()
    {
        $config = $this->app['config']->get('services.mailgun', array());

        return new MailgunTransport($config['secret'], $config['domain']);
    }

    /**
     * Créez une instance du pilote Mandrill Swift Transport.
     *
     * @return \Two\Mail\Transport\MandrillTransport
     */
    protected function createMandrillDriver()
    {
        $config = $this->app['config']->get('services.mandrill', array());

        return new MandrillTransport($config['secret']);
    }

    /**
     * Créez une instance du pilote Log Swift Transport.
     *
     * @return \Two\Mail\Transport\LogTransport
     */
    protected function createLogDriver()
    {
        return new LogTransport($this->app->make('Psr\Log\LoggerInterface'));
    }

    /**
     * Obtenez le nom du pilote de cache par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['mail.driver'];
    }

    /**
     * Définissez le nom du pilote de cache par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['mail.driver'] = $name;
    }

}

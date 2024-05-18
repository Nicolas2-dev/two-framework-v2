<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Mail;

use Swift_Image;
use Swift_Attachment;


class Message
{
    /**
     * L'instance de message Swift.
     *
     * @var \Swift_Message
     */
    protected $swift;

    /**
     * Créez une nouvelle instance de message.
     *
     * @param  \Swift_Message  $swift
     * @return void
     */
    public function __construct($swift)
    {
        $this->swift = $swift;
    }

    /**
     * Ajoutez une adresse « de » au message.
     *
     * @param  string  $address
     * @param  string  $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        $this->swift->setFrom($address, $name);

        return $this;
    }

    /**
     * Définissez "l'expéditeur" du message.
     *
     * @param  string  $address
     * @param  string  $name
     * @return $this
     */
    public function sender($address, $name = null)
    {
        $this->swift->setSender($address, $name);

        return $this;
    }

    /**
     * Définissez le "chemin de retour" du message.
     *
     * @param  string  $address
     * @return $this
     */
    public function returnPath($address)
    {
        $this->swift->setReturnPath($address);

        return $this;
    }

    /**
     * Ajoutez un destinataire au message.
     *
     * @param  string|array  $address
     * @param  string  $name
     * @return $this
     */
    public function to($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'To');
    }

    /**
     * Ajoutez une copie carbone au message.
     *
     * @param  string  $address
     * @param  string  $name
     * @return $this
     */
    public function cc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * Ajoutez une copie carbone invisible au message.
     *
     * @param  string  $address
     * @param  string  $name
     * @return $this
     */
    public function bcc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * Ajoutez une adresse de réponse au message.
     *
     * @param  string  $address
     * @param  string  $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }

    /**
     * Ajoutez un destinataire au message.
     *
     * @param  string|array  $address
     * @param  string  $name
     * @param  string  $type
     * @return $this
     */
    protected function addAddresses($address, $name, $type)
    {
        if (is_array($address)) {
            $this->swift->{"set{$type}"}($address, $name);
        } else {
            $this->swift->{"add{$type}"}($address, $name);
        }

        return $this;
    }

    /**
     * Définissez le sujet du message.
     *
     * @param  string  $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->swift->setSubject($subject);

        return $this;
    }

    /**
     * Définissez le niveau de priorité des messages.
     *
     * @param  int  $level
     * @return $this
     */
    public function priority($level)
    {
        $this->swift->setPriority($level);

        return $this;
    }

    /**
     * Joindre un fichier au message.
     *
     * @param  string  $file
     * @param  array   $options
     * @return $this
     */
    public function attach($file, array $options = array())
    {
        $attachment = $this->createAttachmentFromPath($file);

        return $this->prepAttachment($attachment, $options);
    }

    /**
     * Créez une instance de pièce jointe Swift.
     *
     * @param  string  $file
     * @return \Swift_Attachment
     */
    protected function createAttachmentFromPath($file)
    {
        return Swift_Attachment::fromPath($file);
    }

    /**
     * Joignez des données en mémoire en pièce jointe.
     *
     * @param  string  $data
     * @param  string  $name
     * @param  array   $options
     * @return $this
     */
    public function attachData($data, $name, array $options = array())
    {
        $attachment = $this->createAttachmentFromData($data, $name);

        return $this->prepAttachment($attachment, $options);
    }

    /**
     * Créez une instance Swift Attachment à partir de données.
     *
     * @param  string  $data
     * @param  string  $name
     * @return \Swift_Attachment
     */
    protected function createAttachmentFromData($data, $name)
    {
        return Swift_Attachment::newInstance($data, $name);
    }

    /**
     * Intégrez un fichier dans le message et obtenez le CID.
     *
     * @param  string  $file
     * @return string
     */
    public function embed($file)
    {
        return $this->swift->embed(Swift_Image::fromPath($file));
    }

    /**
     * Intégrez les données en mémoire dans le message et obtenez le CID.
     *
     * @param  string  $data
     * @param  string  $name
     * @param  string  $contentType
     * @return string
     */
    public function embedData($data, $name, $contentType = null)
    {
        $image = Swift_Image::newInstance($data, $name, $contentType);

        return $this->swift->embed($image);
    }

    /**
     * Préparez et joignez la pièce jointe donnée.
     *
     * @param  \Swift_Attachment  $attachment
     * @param  array  $options
     * @return $this
     */
    protected function prepAttachment($attachment, $options = array())
    {
        // Nous allons d'abord vérifier la présence d'un type MIME sur le message, ce qui indique au
        // client de messagerie sur quel type de pièce jointe se trouve le fichier afin qu'il puisse être
        // téléchargé correctement par l'utilisateur. L'option MIME n'est pas obligatoire.
        if (isset($options['mime'])) {
            $attachment->setContentType($options['mime']);
        }

        // Si un nom alternatif a été proposé en option, nous le définirons sur ce
        // pièce jointe pour qu'elle soit téléchargée avec les noms souhaités depuis
        // le développeur, sinon les noms de fichiers par défaut seront attribués.
        if (isset($options['as'])) {
            $attachment->setFilename($options['as']);
        }

        $this->swift->attach($attachment);

        return $this;
    }

    /**
     * Obtenez l'instance Swift Message sous-jacente.
     *
     * @return \Swift_Message
     */
    public function getSwiftMessage()
    {
        return $this->swift;
    }

    /**
     * Transmettez dynamiquement les méthodes manquantes à l’instance Swift.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = array($this->swift, $method);

        return call_user_func_array($callable, $parameters);
    }

}

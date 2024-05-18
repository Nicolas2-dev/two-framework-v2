<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Messages;

use Two\Notifications\Messages\SimpleMessage;


class MailMessage extends SimpleMessage
{
    /**
     * La vue du message.
     *
     * @var array
     */
    public $view = array(
        'Emails/Notifications/Default',
        'Emails/Notifications/Plain',
    );

    /**
     * Afficher les données du message.
     *
     * @var array
     */
    public $viewData = array();

    /**
     * Les informations « de » pour le message.
     *
     * @var array
     */
    public $from = array();

    /**
     * Informations sur le destinataire du message.
     *
     * @var array
     */
    public $to = array();

    /**
     * Les destinataires « cc » du message.
     *
     * @var array
     */
    public $cc = array();

    /**
     * Les informations « Répondre à » pour le message.
     *
     * @var array
     */
    public $replyTo = array();

    /**
     * Les pièces jointes du message.
     *
     * @var array
     */
    public $attachments = array();

    /**
     * Les pièces jointes brutes du message.
     *
     * @var array
     */
    public $rawAttachments = array();

    /**
     * Niveau de priorité du message.
     *
     * @var int
     */
    public $priority;


    /**
     * Définissez l'affichage du message électronique.
     *
     * @param  string  $view
     * @param  array  $data
     * @return $this
     */
    public function view($view, array $data = array())
    {
        $this->view = $view;

        $this->viewData = $data;

        return $this;
    }

    /**
     * Définissez l'adresse d'expéditeur du message électronique.
     *
     * @param  string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        $this->from = array($address, $name);

        return $this;
    }

    /**
     * Définissez l'adresse du destinataire du message électronique.
     *
     * @param  string|array  $address
     * @return $this
     */
    public function to($address)
    {
        $this->to = $address;

        return $this;
    }

    /**
     * Définissez les destinataires du message.
     *
     * @param  string|array  $address
     * @return $this
     */
    public function cc($address)
    {
        $this->cc = $address;

        return $this;
    }

    /**
     * Définissez l'adresse « répondre à » du message.
     *
     * @param  array|string $address
     * @param null $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        $this->replyTo = array($address, $name);

        return $this;
    }

    /**
     * Joindre un fichier au message.
     *
     * @param  string  $file
     * @param  array  $options
     * @return $this
     */
    public function attach($file, array $options = array())
    {
        $this->attachments[] = compact('file', 'options');

        return $this;
    }

    /**
     * Joignez des données en mémoire en pièce jointe.
     *
     * @param  string  $data
     * @param  string  $name
     * @param  array  $options
     * @return $this
     */
    public function attachData($data, $name, array $options = array())
    {
        $this->rawAttachments[] = compact('data', 'name', 'options');

        return $this;
    }

    /**
     * Définissez la priorité de ce message.
     *
     * La valeur est un nombre entier où 1 est la priorité la plus élevée et 5 la plus basse.
     *
     * @param  int  $level
     * @return $this
     */
    public function priority($level)
    {
        $this->priority = $level;

        return $this;
    }

    /**
     * Obtenez le tableau de données pour le message électronique.
     *
     * @return array
     */
    public function data()
    {
        return array_merge($this->toArray(), $this->viewData);
    }
}

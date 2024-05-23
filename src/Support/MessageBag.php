<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support;

use Two\Application\Contracts\ArrayableInterface;


class MessageBag Implements ArrayableInterface
{
    /**
     * Tous les messages enregistrés.
     *
     * @var array
     */
    protected $messages = array();

    /**
     * Format par défaut pour la sortie des messages.
     *
     * @var string
     */
    protected $format = ':message';

    /**
     * Créez une nouvelle instance de Message Bag.
     *
     * @param  array  $messages
     * @return void
     */
    public function __construct(array $messages = array())
    {
        foreach ($messages as $key => $value) {
            $this->messages[$key] = (array) $value;
        }
    }

    /**
     * Ajoutez un message au sac.
     *
     * @param  string  $key
     * @param  string  $message
     * @return \Two\Support\MessageBag
     */
    public function add($key, $message)
    {
        if ($this->isUnique($key, $message)) {
            $this->messages[$key][] = $message;
        }

        return $this;
    }

    /**
     * Fusionnez un nouveau tableau de messages dans le sac.
     *
     * @param  array  $messages
     * @return \Two\Support\MessageBag
     */
    public function merge($messages)
    {
        $this->messages = array_merge_recursive($this->messages, $messages);

        return $this;
    }

    /**
     * Déterminez si une combinaison de clé et de message existe déjà.
     *
     * @param  string  $key
     * @param  string  $message
     * @return bool
     */
    protected function isUnique($key, $message)
    {
        $messages = (array) $this->messages;

        return ! isset($messages[$key]) || ! in_array($message, $messages[$key]);
    }

    /**
     * Déterminez si des messages existent pour une clé donnée.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key = null)
    {
        return $this->first($key) !== '';
    }

    /**
     * Recevez le premier message du sac pour une clé donnée.
     *
     * @param  string  $key
     * @param  string  $format
     * @return string
     */
    public function first($key = null, $format = null)
    {
        $messages = is_null($key) ? $this->all($format) : $this->get($key, $format);

        return (count($messages) > 0) ? $messages[0] : '';
    }

    /**
     * Récupérez tous les messages du sac pour une clé donnée.
     *
     * @param  string  $key
     * @param  string  $format
     * @return array
     */
    public function get($key, $format = null)
    {
        $format = $this->checkFormat($format);

        if (array_key_exists($key, $this->messages)) {
            return $this->transform($this->messages[$key], $format, $key);
        }

        return array();
    }

    /**
     * Recevez tous les messages pour chaque clé dans le sac.
     *
     * @param  string  $format
     * @return array
     */
    public function all($format = null)
    {
        $format = $this->checkFormat($format);

        $all = array();

        foreach ($this->messages as $key => $messages) {
            $all = array_merge($all, $this->transform($messages, $format, $key));
        }

        return $all;
    }

    /**
     * Formatez un tableau de messages.
     *
     * @param  array   $messages
     * @param  string  $format
     * @param  string  $messageKey
     * @return array
     */
    protected function transform($messages, $format, $key)
    {
        return array_map(function ($message) use ($format, $key)
        {
            return str_replace(array(':message', ':key'), array($message, $key), $format);

        }, (array) $messages);
    }

    /**
     * Obtenez le format approprié en fonction du format donné.
     *
     * @param  string  $format
     * @return string
     */
    protected function checkFormat($format)
    {
        return $format ?: $this->format;
    }

    /**
     * Récupérez les messages bruts dans le conteneur.
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Récupérez les messages de l'instance.
     *
     * @return \Two\Support\MessageBag
     */
    public function getMessageBag()
    {
        return $this;
    }

    /**
     * Obtenez le format de message par défaut.
     *
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Définissez le format de message par défaut.
     *
     * @param  string  $format
     * @return \Two\Support\MessageBag
     */
    public function setFormat($format = ':message')
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Déterminez si le sac de messages contient des messages.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return ! $this->any();
    }

    /**
     * Déterminez si le sac de messages contient des messages.
     *
     * @return bool
     */
    public function any()
    {
        return ($this->count() > 0);
    }

    /**
     * Obtenez le nombre de messages dans le conteneur.
     *
     * @return int
     */
    public function count()
    {
        return (count($this->messages, COUNT_RECURSIVE) - count($this->messages));
    }

    /**
     * Effacez le tableau des messages.
     *
     * @return void
     */
    public function clear()
    {
        $this->messages = array();
    }

    /**
     * Obtenez l'instance sous forme de tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getMessages();
    }

    /**
     * Convertissez l'objet en sa représentation JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convertissez le sac de messages en sa représentation sous forme de chaîne.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}

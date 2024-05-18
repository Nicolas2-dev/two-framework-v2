<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Access;


class Response
{
    /**
     * Le message de réponse.
     *
     * @var string|null
     */
    protected $message;

    /**
     * Créez une nouvelle réponse.
     *
     * @param  string|null  $message
     */
    public function __construct($message = null)
    {
        $this->message = $message;
    }

    /**
     * Obtenez le message de réponse.
     *
     * @return string|null
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Obtenez la représentation sous forme de chaîne du message.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->message();
    }
}

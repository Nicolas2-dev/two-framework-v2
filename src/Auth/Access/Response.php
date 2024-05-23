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
     * The response message.
     *
     * @var string|null
     */
    protected $message;

    /**
     * Create a new response.
     *
     * @param  string|null  $message
     */
    public function __construct($message = null)
    {
        $this->message = $message;
    }

    /**
     * Get the response message.
     *
     * @return string|null
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Get the string representation of the message.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->message();
    }
}

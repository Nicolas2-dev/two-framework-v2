<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Messages;

use Two\Bus\Traits\QueueableTrait;


class BroadcastMessage
{
    use QueueableTrait;

    /**
     * Les données pour la notification.
     *
     * @var array
     */
    public $data;

    /**
     * Créez une nouvelle instance de message.
     *
     * @param  string  $content
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Définissez les données du message.
     *
     * @param  array  $data
     * @return $this
     */
    public function data($data)
    {
        $this->data = $data;

        return $this;
    }
}

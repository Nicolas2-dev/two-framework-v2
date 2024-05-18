<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Channels;

use Two\Broadcasting\Channel as BaseChannel;


class PublicChannel extends BaseChannel
{
    /**
     * Créez une nouvelle instance de canal.
     *
     * @param  string  $name
     * @return void
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}

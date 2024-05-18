<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Messages;


class DatabaseMessage
{
    /**
     * Les données qui doivent être stockées avec la notification.
     *
     * @var array
     */
    public $data = array();

    /**
     * Créez un nouveau message de base de données.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data = array())
    {
        $this->data = $data;
    }
}

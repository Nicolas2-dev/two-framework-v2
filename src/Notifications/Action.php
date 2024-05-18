<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;


class Action
{
    /**
     * Le texte de l'action.
     *
     * @var string
     */
    public $text;

    /**
     * L'URL de l'action.
     *
     * @var string
     */
    public $url;

    /**
     * Créez une nouvelle instance d'action.
     *
     * @param  string  $text
     * @param  string  $url
     * @return void
     */
    public function __construct($text, $url)
    {
        $this->url = $url;

        $this->text = $text;
    }
}

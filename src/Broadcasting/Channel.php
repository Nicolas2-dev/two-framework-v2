<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;


class Channel
{
    /**
     * Le nom de la chaîne.
     *
     * @var string
     */
    public $name = 'default';


    /**
     * Obtient le nom de la chaîne.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Convertissez l'instance de canal en chaîne.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}

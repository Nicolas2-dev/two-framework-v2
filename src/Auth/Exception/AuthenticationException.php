<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth\Exception;

use Exception;


class AuthenticationException extends Exception
{
    /**
     * Tous les gardes qui ont été contrôlés.
     *
     * @var array
     */
    protected $guards;

    /**
     * Créez une nouvelle exception d'authentification.
     *
     * @param string  $message
     */
    public function __construct($message = 'Unauthenticated.', array $guards = array())
    {
        parent::__construct($message);

        $this->guards = $guards;
    }

    /**
     * Obtenez les gardes qui ont été contrôlés.
     *
     * @return array
     */
    public function guards()
    {
        return $this->guards;
    }
}

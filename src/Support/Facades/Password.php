<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support\Facades;

use Two\Support\Facades\Facade;


/**
 * @see \Two\Auth\Reminders\PasswordBroker
 */
class Password extends Facade
{
    /**
     * Constante représentant un rappel envoyé avec succès.
     *
     * @var int
     */
    const REMINDER_SENT = 'reminders.sent';

    /**
     * Constante représentant un mot de passe réinitialisé avec succès.
     *
     * @var int
     */
    const PASSWORD_RESET = 'reminders.reset';

    /**
     * Constante représentant la réponse de l'utilisateur introuvable.
     *
     * @var int
     */
    const INVALID_USER = 'reminders.user';

    /**
     * Constante représentant un mot de passe invalide.
     *
     * @var int
     */
    const INVALID_PASSWORD = 'reminders.password';

    /**
     * Constante représentant un jeton non valide.
     *
     * @var int
     */
    const INVALID_TOKEN = 'reminders.token';

    /**
     * Obtenez le nom enregistré du composant.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'auth.password'; }

}

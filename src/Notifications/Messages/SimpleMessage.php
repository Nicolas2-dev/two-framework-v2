<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Messages;

use Two\Notifications\Action;


class SimpleMessage
{
    /**
     * Le "niveau" de la notification (info, réussite, erreur).
     *
     * @var string
     */
    public $level = 'info';

    /**
     * Objet de la notification.
     *
     * @var string
     */
    public $subject;

    /**
     * Le message d'accueil de la notification.
     *
     * @var string
     */
    public $greeting;

    /**
     * Les lignes "intro" de la notification.
     *
     * @var array
     */
    public $introLines = array();

    /**
     * Les lignes "outro" de la notification.
     *
     * @var array
     */
    public $outroLines = array();

    /**
     * Le texte/libellé de l'action.
     *
     * @var string
     */
    public $actionText;

    /**
     * L'URL de l'action.
     *
     * @var string
     */
    public $actionUrl;


    /**
     * Indiquez que la notification donne des informations sur une opération réussie.
     *
     * @return $this
     */
    public function success()
    {
        $this->level = 'success';

        return $this;
    }

    /**
     * Indiquez que la notification donne des informations sur une erreur.
     *
     * @return $this
     */
    public function error()
    {
        $this->level = 'error';

        return $this;
    }

    /**
     * Définissez le "niveau" de la notification (succès, erreur, etc.).
     *
     * @param  string  $level
     * @return $this
     */
    public function level($level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Définissez l'objet de la notification.
     *
     * @param  string  $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Définissez le message d'accueil de la notification.
     *
     * @param  string  $greeting
     * @return $this
     */
    public function greeting($greeting)
    {
        $this->greeting = $greeting;

        return $this;
    }

    /**
     * Ajoutez une ligne de texte à la notification.
     *
     * @param  \Two\Notifications\Action|string  $line
     * @return $this
     */
    public function line($line)
    {
        return $this->with($line);
    }

    /**
     * Ajoutez une ligne de texte à la notification.
     *
     * @param  \Two\Notifications\Action|string|array  $line
     * @return $this
     */
    public function with($line)
    {
        if ($line instanceof Action) {
            $this->action($line->text, $line->url);
        } else if (is_null($this->actionText)) {
            $this->introLines[] = $this->format($line);
        } else {
            $this->outroLines[] = $this->format($line);
        }

        return $this;
    }

    /**
     * Formatez la ligne de texte donnée.
     *
     * @param  string|array  $line
     * @return string
     */
    protected function format($line)
    {
        if (is_array($line)) {
            return implode(' ', array_map('trim', $line));
        }

        $lines = preg_split('/\\r\\n|\\r|\\n/', $line);

        return trim(implode(' ', array_map('trim', $lines)));
    }

    /**
     * Configurez le bouton "appel à l'action".
     *
     * @param  string  $text
     * @param  string  $url
     * @return $this
     */
    public function action($text, $url)
    {
        $this->actionText = $text;

        $this->actionUrl = $url;

        return $this;
    }

    /**
     * Obtenez une représentation matricielle du message.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'level'       => $this->level,
            'subject'     => $this->subject,
            'greeting'    => $this->greeting,
            'introLines'  => $this->introLines,
            'outroLines'  => $this->outroLines,
            'actionText'  => $this->actionText,
            'actionUrl'   => $this->actionUrl,
        );
    }
}

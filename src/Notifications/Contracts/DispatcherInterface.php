<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Contracts;


interface DispatcherInterface
{
    /**
     * Envoyez la notification donnée aux entités notifiables indiquées.
     *
     * @param  \Two\Collection\Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification);
}

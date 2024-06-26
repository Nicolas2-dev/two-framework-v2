<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Channels;

use Two\Mail\Mailer;
use Two\Support\Str;
use Two\Notifications\Notification;


class MailChannel
{
    /**
     * L’implémentation du mailer.
     *
     * @var \Two\Mail\Mailer
     */
    protected $mailer;


    /**
     * Créez une nouvelle instance de canal de messagerie.
     *
     * @param  \Two\Mail\Mailer  $mailer
     * @return void
     */
    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Envoyez la notification donnée.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $notifiable->routeNotificationFor('mail')) {
            return;
        }

        $mail = $notification->toMail($notifiable);

        $this->mailer->send($mail->view, $mail->data(), function ($message) use ($notifiable, $notification, $mail)
        {
            $recipients = empty($mail->to) ? $notifiable->routeNotificationFor('mail') : $mail->to;

            if (! empty($mail->from)) {
                $message->from($mail->from[0], isset($mail->from[1]) ? $mail->from[1] : null);
            }

            if (is_array($recipients)) {
                $message->bcc($recipients);
            } else {
                $message->to($recipients);
            }

            if (! empty($mail->cc)) {
                $message->cc($mail->cc);
            }

            if (! empty($mail->replyTo)) {
                $message->replyTo($mail->replyTo[0], isset($mail->replyTo[1]) ? $mail->replyTo[1] : null);
            }

            $message->subject($mail->subject ?: Str::title(
                Str::snake(class_basename($notification), ' ')
            ));

            foreach ($mail->attachments as $attachment) {
                $message->attach($attachment['file'], $attachment['options']);
            }

            foreach ($mail->rawAttachments as $attachment) {
                $message->attachData($attachment['data'], $attachment['name'], $attachment['options']);
            }

            if (! is_null($mail->priority)) {
                $message->setPriority($mail->priority);
            }
        });
    }
}

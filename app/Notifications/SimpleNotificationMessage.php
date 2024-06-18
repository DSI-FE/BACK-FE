<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SimpleNotificationMessage extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subject;
    protected $name;
    protected $line;

    public function __construct($subject, $name, $line)
    {
        $this->subject = $subject;
        $this->name = $name;
        $this->line = $line;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('notificaciones@dgehm.gob.sv', 'Solicitudes DGEHM')
            ->subject($this->subject)
            ->greeting('Hola, ' . $this->name)
            ->line($this->line)
            ->salutation('')
            // ->markdown('vendor.notifications.common_email_notification_message'); // Si funciona pero no interpreta las etiquetas HTML
            // ->view('common_email_notification_message', ['data' => '']); // No funciona
            // ->action('Notification Action', url('/'));
           ;
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

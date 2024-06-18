<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MyResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The token to reset the password.
     *
     * @var string
     */
    protected $token;

    /**
     * Create a new notification instance.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $url = config('app.url')."/reset-password?token={$this->token}";
        return (new MailMessage)
            ->subject('Restablecer Contraseña')
            ->greeting('Hola, '.$notifiable->name)
            ->line('¿Solicitaste restablecer tu contraseña? Haz clic en el botón y se te redireccionará a la página donde puedes hacerlo')
            ->action('Restablecer Contraseña', $url)
            ->line('En caso de que no hayas solicitado un cambio de contraseña, ignora este correo')
            ->salutation('Saludos');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

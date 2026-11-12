<?php

namespace App\Notifications;

use App\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to a new Client Admin: a link to choose their own password, then sign in to their store. */
class ClientAdminInvitation extends Notification
{
    public function __construct(
        public readonly string $setPasswordUrl,
        public readonly string $loginUrl,
        public readonly int $validForHours,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        $store = $notifiable->client?->name ?? config('app.name');

        return (new MailMessage)
            ->subject('You\'re invited to manage '.$store)
            ->greeting('Hello '.$notifiable->firstName().',')
            ->line('An account has been created for you to manage **'.$store.'**.')
            ->line('Choose your password to get started:')
            ->action('Set your password', $this->setPasswordUrl)
            ->line('This link works for '.$this->validForHours.' hours. After that, ask us to send a new one.')
            ->line('From then on you sign in at '.$this->loginUrl);
    }
}

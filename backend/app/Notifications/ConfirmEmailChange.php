<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConfirmEmailChange extends Notification
{
    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Norocel: confirm email')->line('Confirm the new address. Your current verified email remains active until confirmation.')->action('Confirm email', $this->url)->line('This link expires in 60 minutes.');
    }
}

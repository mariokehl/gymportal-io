<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailGerman extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('E-Mail-Adresse bestätigen')
            ->greeting('Hallo!')
            ->line('Bitte klicken Sie auf den Button unten, um Ihre E-Mail-Adresse zu bestätigen.')
            ->action('E-Mail bestätigen', $verificationUrl)
            ->line('Dieser Link ist 60 Minuten gültig.')
            ->line('Falls Sie kein Konto erstellt haben, ignorieren Sie diese E-Mail.')
            ->salutation('Mit freundlichen Grüßen');
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserVerificationNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify Your Account'))
            ->line(__('Please use the button below to verify your account.'))
            ->action(__('Verify Your Account'), $this->verificationUrl($notifiable))
            ->line(__('If you did not create an account, no further action is required.'));
    }

    private function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}

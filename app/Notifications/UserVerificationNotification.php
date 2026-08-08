<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserVerificationNotification extends Notification
{
    public function __construct(private readonly string $otp) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify Your Account'))
            ->action(__('Verify Your Account'), $this->verificationUrl($notifiable))
            ->view('auth.verify-account', [
                'appName' => __(config('app.name')),
                'appUrl' => config('app.url'),
                'otp' => $this->otp,
                'expiresInMinutes' => config('app.verification_expire', 1),
            ]);
    }

    private function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('app.verification_expire', 1)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}

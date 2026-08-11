<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportExportNotification extends Notification
{
    public function __construct(public readonly string $content, public readonly string $filename, public readonly string $mime) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your requested report'))
            ->view('emails.report-export', [
                'appName' => __(config('app.name')),
                'appUrl' => config('app.url'),
                'userName' => $notifiable->name,
                'filename' => $this->filename,
            ])
            ->attachData($this->content, $this->filename, ['mime' => $this->mime]);
    }
}

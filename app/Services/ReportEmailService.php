<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ReportExportNotification;
use Illuminate\Support\Facades\Notification;

class ReportEmailService
{
    public function __construct(private readonly ReportExportService $reportExportService) {}

    public function send(User $user, string $recipientEmail, string $export, array $filters): void
    {
        $file = $this->reportExportService->generate($export, $filters);
        $notification = new ReportExportNotification($file['content'], $file['filename'], $file['mime'], $user->name, $user->email, strcasecmp($recipientEmail, $user->email) !== 0);

        if (strcasecmp($recipientEmail, $user->email) === 0) {
            $user->notify($notification);

            return;
        }

        Notification::route('mail', $recipientEmail)->notify($notification);
    }
}

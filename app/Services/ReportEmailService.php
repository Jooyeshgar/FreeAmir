<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ReportExportNotification;

class ReportEmailService
{
    public function __construct(private readonly ReportExportService $reportExportService) {}

    public function send(User $user, string $export, array $filters): void
    {
        $file = $this->reportExportService->generate($export, $filters);

        $user->notify(new ReportExportNotification($file['content'], $file['filename'], $file['mime']));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ReportEmailService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class ReportEmailController extends Controller
{
    public function store(Request $request, ReportExportService $reportExportService, ReportEmailService $reportEmailService): Response
    {
        $validated = $request->validate([
            'export' => ['required', 'string', Rule::in(array_keys(ReportExportService::EXPORTS))],
            'delivery' => ['nullable', Rule::in(['download', 'email'])],
            'filters' => ['nullable', 'array'],
            'email' => [Rule::excludeIf($request->input('delivery') === 'download'), 'required', 'email:rfc', 'max:255'],
        ]);

        $definition = ReportExportService::EXPORTS[$validated['export']];
        $user = $request->user();
        $permission = $definition['permission'];
        $wildcard = preg_replace('/\.[^.]+$/', '.*', $permission);

        if (! $user->can($permission) && ! $user->can($wildcard)) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        $filters = $validated['filters'] ?? $request->except(['_token', 'export', 'delivery', 'email']);
        if (($validated['delivery'] ?? 'email') === 'download') {
            return $reportExportService->downloadResponse($validated['export'], $filters);
        }

        $recipientEmail = $validated['email'];
        $reportEmailService->send($user, $recipientEmail, $validated['export'], $filters);

        return back()->with('success', __('The report was sent to :email.', ['email' => $recipientEmail]));
    }
}

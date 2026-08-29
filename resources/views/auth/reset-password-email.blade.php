<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Reset Password Notification') }}</title>
</head>
<body style="margin: 0; background-color: #f3f4f6; color: #111827; font-family: Tahoma, Arial, sans-serif;" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <div style="padding: 16px;">
        <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="max-width: 640px; margin: 0 auto; background-color: #ffffff;">
            <tr>
                <td style="padding: 20px; background-color: #111827; text-align: center;">
                    <a href="{{ $appUrl }}" style="color: #ffffff; text-decoration: none;">
                        <h3 style="margin: 0;">{{ $appName }}</h3>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding: 16px; line-height: 1.8;">
                    <p style="color: #111827;">{{ __('Greetings.') }}</p>

                    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>

                    <p style="text-align: center;">
                        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 8px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none;">{{ $actionText }}</a>
                    </p>

                    <p>{{ __('This password reset link will expire in :count minutes.', ['count' => localizeNumber($expiresInMinutes)]) }}</p>

                    <p>{{ __('If you did not request a password reset, no further action is required.') }}</p>

                    <p>{{ __('Regards') }}، {{ $appName }}</p>

                    <p style="padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
                        {{ __("If you're having trouble clicking the \":actionText\" button, use this link:", ['actionText' => $actionText]) }}
                        <a href="{!! $actionUrl !!}" style="color: #2563eb;">{{ __('Open password reset link') }}</a>
                    </p>
                </td>
            </tr>
            <tr>
                <td style="padding: 20px; color: #6b7280; font-size: 12px; background-color: #f9fafb; text-align: center;">
                    © {{ date('Y') }} {{ $appName }}. {{ __('All rights reserved.') }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

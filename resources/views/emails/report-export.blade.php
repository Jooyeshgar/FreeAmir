<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Your requested report') }}</title>
</head>
<body style="margin: 0; background-color: #f3f4f6; color: #111827; font-family: Tahoma, Arial, sans-serif;" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <div style="padding: 24px 16px;">
        <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
            <tr>
                <td style="padding: 20px; background-color: #111827; text-align: center;">
                    <a href="{{ $appUrl }}" style="color: #ffffff; text-decoration: none;">
                        <h2 style="margin: 0;">{{ $appName }}</h2>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding: 28px 24px; line-height: 1.8;">
                    <h3 style="margin-top: 0; color: #111827;">{{ __('Your report is ready') }}</h3>

                    @if($sentToAnotherRecipient)
                        <p>{{ __('Hello,') }}</p>
                    @else
                        <p>{{ __('Hello :name,', ['name' => $userName]) }}</p>
                    @endif
                    <p>{{ __('The report you requested is attached to this email.') }}</p>

                    @if($sentToAnotherRecipient)
                        <div style="margin: 20px 0; padding: 14px 16px; border: 1px solid #fde68a; border-radius: 6px; background-color: #fffbeb;">
                            {{ __('This report was requested and sent to you by :name (:email).', ['name' => $userName, 'email' => $requesterEmail]) }}
                        </div>
                    @endif

                    <div style="margin: 20px 0; padding: 14px 16px; border: 1px solid #dbeafe; border-radius: 6px; background-color: #eff6ff;">
                        <strong>{{ __('Attached file') }}:</strong>
                        <span dir="ltr">{{ $filename }}</span>
                    </div>

                    <p style="color: #6b7280; font-size: 13px;">
                        {{ __('This report may contain confidential business information. Please store it securely.') }}
                    </p>

                    <p>{{ __('Regards') }}، {{ $appName }}</p>
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

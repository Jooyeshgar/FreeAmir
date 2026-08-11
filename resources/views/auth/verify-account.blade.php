<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Verify Your Account') }}</title>
</head>
<div style="background-color: #f3f4f6; padding: 16px; color: #111827;" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <table role="presentation" cellspacing="0" cellpadding="0" style="background-color: #ffffff;" align="center">
        <tr>
            <td style="background-color: #111827;">
                <a href="{{ $appUrl }}" style="color: #ffffff;">
                    <center><h3>{{ $appName }}</h3></center>
                </a>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px;">
                <p style="color: #111827;">{{ __('Greetings.') }}</p>

                <p>{{ __('Verify your account using either the magic link or the code below.') }}</p>

                <p>
                    <center>
                        <a href="{!! $actionUrl !!}" style="padding: 8px; background-color: #2563eb; color: #ffffff;">{{ $actionText }}</a>
                    </center>
                </p>

                <p style="margin: 24px 0 8px; text-align: center; color: #6b7280; font-size: 13px;">{{ __('Your 6-digit verification code') }}</p>
                <p style="margin: 0; text-align: center; font-family: monospace; font-size: 30px; font-weight: 700; letter-spacing: 8px; color: #111827;" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">{{ localizeNumber($otp) }}</p>
                <p style="text-align: center; color: #6b7280; font-size: 13px;">{{ __('This code expires in :minutes minutes.', ['minutes' => localizeNumber($expiresInMinutes)]) }}</p>

                <p>{{ __('If you did not create an account, no further action is required.') }}</p>

                <p>{{ __('Regards') }}، {{ $appName }}</p>

                <p style="padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
                    {{ __("If you're having trouble clicking the \":actionText\" button, use this link:", ['actionText' => $actionText]) }}
                    <a href="{!! $actionUrl !!}" style="color: #2563eb;">{{ __('Open verification link') }}</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px; color: #6b7280; font-size: 12px; background-color: #f9fafb;">
                <center>© {{ date('Y') }} {{ $appName }}. {{ __('All rights reserved.') }}</center>
            </td>
        </tr>
    </table>
</div>
</html>

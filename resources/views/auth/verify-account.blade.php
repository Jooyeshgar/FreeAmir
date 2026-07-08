<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Verify Your Account') }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; color: #111827; font-family: Tahoma, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 24px 32px; text-align: center; background-color: #111827;">
                            <a href="{{ $appUrl }}" style="color: #ffffff; font-size: 18px; font-weight: 700; text-decoration: none;">{{ $appName }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; font-size: 15px; line-height: 1.9;">
                            <h1 style="margin: 0 0 20px; font-size: 22px; color: #111827;">{{ __('Greetings.') }}</h1>

                            <p style="margin: 0 0 24px;">{{ __('Please use the button below to verify your account.') }}</p>

                            <p style="margin: 0 0 28px; text-align: center;">
                                <a href="{!! $actionUrl !!}" style="display: inline-block; padding: 12px 22px; border-radius: 6px; background-color: #2563eb; color: #ffffff; font-weight: 700; text-decoration: none;">
                                    {{ $actionText }}
                                </a>
                            </p>

                            <p style="margin: 0 0 24px;">{{ __('If you did not create an account, no further action is required.') }}</p>

                            <p style="margin: 0 0 24px;">{{ __('Regards') }}، {{ $appName }}</p>

                            <p style="margin: 24px 0 0; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
                                {{ __("If you're having trouble clicking the \":actionText\" button, use this link:", ['actionText' => $actionText]) }}
                                <a href="{!! $actionUrl !!}" style="color: #2563eb; text-decoration: underline;">{{ __('Open verification link') }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px; text-align: center; color: #6b7280; font-size: 12px; background-color: #f9fafb;">
                            © {{ date('Y') }} {{ $appName }}. {{ __('All rights reserved.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

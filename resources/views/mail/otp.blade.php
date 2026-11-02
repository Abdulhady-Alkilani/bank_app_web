<div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; text-align: right; max-width: 480px; margin: 0 auto;">
    <p>{{ __('scoring.otp.greeting', [], 'ar') }}</p>
    <p style="font-size: 32px; letter-spacing: 8px; font-weight: bold; text-align: center;">{{ $code }}</p>
    <p>{{ __('scoring.otp.validity', ['minutes' => $ttlMinutes], 'ar') }}</p>
    <hr>
    <div dir="ltr" style="text-align: left; color: #555;">
        <p>{{ __('scoring.otp.greeting', [], 'en') }} <strong>{{ $code }}</strong></p>
        <p>{{ __('scoring.otp.validity', ['minutes' => $ttlMinutes], 'en') }}</p>
    </div>
</div>

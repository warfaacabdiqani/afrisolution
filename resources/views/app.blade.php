<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $settings = app(\App\Services\SystemSettingsService::class);
            $browserTitle = $settings->get('branding.display_name', $settings->get('general.platform_name', 'Afri Clinic'));
            $favicon = $settings->get('branding.favicon');
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $browserTitle }}</title>
        @if($favicon)
            <link rel="icon" href="{{ $favicon }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
        <noscript>Please enable JavaScript to use Afri Clinic.</noscript>
    </body>
</html>

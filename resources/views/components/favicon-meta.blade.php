@php
    $faviconDir = dirname(config('aicl.theme.favicon', 'vendor/aicl/images/favicon.png'));
@endphp
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset($faviconDir . '/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset($faviconDir . '/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset($faviconDir . '/favicon-16x16.png') }}">

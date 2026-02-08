<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Report' }}</title>
    @include('aicl::pdf.styles')
</head>
<body>
    <!-- Header -->
    <div class="pdf-header">
        <table>
            <tr>
                <td class="logo">{{ config('app.name', 'AICL') }}</td>
                <td class="date">Generated: {{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="pdf-footer">
        <table>
            <tr>
                <td>{{ $title ?? 'Report' }}</td>
                <td class="text-right">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <!-- Content -->
    <div class="pdf-content">
        {{ $slot ?? '' }}
        @yield('content')
    </div>
</body>
</html>

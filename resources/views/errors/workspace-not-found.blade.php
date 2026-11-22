<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <title>{{ __('Workspace not found') }} | {{ __('Afrad') }}</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f8fb;color:#0E2A47;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',system-ui,sans-serif}
        .card{max-width:420px;margin:24px;padding:32px;background:#fff;border:1px solid #e2e8f0;border-radius:24px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        h1{font-size:22px;margin:16px 0 8px} p{color:#475569;line-height:1.7;margin:0 0 20px;font-size:15px}
        a{display:inline-block;background:#0E2A47;color:#fff;text-decoration:none;padding:10px 18px;border-radius:12px;font-weight:600;font-size:14px}
        .brand{font-weight:700;font-size:20px;letter-spacing:.02em}
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">{{ __('Afrad') }}</div>
        <h1>{{ __('Workspace not found') }}</h1>
        <p>{{ __('We could not find a company workspace at this address. Check the link you were given, or register a new company.') }}</p>
        @php($central = config('tenancy.central_domains')[0] ?? null)
        @if($central)
            <a href="{{ request()->getScheme() }}://{{ $central }}{{ request()->getPort() && ! in_array(request()->getPort(), [80, 443]) ? ':'.request()->getPort() : '' }}/">{{ __('Go to Afrad') }}</a>
        @endif
    </main>
</body>
</html>

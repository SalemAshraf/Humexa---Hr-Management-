<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/brand/png/afrad-icon-180.png">
        @if (request()->path() === '/' && ! tenancy()->initialized)
            @php
                $seoAr = app()->getLocale() === 'ar';
                $seoDesc = $seoAr
                    ? 'أفراد نظام موارد بشرية بالعربي والإنجليزي: ملفات الموظفين والحضور والإجازات والتوظيف والتقييمات في مكان واحد.'
                    : 'Employee records, attendance, leave, hiring and reviews in one workspace, in Arabic and English.';
                $seoTitle = $seoAr ? 'أفراد | برنامج موارد بشرية للشركات النامية' : 'Afrad | HR software for growing companies';
                $seoLd = ['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'Afrad', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => $seoDesc, 'url' => url('/'), 'inLanguage' => ['ar', 'en']];
            @endphp
            <meta name="description" content="{{ $seoDesc }}">
            <link rel="canonical" href="{{ url('/') }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="Afrad">
            <meta property="og:title" content="{{ $seoTitle }}">
            <meta property="og:description" content="{{ $seoDesc }}">
            <meta property="og:url" content="{{ url('/') }}">
            <meta property="og:image" content="{{ url('/brand/png/afrad-icon-512.png') }}">
            <meta property="og:locale" content="{{ $seoAr ? 'ar_EG' : 'en_US' }}">
            <meta name="twitter:card" content="summary">
            <script type="application/ld+json">{!! json_encode($seoLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @else
            {{-- Everything except the public landing page and registration is private or transactional: keep it out of search. --}}
            @if (tenancy()->initialized || ! (request()->path() === 'register'))
                <meta name="robots" content="noindex, nofollow">
            @endif
        @endif
        <title inertia>{{ __('Afrad') }}</title>


        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

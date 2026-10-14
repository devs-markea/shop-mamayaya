<!doctype html>
<html lang="es" data-theme="{{ $theme->id }}" style="{{ $theme->style() }}">
<head>
    <meta charset="utf-8" />
    {{-- viewport-fit=cover: the action bar uses env(safe-area-inset-bottom). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#ffffff" />

    {{-- The store icons live in public/. They don't follow the theme: they are images
         with the logo's colors inside. --}}
    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <meta name="msapplication-TileImage" content="/mstile-150x150.png" />

    <title>{{ $documentTitle }}</title>
    <meta name="description" content="{{ $description }}" />

    {{-- Indexable pages get a canonical and Open Graph; the order screens get noindex,
         which (unlike a robots.txt Disallow) is what takes them out of the index. --}}
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow" />
    @else
        <link rel="canonical" href="{{ $canonical }}" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $documentTitle }}" />
        <meta property="og:description" content="{{ $description }}" />
        <meta property="og:url" content="{{ $canonical }}" />
    @endif

    {{-- The latin subset of Inter, preloaded: it's on every page above the fold. --}}
    <link rel="preload" href="{{ Vite::asset('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin />

    @vite(['resources/css/app.css', 'resources/js/app.ts', ...$vite])
</head>
<body class="page">
    {{ $slot }}

    <x-chat-fab :store="$store" :dish="$dish" :order="$noindex" />
</body>
</html>

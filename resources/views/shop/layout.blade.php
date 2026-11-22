<!DOCTYPE html>
<html lang="nl-BE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#151312">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? settings()->get('shop.title') }} | {{ settings()->siteName() }}</title>
    <link rel="icon" href="{{ settings()->faviconUrl() }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:700,800|source-sans-3:400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
    @php($accent = preg_match('/^#[0-9a-f]{6}$/i', (string) settings()->get('accent_color')) ? settings()->get('accent_color') : '#e4572e')
    <style>:root { --accent: {{ $accent }}; }</style>
</head>
<body>
<header class="shop-header">
    <a href="{{ route('shop.index', isset($table) && $table ? ['tafel' => $table->label()] : []) }}" class="shop-brand">
        <img src="{{ settings()->logoUrl() ?? settings()->faviconUrl() }}" alt="" width="36" height="36">
        <span>{{ settings()->siteName() }}</span>
    </a>
    @if (isset($table) && $table)
        <span class="shop-table-badge">Tafel {{ $table->label() }}</span>
    @endif
</header>
<main class="shop-main">
    @yield('content')
</main>
@stack('scripts')
</body>
</html>

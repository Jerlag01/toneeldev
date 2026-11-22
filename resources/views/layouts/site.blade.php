<!DOCTYPE html>
<html lang="nl-BE">
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $pageTitle = \App\Support\Seo::title($title ?? null);
        $metaDescription = \App\Support\Seo::description($description ?? null);
        $indexable = \App\Support\Seo::indexable() && empty($noindex);
        $canonical = $canonical ?? url()->current();
        $shareImage = \App\Support\Seo::shareImage($image ?? null);
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) settings()->get('accent_color')) ? settings()->get('accent_color') : '#e4572e';
        $chapters = $chapters ?? [];
        $socials = collect((array) settings()->get('social_links'))->filter(fn ($link) => filled($link['url'] ?? null));
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if (filled(settings()->get('seo.keywords')))
        <meta name="keywords" content="{{ settings()->get('seo.keywords') }}">
    @endif
    <meta name="robots" content="{{ $indexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
    @if ($indexable)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    @if (filled(settings()->get('seo.google_verification')))
        <meta name="google-site-verification" content="{{ settings()->get('seo.google_verification') }}">
    @endif
    @if (filled(settings()->get('seo.bing_verification')))
        <meta name="msvalidate.01" content="{{ settings()->get('seo.bing_verification') }}">
    @endif
    <meta name="theme-color" content="#151312">

    <meta property="og:site_name" content="{{ settings()->siteName() }}">
    <meta property="og:locale" content="nl_BE">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? settings()->siteName() }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? settings()->siteName() }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $shareImage }}">

    <script type="application/ld+json">{!! \App\Support\Seo::siteGraph() !!}</script>
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">
    <link rel="icon" href="{{ settings()->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ filled(settings()->get('favicon')) ? settings()->faviconUrl() : asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700,800,700i|source-serif-4:400,400i,600|source-sans-3:400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <style>:root { --accent: {{ $accent }}; }</style>
</head>
<body @class(['has-hero' => $hasHero ?? false])>
<a class="skip-link" href="#inhoud">Spring naar de inhoud</a>
<div class="read-progress" data-progress aria-hidden="true"></div>

<header class="site-header" data-header>
    <div class="header-inner">
        <a href="{{ route('home') }}" class="brand">
            @if (settings()->logoUrl())
                <img src="{{ settings()->logoUrl() }}" alt="" height="40">
            @endif
            <span>{{ settings()->siteName() }}</span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="hoofdmenu" data-menu-toggle>
            <span class="sr-only">Menu</span>
            <span class="menu-toggle-bar"></span>
        </button>
        <nav id="hoofdmenu" class="main-nav" aria-label="Hoofdmenu">
            @if ($chapters !== [])
                {{-- On the story: jump between its chapters. --}}
                <a href="{{ route('home') }}" class="nav-home" aria-label="Naar de startpagina">&larr; Home</a>
                @foreach ($chapters as $chapter)
                    <a href="#{{ $chapter['anchor'] }}" data-chapter-link="{{ $chapter['anchor'] }}">{{ $chapter['title'] }}</a>
                @endforeach
            @else
                <a href="{{ route('home') }}" @class(['is-active' => request()->routeIs('home')])>Home</a>
                <a href="{{ route('productions.index') }}" @class(['is-active' => request()->routeIs('productions.*')])>Voorstellingen</a>
                @foreach ($menuPages as $menuPage)
                    @continue($menuPage->isHome())
                    <a href="{{ $menuPage->url() }}" @class(['is-active' => request()->url() === $menuPage->url()])>{{ $menuPage->title }}</a>
                @endforeach
            @endif
        </nav>
    </div>
</header>

<main id="inhoud">
    @yield('content')
</main>

@php($footer = (array) settings()->get('footer'))
@php($vzw = (array) settings()->get('vzw'))
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-about">
            <a href="{{ route('home') }}" class="footer-brand">
                @if (settings()->logoUrl())
                    <img src="{{ settings()->logoUrl() }}" alt="" height="48" loading="lazy">
                @endif
                <span>{{ settings()->siteName() }}</span>
            </a>
            @if (filled($footer['about'] ?? null))
                <p>{{ $footer['about'] }}</p>
            @endif
            @if (($footer['show_social'] ?? true) && $socials->isNotEmpty())
                <div class="footer-social">
                    @foreach ($socials as $social)
                        @php($platforms = \App\Filament\Pages\Settings::platforms())
                        <a href="{{ $social['url'] }}" @if (! str_starts_with($social['url'], 'mailto:')) target="_blank" rel="noopener" @endif aria-label="{{ $social['label'] ?: ($platforms[$social['platform']] ?? 'Link') }}" title="{{ $social['label'] ?: ($platforms[$social['platform']] ?? 'Link') }}">
                            @include('partials.social-icon', ['platform' => $social['platform'] ?? 'website'])
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @foreach ((array) ($footer['columns'] ?? []) as $column)
            <div class="footer-col">
                <h2>{{ $column['title'] ?? '' }}</h2>
                <ul>
                    @foreach ((array) ($column['links'] ?? []) as $link)
                        <li><a href="{{ $link['url'] ?? '#' }}" @if (! empty($link['new_tab'])) target="_blank" rel="noopener" @endif>{{ $link['label'] ?? '' }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        @if ($footer['show_vzw'] ?? true)
            <div class="footer-col footer-vzw">
                <h2>Contact</h2>
                <address>
                    <strong>{{ settings()->vzwName() }}</strong><br>
                    @if (settings()->address())
                        {{ settings()->address() }}<br>
                    @endif
                    @if (filled($vzw['company_number'] ?? null))
                        Ondernemingsnr. {{ $vzw['company_number'] }}<br>
                    @endif
                    @if (filled($vzw['rpr'] ?? null))
                        RPR {{ $vzw['rpr'] }}<br>
                    @endif
                    @if (filled($vzw['email'] ?? null))
                        <a href="mailto:{{ $vzw['email'] }}">{{ $vzw['email'] }}</a><br>
                    @endif
                    @if (filled($vzw['phone'] ?? null))
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $vzw['phone']) }}">{{ $vzw['phone'] }}</a><br>
                    @endif
                    @if (filled($vzw['iban'] ?? null))
                        IBAN {{ $vzw['iban'] }}{{ filled($vzw['bic'] ?? null) ? ' · BIC '.$vzw['bic'] : '' }}
                    @endif
                </address>
            </div>
        @endif
    </div>
    <div class="footer-bottom">
        <span>{{ settings()->fillText($footer['copyright'] ?? '') }}</span>
        @if (filled($footer['credits'] ?? null))
            <span>{{ settings()->fillText($footer['credits']) }}</span>
        @endif
        @if (filled($footer['made_by_name'] ?? null))
            <span class="footer-madeby">{{ $footer['made_by_text'] ?? 'Website door' }}
                @if (filled($footer['made_by_url'] ?? null))
                    <a href="{{ $footer['made_by_url'] }}" target="_blank" rel="noopener">
                        @if (str_contains($footer['made_by_url'], 'github.com'))
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" style="vertical-align:-2px"><path d="M12 .5a11.5 11.5 0 0 0-3.6 22.4c.6.1.8-.3.8-.6v-2c-3.2.7-3.9-1.5-3.9-1.5-.5-1.3-1.3-1.7-1.3-1.7-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.7 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.2 1.2a11 11 0 0 1 5.8 0C17.3 4.8 18.3 5.1 18.3 5.1c.6 1.6.2 2.8.1 3.1.8.8 1.2 1.9 1.2 3.1 0 4.4-2.7 5.4-5.3 5.7.4.4.8 1.1.8 2.2v3.2c0 .3.2.7.8.6A11.5 11.5 0 0 0 12 .5z"/></svg>
                        @endif
                        {{ $footer['made_by_name'] }}</a>
                @else
                    {{ $footer['made_by_name'] }}
                @endif
            </span>
        @endif
        @if (! empty($footer['bottom_links']))
            <nav aria-label="Voetnoten">
                @foreach ((array) $footer['bottom_links'] as $link)
                    <a href="{{ $link['url'] ?? '#' }}" @if (! empty($link['new_tab'])) target="_blank" rel="noopener" @endif>{{ $link['label'] ?? '' }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</footer>

<a href="#inhoud" class="to-top" aria-label="Naar boven" data-to-top>
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
</a>
<div class="lightbox" data-lightbox hidden>
    <button type="button" class="lightbox-close" data-lightbox-close aria-label="Sluiten">&times;</button>
    <button type="button" class="lightbox-prev" data-lightbox-prev aria-label="Vorige">&#8249;</button>
    <figure><img alt="" data-lightbox-img><figcaption data-lightbox-caption></figcaption></figure>
    <button type="button" class="lightbox-next" data-lightbox-next aria-label="Volgende">&#8250;</button>
</div>
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
</body>
</html>

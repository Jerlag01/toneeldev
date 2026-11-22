@php($image = media_url($data['image'] ?? null))
<section @class(['hero', 'hero--'.($data['height'] ?? 'full'), 'hero--overlay-'.($data['overlay'] ?? 'medium')])>
    @if ($image)
        <img class="hero-bg" src="{{ $image }}" alt="" fetchpriority="high">
    @endif
    <div class="hero-content">
        <h1>{{ $data['title'] ?? $page->title }}</h1>
        @if (filled($data['subtitle'] ?? null))
            <p class="hero-subtitle">{{ $data['subtitle'] }}</p>
        @endif
    </div>
    <a href="#verhaal" class="hero-scroll" aria-label="Lees het verhaal">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </a>
</section>
<div id="verhaal"></div>
@if (filled($data['intro'] ?? null))
    <div class="story-text lead hero-intro reveal">
        <p>{!! nl2br(e($data['intro'])) !!}</p>
    </div>
@endif

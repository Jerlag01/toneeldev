@php
    $cover = $production->coverUrl();
    $performances = $production->performanceList();
    $gallery = array_values(array_filter((array) ($production->gallery ?? [])));
@endphp
@extends('layouts.site', [
    'title' => $production->title,
    'description' => $production->meta_description ?: $production->summary,
    'canonical' => $production->url(),
    'image' => $production->cover_image,
    'hasHero' => (bool) $cover,
    'noindex' => ! $production->is_published,
])

@section('content')
    @unless ($production->is_published)
        <div class="draft-banner">Voorbeeld: deze voorstelling is nog niet gepubliceerd.</div>
    @endunless

    @if ($cover)
        <section class="hero hero--large hero--overlay-medium">
            <img class="hero-bg" src="{{ $cover }}" alt="" fetchpriority="high">
            <div class="hero-content">
                <p class="hero-kicker">{{ $production->dateLabel() }}</p>
                <h1>{{ $production->title }}</h1>
                @if (filled($production->topic))
                    <p class="hero-subtitle">{{ $production->topic }}</p>
                @endif
            </div>
        </section>
    @else
        <header class="page-header">
            <p class="page-kicker">{{ $production->dateLabel() }}</p>
            <h1>{{ $production->title }}</h1>
            @if (filled($production->topic))
                <p class="page-intro">{{ $production->topic }}</p>
            @endif
        </header>
    @endif

    <article class="story">
        <div class="production-facts reveal">
            @if ($performances !== [])
                <div>
                    <h2>Voorstellingen</h2>
                    <ul>
                        @foreach ($performances as $performance)
                            <li @class(['is-past' => $performance['when']->copy()->endOfDay()->isPast()])>
                                <strong>{{ ucfirst($performance['when']->translatedFormat('l j F Y')) }}</strong>{{ $performance['has_time'] ? ' om '.$performance['when']->format('H:i') : '' }}
                                @if ($performance['venue'] || $performance['note'])
                                    <span>{{ collect([$performance['venue'], $performance['note']])->filter()->implode(' · ') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <dl>
                @if (filled($production->venue))
                    <div><dt>Waar</dt><dd>{{ $production->venue }}</dd></div>
                @endif
                @if (filled($production->charity))
                    <div><dt>Goede doel</dt><dd>{{ $production->charity }}</dd></div>
                @endif
            </dl>
            @if (filled($production->ticket_url) && $production->isUpcoming())
                <a class="btn" href="{{ $production->ticket_url }}" target="_blank" rel="noopener">Tickets</a>
            @endif
        </div>

        @if (filled($production->summary))
            <div class="story-text lead reveal"><p>{!! nl2br(e($production->summary)) !!}</p></div>
        @endif

        @foreach ($production->blocks ?? [] as $block)
            @includeIf('blocks.'.str_replace('_', '-', $block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => null, 'first' => false])
        @endforeach

        @if ($gallery !== [])
            <figure class="gallery gallery--masonry gallery--cols-3 reveal" data-gallery>
                <div class="gallery-items">
                    @foreach ($gallery as $image)
                        <a href="{{ media_url($image) }}" class="gallery-item" data-lightbox-item data-caption="{{ $production->title }}">
                            <img src="{{ media_url($image) }}" alt="{{ $production->title }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            </figure>
        @endif

        <div class="story-button"><a class="btn btn-ghost" href="{{ route('productions.index') }}">&larr; Alle voorstellingen</a></div>
    </article>
@endsection

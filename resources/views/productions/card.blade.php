@php($cover = $production->coverUrl())
<a href="{{ $production->url() }}" @class(['production-card', 'production-card--featured' => $featured ?? false, 'is-upcoming' => $production->isUpcoming()])>
    <div class="production-card-media">
        @if ($cover)
            <img src="{{ $cover }}" alt="" loading="lazy">
        @else
            <div class="production-card-placeholder" aria-hidden="true"><span>{{ $production->title }}</span></div>
        @endif
        @if ($production->isUpcoming())
            <span class="production-badge">Binnenkort</span>
        @endif
    </div>
    <div class="production-card-body">
        <span class="production-date">{{ $production->dateLabel() }}</span>
        <h3>{{ $production->title }}</h3>
        @if (filled($production->topic))
            <p class="production-topic">{{ $production->topic }}</p>
        @endif
        @if (($featured ?? false) && filled($production->summary))
            <p class="production-summary">{{ \Illuminate\Support\Str::limit($production->summary, 180) }}</p>
        @endif
    </div>
</a>

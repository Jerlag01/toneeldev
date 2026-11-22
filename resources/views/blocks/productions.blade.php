@php
    $mode = $data['mode'] ?? 'upcoming';
    $query = \App\Models\Production::query()->published();
    $query = match ($mode) { 'past' => $query->past(), 'all' => $query->orderByDesc('starts_on'), default => $query->upcoming() };
    $items = $query->when(filled($data['limit'] ?? null), fn ($q) => $q->limit((int) $data['limit']))->get();
@endphp
<section @class(['productions-block', 'reveal', 'productions-block--'.$mode])>
    @if (filled($data['heading'] ?? null))
        <h2 class="section-title">{{ $data['heading'] }}</h2>
    @endif
    @if ($items->isEmpty())
        <p class="productions-empty">{{ ($data['empty_text'] ?? null) ?: 'Binnenkort meer nieuws!' }}</p>
    @else
        <div @class(['production-grid', 'production-grid--featured' => $mode === 'upcoming'])>
            @foreach ($items as $production)
                @include('productions.card', ['production' => $production, 'featured' => $mode === 'upcoming'])
            @endforeach
        </div>
    @endif
    @if (! empty($data['show_more']))
        <div class="story-button"><a class="btn btn-ghost" href="{{ route('productions.index') }}">Alle voorstellingen</a></div>
    @endif
</section>

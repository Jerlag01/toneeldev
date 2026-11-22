@extends('layouts.site', ['title' => 'Voorstellingen', 'description' => 'Alle toneelstukken van Het Vrolijk Toneel Initiatief: wat er binnenkort op de planken staat en alle voorstellingen sinds 1997.'])

@section('content')
    <header class="page-header">
        <h1>Voorstellingen</h1>
        <p class="page-intro">Wat er binnenkort op de planken staat, en alles wat we sinds 1997 speelden.</p>
    </header>

    <section class="productions-block">
        <h2 class="section-title">Binnenkort</h2>
        @if ($upcoming->isEmpty())
            <p class="productions-empty">Ons volgende toneelstuk wordt binnenkort aangekondigd. Volg ons op sociale media!</p>
        @else
            <div class="production-grid production-grid--featured">
                @foreach ($upcoming as $production)
                    @include('productions.card', ['production' => $production, 'featured' => true])
                @endforeach
            </div>
        @endif
    </section>

    @if ($past->isNotEmpty())
        <section class="productions-block">
            <h2 class="section-title">Voorbije voorstellingen</h2>
            <div class="production-grid">
                @foreach ($past as $production)
                    @include('productions.card', ['production' => $production])
                @endforeach
            </div>
        </section>
    @endif
@endsection

@extends('layouts.site', ['title' => 'Pagina niet gevonden', 'noindex' => true])

@section('content')
    <header class="page-header">
        <h1>Doek!</h1>
    </header>
    <div class="story">
        <div class="story-text lead">
            <p>Deze pagina bestaat niet (meer). Misschien vind je wat je zoekt in <a href="{{ route('home') }}">het verhaal</a>.</p>
        </div>
    </div>
@endsection

@php
    $blocks = $page->blocks ?? [];
    $hasHero = ($blocks[0]['type'] ?? null) === 'hero';
@endphp
@extends('layouts.site', [
    'title' => \App\Support\Seo::pageTitle($page),
    'description' => $page->meta_description,
    'canonical' => $page->url(),
    'image' => $page->share_image ?: (($blocks[0]['type'] ?? null) === 'hero' ? ($blocks[0]['data']['image'] ?? null) : null),
    'chapters' => $page->chapters(),
    'hasHero' => $hasHero,
    'noindex' => ! $page->is_published,
])

@section('content')
    @unless ($page->is_published)
        <div class="draft-banner">Voorbeeld: deze pagina is nog niet gepubliceerd.</div>
    @endunless
    @unless ($hasHero)
        <header class="page-header">
            <h1>{{ $page->title }}</h1>
        </header>
    @endunless

    <article class="story">
        @foreach ($blocks as $block)
            @includeIf('blocks.'.str_replace('_', '-', $block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page, 'first' => $loop->first])
        @endforeach
    </article>
@endsection

@extends('shop.layout', ['title' => settings()->get('shop.closed_title')])

@section('content')
    <section class="shop-message">
        <div class="shop-message-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        </div>
        <h1>{{ settings()->get('shop.closed_title') }}</h1>
        <p>{!! nl2br(e(settings()->get('shop.closed_message'))) !!}</p>
    </section>
@endsection

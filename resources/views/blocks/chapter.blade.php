@php($image = media_url($data['image'] ?? null))
<section @class(['chapter', 'chapter--image' => $image]) @if (filled($data['anchor'] ?? null)) id="{{ $data['anchor'] }}" @endif data-chapter>
    @if ($image)
        <img class="chapter-bg" src="{{ $image }}" alt="" loading="lazy">
    @endif
    <div class="chapter-inner reveal">
        <h2>{{ $data['title'] ?? '' }}</h2>
        @if (filled($data['subtitle'] ?? null))
            <p>{{ $data['subtitle'] }}</p>
        @endif
    </div>
</section>

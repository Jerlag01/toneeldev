@if ($src = media_url($data['image'] ?? null))
    <figure @class(['story-image', 'story-image--'.($data['width'] ?? 'wide'), 'reveal']) data-gallery>
        <a href="{{ $src }}" data-lightbox-item data-caption="{{ $data['caption'] ?? '' }}">
            <img src="{{ $src }}" alt="{{ $data['caption'] ?? '' }}" loading="lazy">
        </a>
        @if (filled($data['caption'] ?? null))
            <figcaption>{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif

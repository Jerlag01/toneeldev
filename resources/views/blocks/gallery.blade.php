@php($images = array_values(array_filter((array) ($data['images'] ?? []))))
@if ($images !== [])
    <figure @class(['gallery', 'gallery--'.($data['layout'] ?? 'masonry'), 'gallery--cols-'.($data['columns'] ?? '3'), 'reveal']) data-gallery>
        <div class="gallery-items">
            @foreach ($images as $image)
                <a href="{{ media_url($image) }}" class="gallery-item" data-lightbox-item data-caption="{{ $data['caption'] ?? '' }}">
                    <img src="{{ media_url($image) }}" alt="{{ $data['caption'] ?? '' }}" loading="lazy">
                </a>
            @endforeach
        </div>
        @if (filled($data['caption'] ?? null))
            <figcaption>{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif

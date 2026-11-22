@if (($data['source'] ?? 'youtube') === 'upload')
    @if ($src = media_url($data['file'] ?? null))
        <figure class="story-video reveal">
            <video controls preload="metadata" playsinline @if ($poster = media_url($data['poster'] ?? null)) poster="{{ $poster }}" @endif>
                <source src="{{ $src }}">
            </video>
            @if (filled($data['caption'] ?? null))
                <figcaption>{{ $data['caption'] }}</figcaption>
            @endif
        </figure>
    @endif
@elseif ($id = youtube_id($data['url'] ?? null))
    <figure class="story-video reveal">
        {{-- Loads YouTube (and its cookies) only after a click. --}}
        @include('partials.youtube', ['id' => $id, 'caption' => $data['caption'] ?? null])
        @if (filled($data['caption'] ?? null))
            <figcaption>{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif

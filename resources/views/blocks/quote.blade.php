<blockquote class="story-quote reveal">
    <p>{!! nl2br(e($data['text'] ?? '')) !!}</p>
    @if (filled($data['author'] ?? null))
        <cite>{{ $data['author'] }}</cite>
    @endif
</blockquote>

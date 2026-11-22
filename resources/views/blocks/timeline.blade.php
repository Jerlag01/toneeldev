<div class="story-text reveal">
    @if (filled($data['heading'] ?? null))
        <h3>{{ $data['heading'] }}</h3>
    @endif
    <ol class="timeline">
        @foreach ((array) ($data['items'] ?? []) as $item)
            <li>
                <span class="timeline-year">{{ $item['year'] ?? '' }}</span>
                <span class="timeline-title">
                    @if (filled($item['url'] ?? null))
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['title'] ?? '' }}</a>
                    @else
                        {{ $item['title'] ?? '' }}
                    @endif
                    @if (filled($item['note'] ?? null))
                        <small>{{ $item['note'] }}</small>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</div>

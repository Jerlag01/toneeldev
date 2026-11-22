@php($items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => filled(is_array($item) ? ($item['text'] ?? null) : $item))))
<div class="story-text reveal">
    @if (filled($data['heading'] ?? null))
        <h3>{{ $data['heading'] }}</h3>
    @endif
    @if (filled($data['intro'] ?? null))
        <p>{!! nl2br(e($data['intro'])) !!}</p>
    @endif
    @php($tag = ($data['style'] ?? 'bullets') === 'numbered' ? 'ol' : 'ul')
    <{{ $tag }} @class(['story-list', 'story-list--columns' => ($data['style'] ?? '') === 'columns'])>
        @foreach ($items as $item)
            <li>{{ is_array($item) ? $item['text'] : $item }}</li>
        @endforeach
    </{{ $tag }}>
</div>

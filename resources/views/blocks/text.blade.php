<div @class(['story-text', 'reveal', 'lead' => ! empty($data['lead'])])>
    @if (filled($data['heading'] ?? null))
        <h3>{{ $data['heading'] }}</h3>
    @endif
    {!! rich_text($data['content'] ?? null) !!}
</div>

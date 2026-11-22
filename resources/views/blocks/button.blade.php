<div class="story-button reveal">
    <a href="{{ $data['url'] ?? '#' }}" class="btn" @if (! empty($data['new_tab'])) target="_blank" rel="noopener" @endif>{{ $data['label'] ?? '' }}</a>
</div>

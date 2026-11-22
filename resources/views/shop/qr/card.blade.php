{{-- One table card. Positions are % of the card; sizes are % of the card width (cqw), so it scales to any paper size. --}}
@php
    $el = $design['elements'];
    $label = $table->label();
    $fill = fn (?string $text) => str_replace(['{tafel}', '{rij}', '{nummer}', '{site}'], [$label, $table->row, $table->number, settings()->siteName()], (string) $text);
    $logo = media_url($design['logo'] ?? null) ?? settings()->logoUrl() ?? settings()->faviconUrl();
    $bg = media_url($design['background_image'] ?? null);
@endphp
<div class="qr-card" style="width: {{ (float) $design['width_mm'] }}mm; height: {{ (float) $design['height_mm'] }}mm; background-color: {{ $design['background_color'] }}; color: {{ $design['text_color'] }}; font-family: '{{ $design['font'] }}', Georgia, serif; {{ $bg ? "background-image:url('{$bg}');" : '' }} {{ ! empty($design['border']) ? 'outline: .2mm dashed #bbb;' : '' }}">
    @if ($el['logo']['visible'] ?? false)
        <img class="qr-el" data-key="logo" src="{{ $logo }}" alt="" style="left: {{ $el['logo']['x'] }}%; top: {{ $el['logo']['y'] }}%; width: {{ $el['logo']['size'] }}cqw;">
    @endif
    @if ($el['title']['visible'] ?? false)
        <div class="qr-el qr-text" data-key="title" style="left: {{ $el['title']['x'] }}%; top: {{ $el['title']['y'] }}%; font-size: {{ $el['title']['size'] }}cqw; color: {{ $design['accent_color'] }};">{{ $fill($el['title']['text'] ?? '') }}</div>
    @endif
    @if ($el['qr']['visible'] ?? true)
        <div class="qr-el qr-code" data-key="qr" style="left: {{ $el['qr']['x'] }}%; top: {{ $el['qr']['y'] }}%; width: {{ $el['qr']['size'] }}cqw; height: {{ $el['qr']['size'] }}cqw;">{!! \App\Support\Qr::svg(route('shop.index', ['tafel' => $label]), $design['qr_color']) !!}</div>
    @endif
    @if ($el['table']['visible'] ?? true)
        <div class="qr-el qr-text qr-table" data-key="table" style="left: {{ $el['table']['x'] }}%; top: {{ $el['table']['y'] }}%; font-size: {{ $el['table']['size'] }}cqw;">{{ $fill($el['table']['text'] ?? 'Tafel {tafel}') }}</div>
    @endif
    @if ($el['text']['visible'] ?? false)
        <div class="qr-el qr-text qr-small" data-key="text" style="left: {{ $el['text']['x'] }}%; top: {{ $el['text']['y'] }}%; font-size: {{ $el['text']['size'] }}cqw;">{{ $fill($el['text']['text'] ?? '') }}</div>
    @endif
</div>

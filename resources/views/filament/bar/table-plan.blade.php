<x-filament-panels::page>
    @include('filament.bar.styles')
    <div wire:poll.{{ $this->refreshSeconds() }}s style="display:flex; flex-direction:column; gap:1.25rem;">
        @include('filament.bar.shop-toggle')

        @php($rows = $this->rows())
        @php($open = $this->openPerTable())
        @php($maxNumber = $rows->flatten()->max('number') ?? 0)

        @if ($rows->isEmpty())
            <div class="vb-empty">Nog geen tafels. Maak ze aan bij Bar → Tafels (knop "Tafels genereren").</div>
        @elseif (\App\Support\Shop\FloorPlan::hasLayout())
            @include('filament.bar.floor-styles')
            @php($plan = \App\Support\Shop\FloorPlan::data())
            @php($pct = fn ($v, $total) => round($v / $total * 100, 4).'%')
            <div class="fp-wrap fp-live">
                <div class="fp-canvas" style="aspect-ratio: {{ $plan['width'] }} / {{ $plan['height'] }}; --cols: {{ $plan['width'] }}; --rows: {{ $plan['height'] }};">
                    @foreach ($plan['elements'] as $el)
                        @php($color = $el['color'] ?: (\App\Support\Shop\FloorPlan::types()[$el['type']]['color'] ?? '#cccccc'))
                        @php($hex = substr(ltrim($color, '#'), 0, 6))
                        @php($light = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) > 150)
                        <div class="fp-item fp-el fp-type-{{ $el['type'] }}" wire:key="el-{{ $el['id'] }}"
                             style="left: {{ $pct($el['x'], $plan['width']) }}; top: {{ $pct($el['y'], $plan['height']) }}; width: {{ $pct($el['w'], $plan['width']) }}; height: {{ $pct($el['h'], $plan['height']) }}; {{ $el['type'] === 'text' ? 'background:transparent;' : 'background:'.$color.';color:'.($light ? '#1d1a18' : '#fff').';' }}">
                            <span class="fp-label">{{ $el['label'] }}</span>
                        </div>
                    @endforeach
                    @foreach ($plan['tables'] as $t)
                        @continue($t['x'] === null)
                        @php($info = $open[$t['id']] ?? null)
                        <button type="button" wire:click="select({{ $t['id'] }})" wire:key="pt-{{ $t['id'] }}"
                                @class(['fp-item', 'fp-table', 'is-round' => $t['shape'] === 'round', 'is-inactive' => ! $t['active'], 'has-orders' => $info, 'is-old' => $info && $info['oldest']->lt(now()->subMinutes(10)), 'is-current' => $selected === $t['id']])
                                style="left: {{ $pct($t['x'], $plan['width']) }}; top: {{ $pct($t['y'], $plan['height']) }}; width: {{ $pct($t['w'], $plan['width']) }}; height: {{ $pct($t['h'], $plan['height']) }};"
                                title="Tafel {{ $t['label'] }}{{ $t['name'] ? ' - '.$t['name'] : '' }}{{ $info ? ': '.$info['count'].' open' : '' }}">
                            {{ $t['label'] }}
                            @if ($info)
                                <span class="vb-badge">{{ $info['count'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
            @php($unplaced = collect($plan['tables'])->whereNull('x'))
            @if ($unplaced->isNotEmpty())
                <div class="vb-toolbar">
                    <span class="vb-muted">Niet op het grondplan:</span>
                    <div style="display:flex; flex-wrap:wrap; gap:.5rem; flex:1">
                        @foreach ($unplaced as $t)
                            @php($info = $open[$t['id']] ?? null)
                            <button type="button" wire:click="select({{ $t['id'] }})" @class(['vb-cell', 'has-orders' => $info, 'is-selected' => $selected === $t['id']]) style="width:4rem;height:2.6rem;font-size:.95rem">
                                {{ $t['label'] }}@if ($info)<span class="vb-badge">{{ $info['count'] }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="vb-legend">
                <span>Geen open bestelling</span><span class="l-orders">Open bestelling(en)</span><span class="l-old">Langer dan 10 minuten</span>
                @if (auth()->user()?->hasPermission(\App\Support\Permissions::SHOP_TABLES))
                    <a href="{{ \App\Filament\Pages\Bar\FloorPlanEditor::getUrl() }}" style="text-decoration:underline">Grondplan aanpassen</a>
                @endif
            </div>
        @else
            @if (auth()->user()?->hasPermission(\App\Support\Permissions::SHOP_TABLES))
                <div class="vb-muted">Dit is een automatisch tafelplan. <a href="{{ \App\Filament\Pages\Bar\FloorPlanEditor::getUrl() }}" style="text-decoration:underline">Teken het echte grondplan</a> met gangen, bar, deuren...</div>
            @endif
            <div class="vb-plan">
                <div class="vb-stage">{{ settings()->get('shop.stage_label', 'Podium') }} (voorkant)</div>
                @foreach ($rows as $row => $tables)
                    @php($byNumber = $tables->keyBy('number'))
                    <div class="vb-row">
                        <span class="vb-row-label">{{ $row }}</span>
                        @for ($n = 1; $n <= $maxNumber; $n++)
                            @if ($table = $byNumber->get($n))
                                @php($info = $open[$table->id] ?? null)
                                <button type="button" wire:click="select({{ $table->id }})" wire:key="cell-{{ $table->id }}"
                                    @class(['vb-cell', 'has-orders' => $info, 'is-old' => $info && $info['oldest']->lt(now()->subMinutes(10)), 'is-selected' => $selected === $table->id, 'is-inactive' => ! $table->is_active])
                                    title="Tafel {{ $table->label() }}{{ $table->name ? ' - '.$table->name : '' }}">
                                    {{ $table->label() }}
                                    @if ($info)
                                        <span class="vb-badge">{{ $info['count'] }}</span>
                                        <small>{{ settings()->bonnen($info['total']) }}</small>
                                    @elseif ($table->name)
                                        <small>{{ \Illuminate\Support\Str::limit($table->name, 10) }}</small>
                                    @endif
                                </button>
                            @else
                                <span class="vb-cell" style="visibility:hidden" aria-hidden="true"></span>
                            @endif
                        @endfor
                        <span class="vb-row-label">{{ $row }}</span>
                    </div>
                @endforeach
                <div class="vb-back">{{ settings()->get('shop.back_label', 'Technische toren') }} (achterkant)</div>
                <div class="vb-legend">
                    <span>Geen open bestelling</span><span class="l-orders">Open bestelling(en)</span><span class="l-old">Langer dan 10 minuten</span>
                    <span style="opacity:.8">Rij A vooraan bij het podium · nummer 1 links</span>
                </div>
            </div>
        @endif

        @if ($table = $this->selectedTable())
            @php($orders = $this->selectedOrders())
            <x-filament::section>
                <x-slot name="heading">Tafel {{ $table->label() }}{{ $table->name ? ' - '.$table->name : '' }}</x-slot>
                <x-slot name="afterHeader">
                    <div style="display:flex; gap:.5rem;">
                        @if ($orders->count() > 1)
                            <button type="button" class="vb-btn" wire:click="closeTable({{ $table->id }})" wire:confirm="Alle {{ $orders->count() }} bestellingen van tafel {{ $table->label() }} afsluiten?">Alles afsluiten</button>
                        @endif
                        <button type="button" class="vb-btn" wire:click="select(null)">Sluiten</button>
                    </div>
                </x-slot>
                @if ($orders->isEmpty())
                    <p class="vb-muted">Geen openstaande bestellingen voor deze tafel.</p>
                @else
                    <div class="vb-grid">
                        @foreach ($orders as $order)
                            @include('filament.bar.order-card', ['order' => $order])
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>

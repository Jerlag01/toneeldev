<x-filament-panels::page>
    @php($data = $this->data())
    <style>
        .ss-filter { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .ss-filter label { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; font-weight: 600; }
        .ss-filter input { border: 1px solid rgb(0 0 0 / .15); border-radius: .5rem; padding: .4rem .6rem; background: transparent; }
        .dark .ss-filter input { border-color: rgb(255 255 255 / .2); color-scheme: dark; }
        .ss-filter button { padding: .4rem .8rem; border-radius: 999px; border: 1px solid rgb(0 0 0 / .12); font-size: .85rem; }
        .dark .ss-filter button { border-color: rgb(255 255 255 / .15); }
        .ss-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 1rem; }
        .ss-tile-label { font-size: .8rem; opacity: .7; }
        .ss-tile-value { font-size: 1.6rem; font-weight: 700; }
        .ss-wrap { overflow-x: auto; }
        .ss-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .ss-table th, .ss-table td { padding: .45rem .6rem; border-bottom: 1px solid rgb(0 0 0 / .07); white-space: nowrap; }
        .dark .ss-table th, .dark .ss-table td { border-color: rgb(255 255 255 / .08); }
        .ss-table th { text-align: left; font-size: .75rem; opacity: .75; font-weight: 600; }
        .ss-table .n { text-align: right; font-variant-numeric: tabular-nums; }
        .ss-table .zero { opacity: .25; }
        .ss-table .cat td { font-weight: 700; background: rgb(0 0 0 / .03); }
        .dark .ss-table .cat td { background: rgb(255 255 255 / .04); }
        .ss-table tfoot td { font-weight: 700; border-top: 2px solid rgb(0 0 0 / .15); }
        .ss-bar { height: 6px; background: #b4232a; border-radius: 3px; margin-top: 3px; }
    </style>

    <div class="ss-filter">
        <label>Van <input type="date" wire:model.live="from"></label>
        <label>Tot en met <input type="date" wire:model.live="until"></label>
        <button type="button" wire:click="preset('today')">Vandaag</button>
        <button type="button" wire:click="preset('7d')">7 dagen</button>
        <button type="button" wire:click="preset('30d')">30 dagen</button>
        <button type="button" wire:click="preset('year')">Dit jaar</button>
    </div>

    <div class="ss-tiles">
        <x-filament::section><div class="ss-tile-label">Verkochte producten</div><div class="ss-tile-value">{{ number_format($data['totals']['quantity'], 0, ',', '.') }}</div></x-filament::section>
        <x-filament::section><div class="ss-tile-label">Bestellingen</div><div class="ss-tile-value">{{ number_format($data['totals']['orders'], 0, ',', '.') }}</div></x-filament::section>
        <x-filament::section><div class="ss-tile-label">Omzet</div><div class="ss-tile-value">{{ settings()->bonnen($data['totals']['bonnen']) }}</div></x-filament::section>
        <x-filament::section><div class="ss-tile-label">Waarvan online betaald</div><div class="ss-tile-value">€ {{ number_format($data['totals']['online_eur'], 2, ',', '.') }}</div></x-filament::section>
    </div>

    <x-filament::section heading="Per product en per dag">
        @if ($data['rows'] === [])
            <p style="opacity:.65">Geen verkopen in deze periode.</p>
        @else
            @php($max = max(1, collect($data['rows'])->max('quantity')))
            <div class="ss-wrap">
                <table class="ss-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            @foreach ($data['days'] as $day)
                                <th class="n">{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('D j/n') }}</th>
                            @endforeach
                            <th class="n">Totaal</th>
                            <th class="n">Bonnen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($data['rows'])->groupBy('category') as $category => $rows)
                            <tr class="cat"><td colspan="{{ count($data['days']) + 3 }}">{{ $category ?: 'Zonder categorie' }}</td></tr>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['name'] }}<div class="ss-bar" style="width: {{ $row['quantity'] / $max * 100 }}%"></div></td>
                                    @foreach ($data['days'] as $day)
                                        @php($q = $row['per_day'][$day] ?? 0)
                                        <td @class(['n', 'zero' => $q === 0])>{{ $q }}</td>
                                    @endforeach
                                    <td class="n"><strong>{{ $row['quantity'] }}</strong></td>
                                    <td class="n">{{ settings()->bonnen($row['bonnen']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Totaal producten</td>
                            @foreach ($data['days'] as $day)
                                <td class="n">{{ $data['day_totals'][$day]['quantity'] ?? 0 }}</td>
                            @endforeach
                            <td class="n">{{ $data['totals']['quantity'] }}</td>
                            <td class="n">{{ settings()->bonnen($data['totals']['bonnen']) }}</td>
                        </tr>
                        <tr>
                            <td>Bestellingen</td>
                            @foreach ($data['days'] as $day)
                                <td class="n">{{ $data['day_totals'][$day]['orders'] ?? 0 }}</td>
                            @endforeach
                            <td class="n">{{ $data['totals']['orders'] }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Omzet (bonnen)</td>
                            @foreach ($data['days'] as $day)
                                <td class="n">{{ settings()->bonnen($data['day_totals'][$day]['bonnen'] ?? 0) }}</td>
                            @endforeach
                            <td class="n" colspan="2">{{ settings()->bonnen($data['totals']['bonnen']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>

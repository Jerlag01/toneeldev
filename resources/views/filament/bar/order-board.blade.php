<x-filament-panels::page>
    @include('filament.bar.styles')
    <div wire:poll.{{ $this->refreshSeconds() }}s>
        @include('filament.bar.shop-toggle')

        @php($orders = $this->orders())
        @php($selected = $this->selectedTable())
        @if ($selected)
            <div class="vb-toolbar" style="margin-top:1rem;">
                <strong>Tafel {{ $selected->label() }}: {{ $orders->count() }} open {{ $orders->count() === 1 ? 'bestelling' : 'bestellingen' }}</strong>
                <button type="button" class="vb-btn" wire:click="clearTable">Alle tafels tonen</button>
            </div>
        @endif

        <div style="margin-top:1rem;">
            @if ($orders->isEmpty())
                <div class="vb-empty">
                    <x-filament::icon icon="heroicon-o-check-circle" style="width:3rem;height:3rem;margin:0 auto .5rem;" />
                    Geen openstaande bestellingen.
                </div>
            @else
                <div class="vb-grid">
                    @foreach ($orders as $order)
                        @include('filament.bar.order-card', ['order' => $order])
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Plays a short sound when new orders come in (after the first click on the page, as browsers require). --}}
    <div x-data="{ count: {{ $this->orders()->count() }}, ctx: null }"
         x-init="document.addEventListener('click', () => { ctx ??= new (window.AudioContext || window.webkitAudioContext)(); }, { once: true });
                 Livewire.hook('morphed', () => { const n = document.querySelectorAll('.vb-order').length; if (n > count && ctx) { const o = ctx.createOscillator(); const g = ctx.createGain(); o.frequency.value = 880; g.gain.value = .08; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + .18); } count = n; });"></div>
    <x-filament-actions::modals />
</x-filament-panels::page>

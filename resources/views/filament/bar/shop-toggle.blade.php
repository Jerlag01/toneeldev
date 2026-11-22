<div class="vb-toolbar">
    <div style="display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
        <span @class(['vb-status', 'is-open' => $this->shopOpen(), 'is-closed' => ! $this->shopOpen()])>{{ $this->shopOpen() ? 'Bestellen is open' : 'Bestellen is gesloten' }}</span>
        @if (auth()->user()?->hasAnyPermission(\App\Support\Permissions::SHOP_SETTINGS, \App\Support\Permissions::SHOP_ORDERS))
            <button type="button" class="vb-btn" wire:click="toggleShop" wire:confirm="{{ $this->shopOpen() ? 'Bestellen sluiten? Gasten zien dan de melding uit de instellingen.' : 'Bestellen weer openzetten?' }}">
                {{ $this->shopOpen() ? 'Bestellen sluiten' : 'Bestellen openen' }}
            </button>
        @endif
    </div>
    <span class="vb-muted">Vernieuwt automatisch elke {{ $this->refreshSeconds() }} seconden · {{ now()->format('H:i:s') }}</span>
</div>

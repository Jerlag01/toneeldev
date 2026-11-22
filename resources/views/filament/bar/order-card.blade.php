@php($old = $order->created_at->lt(now()->subMinutes(10)))
<article @class(['vb-order', 'is-old' => $old]) wire:key="order-{{ $order->id }}">
    <div class="vb-order-head">
        <div>
            <div class="vb-table">{{ $order->table_label }}</div>
            <small>#{{ $order->number }}</small>
        </div>
        <div class="vb-time">
            {{ $order->created_at->format('H:i') }}<br>
            <span title="{{ $order->created_at->format('d/m/Y H:i:s') }}">{{ $order->created_at->diffForHumans(short: true) }}</span>
        </div>
    </div>
    <div class="vb-order-body">
        <div class="vb-name">{{ $order->customer_name ?: 'Zonder naam' }}</div>
        <ul class="vb-items">
            @foreach ($order->items as $item)
                <li><b>{{ $item->quantity }}&times;</b><span>{{ $item->name }}</span></li>
            @endforeach
        </ul>
        @if ($order->note)
            <div class="vb-note">{{ $order->note }}</div>
        @endif
    </div>
    <div class="vb-pay">
        @if ($order->isPaidOnline())
            <span class="vb-paid">&#10003; Online betaald</span>
            <span class="vb-muted">{{ settings()->bonnen($order->total) }}</span>
        @else
            <span><span class="vb-muted">Ontvangen:</span> <span class="vb-bonnen">{{ settings()->bonnen($order->total) }}</span></span>
        @endif
    </div>
    @if (auth()->user()?->hasPermission(\App\Support\Permissions::SHOP_ORDERS))
        <div style="padding: 0 .9rem .9rem; display:flex; gap:.5rem; align-items:center;">
            <button type="button" class="vb-btn vb-btn-primary" style="flex:1; justify-content:center;" wire:click="closeOrder({{ $order->id }})" wire:loading.attr="disabled">
                Afsluiten (gebracht)
            </button>
            {{ ($this->cancelOrderAction)(['order' => $order->id])->iconButton()->tooltip('Annuleren (geannuleerd, misbruik...)') }}
        </div>
    @endif
</article>

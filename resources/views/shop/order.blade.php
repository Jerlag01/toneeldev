@extends('shop.layout', ['title' => 'Bestelling '.$order->number, 'table' => $order->table])

@section('content')
    <section class="shop-message order-status" data-order-status="{{ $order->status }}" data-status-url="{{ route('shop.status', $order->token) }}">
        @switch($order->status)
            @case(\App\Models\Order::PENDING)
                <div class="shop-message-icon is-waiting" aria-hidden="true"><span class="spinner"></span></div>
                <h1>{{ $cancelled ? 'Betaling niet afgerond' : 'Wachten op je betaling' }}</h1>
                <p>{{ $cancelled ? 'Je hebt de online betaling afgebroken. Je bestelling wordt pas doorgegeven als ze betaald is.' : 'Even geduld, we controleren je betaling...' }}</p>
                <a class="btn-order" href="{{ route('shop.retry', $order->token) }}">Opnieuw proberen te betalen</a>
                <a class="btn-link" href="{{ route('shop.index', ['tafel' => $order->table_label]) }}">Terug naar de kaart</a>
                @break
            @case(\App\Models\Order::CANCELLED)
                <div class="shop-message-icon is-error" aria-hidden="true">&times;</div>
                <h1>Bestelling geannuleerd</h1>
                @if ($order->cancelled_by)
                    <p>Je bestelling is geannuleerd door de bar.{{ $order->refunded_at ? ' Je online betaling wordt terugbetaald.' : '' }} Vragen? Spreek iemand aan aan de bar.</p>
                @else
                    <p>De online betaling is mislukt of verlopen. Er is niets aangerekend en je bestelling is niet doorgegeven.</p>
                @endif
                <a class="btn-order" href="{{ route('shop.index', ['tafel' => $order->table_label]) }}">Opnieuw bestellen</a>
                @break
            @default
                <div class="shop-message-icon is-ok" aria-hidden="true">&#10003;</div>
                <h1>{{ $order->status === \App\Models\Order::CLOSED ? 'Je bestelling is gebracht' : 'Bedankt, '.$order->customer_name.'!' }}</h1>
                @if ($order->status === \App\Models\Order::OPEN)
                    <p>Je bestelling is doorgegeven aan de bar en wordt zo snel mogelijk naar <strong>tafel {{ $order->table_label }}</strong> gebracht.</p>
                @endif
                @if ($order->isPaidOnline())
                    <div class="pay-box is-paid">Online betaald</div>
                @else
                    <div class="pay-box">
                        <span>Geef af bij het brengen</span>
                        <strong>{{ settings()->bonnen($order->total) }}</strong>
                    </div>
                @endif
        @endswitch

        <div class="order-summary">
            <div class="order-summary-head"><span>Bestelling {{ $order->number }}</span><span>Tafel {{ $order->table_label }}</span></div>
            <ul>
                @foreach ($order->items as $item)
                    <li><span>{{ $item->quantity }} &times; {{ $item->name }}</span><span>{{ settings()->bonnen($item->price * $item->quantity) }}</span></li>
                @endforeach
            </ul>
            <div class="order-summary-total"><span>Totaal</span><strong>{{ settings()->bonnen($order->total) }}{{ $order->isOnline() ? ' (€ '.number_format((float) $order->total_eur, 2, ',', '.').')' : '' }}</strong></div>
            @if ($order->note)
                <p class="order-note">Opmerking: {{ $order->note }}</p>
            @endif
        </div>

        @if (in_array($order->status, [\App\Models\Order::OPEN, \App\Models\Order::CLOSED], true))
            <a class="btn-link" href="{{ route('shop.index', ['tafel' => $order->table_label]) }}">Nog iets bestellen</a>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        // Clear the cart after a successful order, and follow the status without reloading by hand.
        (function () {
            var box = document.querySelector('[data-order-status]');
            var status = box.getAttribute('data-order-status');
            try { if (status === 'open' || status === 'closed') localStorage.removeItem('vti-cart'); } catch (e) {}
            if (status === 'closed' || status === 'cancelled') return;
            setInterval(function () {
                fetch(box.getAttribute('data-status-url'), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { if (data.status !== status) location.reload(); })
                    .catch(function () {});
            }, status === 'pending_payment' ? 3000 : 15000);
        })();
    </script>
@endpush

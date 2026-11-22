<article @class(['product', 'is-soldout' => ! $product->isOrderable()]) data-product="{{ $product->id }}">
    @if ($product->image)
        <img class="product-img" src="{{ media_url($product->image) }}" alt="" loading="lazy" width="72" height="72">
    @endif
    <div class="product-body">
        <h3>{{ $product->name }}</h3>
        @if (filled($product->description))
            <p>{{ $product->description }}</p>
        @endif
        <span class="product-price">{{ settings()->bonnen($product->price) }}</span>
    </div>
    <div class="product-actions">
        @if ($product->isOrderable())
            <button type="button" class="qty-btn" data-minus aria-label="Eén {{ $product->name }} minder" hidden>&minus;</button>
            <span class="qty" data-qty hidden>0</span>
            <button type="button" class="qty-btn qty-plus" data-plus aria-label="{{ $product->name }} toevoegen">+</button>
        @else
            <span class="soldout-badge">Uitverkocht</span>
        @endif
    </div>
</article>

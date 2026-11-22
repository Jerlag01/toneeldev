@extends('shop.layout')

@section('content')
    <section class="shop-intro">
        <h1>{{ settings()->get('shop.title') }}</h1>
        @if (filled(settings()->get('shop.intro')))
            <p>{{ settings()->get('shop.intro') }}</p>
        @endif
        @unless ($table)
            <p class="shop-hint">Tip: scan de QR-code op je tafel, dan staat je tafelnummer al juist.</p>
        @endunless
    </section>

    @if ($categories->count() > 1)
        <nav class="shop-cats" aria-label="Categorieën" data-cats>
            @foreach ($categories as $category)
                <a href="#cat-{{ $category->id }}" @class(['is-active' => $loop->first])>{{ $category->name }}</a>
            @endforeach
        </nav>
    @endif

    <div class="shop-menu">
        @php($groups = $products->groupBy(fn ($p) => $p->shop_category_id ?? 0))
        @foreach ($categories as $category)
            @continue(! isset($groups[$category->id]))
            <section class="shop-cat" id="cat-{{ $category->id }}" data-cat-section>
                <h2>{{ $category->name }}</h2>
                @foreach ($groups[$category->id] as $product)
                    @include('shop.partials.product', ['product' => $product])
                @endforeach
            </section>
        @endforeach
        @if (isset($groups[0]))
            <section class="shop-cat" data-cat-section>
                <h2>Andere</h2>
                @foreach ($groups[0] as $product)
                    @include('shop.partials.product', ['product' => $product])
                @endforeach
            </section>
        @endif
        @if ($products->isEmpty())
            <p class="shop-empty">Er staat nog niets op de kaart.</p>
        @endif
    </div>

    {{-- Sticky cart bar and checkout sheet --}}
    <div class="cart-bar" data-cart-bar hidden>
        <button type="button" class="cart-bar-btn" data-open-checkout>
            <span class="cart-count" data-cart-count>0</span>
            <span>Bekijk bestelling</span>
            <strong data-cart-total>0</strong>
        </button>
    </div>

    <div class="sheet" data-sheet hidden role="dialog" aria-modal="true" aria-labelledby="sheet-title">
        <div class="sheet-backdrop" data-close-checkout></div>
        <form class="sheet-panel" data-checkout novalidate>
            <div class="sheet-head">
                <h2 id="sheet-title">Je bestelling</h2>
                <button type="button" class="sheet-close" data-close-checkout aria-label="Sluiten">&times;</button>
            </div>
            <ul class="sheet-lines" data-lines></ul>
            <div class="sheet-total"><span>Totaal</span><strong data-sheet-total>0</strong></div>

            <label class="field">
                <span>Tafel</span>
                @if ($table)
                    <input type="hidden" name="table" value="{{ $table->label() }}">
                    <div class="field-static">Tafel {{ $table->label() }}{{ $table->name ? ' - '.$table->name : '' }}</div>
                @else
                    <select name="table" required>
                        <option value="">Kies je tafel</option>
                        @foreach ($tables as $t)
                            <option value="{{ $t->label() }}">Tafel {{ $t->label() }}{{ $t->name ? ' - '.$t->name : '' }}</option>
                        @endforeach
                    </select>
                @endif
            </label>

            <label class="field">
                <span>Je naam{{ settings()->get('shop.require_name', true) ? '' : ' (optioneel)' }}</span>
                <input type="text" name="name" maxlength="100" autocomplete="given-name" @required(settings()->get('shop.require_name', true)) data-remember="name">
            </label>

            @if (settings()->get('shop.allow_note', true))
                <label class="field">
                    <span>Opmerking (optioneel)</span>
                    <input type="text" name="note" maxlength="500" placeholder="bv. zonder ijs">
                </label>
            @endif

            <fieldset class="field">
                <legend>Betalen</legend>
                @foreach ($methods as $key => $label)
                    <label class="radio">
                        <input type="radio" name="payment_method" value="{{ $key }}" @checked($loop->first)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
                <p class="field-help" data-bonnen-help>{{ settings()->get('shop.bonnen_instructions') }}</p>
            </fieldset>

            <div class="sheet-errors" data-errors hidden></div>
            <button type="submit" class="btn-order" data-submit>Bestelling plaatsen</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        window.SHOP = {!! json_encode([
            'menu' => $menu,
            'storeUrl' => route('shop.store'),
            'singular' => settings()->get('shop.currency_singular', 'bon'),
            'plural' => settings()->get('shop.currency_plural', 'bonnen'),
            'bonValue' => (float) settings()->get('shop.bon_value', 1),
            'maxQuantity' => (int) settings()->get('shop.max_quantity', 20),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
    </script>
    <script src="{{ asset('js/shop.js') }}?v={{ filemtime(public_path('js/shop.js')) }}" defer></script>
@endpush

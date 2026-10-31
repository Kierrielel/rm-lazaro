@extends('layouts.customer')

@section('title', $product->name)

@section('content')
<div class="product-page">
    <div class="product-image">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
    </div>

    <div class="product-details">
        <h2>{{ $product->name }}</h2>
        <p class="brand">{{ $product->brand }}</p>
        <p class="price" id="product-price">&#8369;{{ number_format($product->price, 2) }}</p>
        <p class="stock" id="product-stock">
            @if ($product->hasVariants())
                Select options
            @else
                {{ $product->stock_quantity }} in stock
            @endif
        </p>
        <p class="description">{{ $product->description }}</p>

        @auth
            @if ($product->hasVariants() || $product->stock_quantity > 0)
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    @if ($product->hasVariants())
                        @php
                            $variantData = $product->variants->map(fn ($v) => [
                                'id' => $v->id,
                                'options' => $v->options,
                                'price' => (float) ($v->price ?? $product->price),
                                'stock' => (float) $v->stock_quantity,
                            ])->values();
                            $optionTypes = array_keys($product->variation_options ?? []);
                        @endphp

                        <div class="variant-picker">
                            @foreach ($product->variation_options ?? [] as $type => $values)
                                <div class="variant-group">
                                    <h4>{{ $type }}</h4>
                                    @foreach ($values as $value)
                                        <label class="variant-option">
                                            <input type="radio" name="opt[{{ $type }}]" value="{{ $value }}" data-type="{{ $type }}">
                                            <span>{{ $value }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                            <input type="hidden" name="variant_id" id="variant_id">
                        </div>
                    @endif

                    <label for="quantity">Quantity</label>
                    <div class="qty-box product-qty-box">
                        <button type="button" class="qty-btn" data-quantity-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input class="qty-number product-qty-input" type="number" name="quantity" id="quantity" value="1" min="1" step="1"
                               @if (!$product->hasVariants()) max="{{ $product->stock_quantity }}" @endif>
                        <button type="button" class="qty-btn" data-quantity-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit">Add to Cart</button>
                </form>

                <script>
                document.querySelectorAll('.product-qty-box').forEach(function (box) {
                    const input = box.querySelector('input[name="quantity"]');

                    box.querySelectorAll('[data-quantity-step]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const min = Number(input.min) || 1;
                            const max = input.max ? Number(input.max) : Infinity;
                            const next = (Number(input.value) || min) + Number(button.dataset.quantityStep);

                            input.value = Math.min(max, Math.max(min, next));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                });
                </script>

                @if ($product->hasVariants())
                    <script>
                    (function () {
                        const variants = @json($variantData);
                        const types = @json($optionTypes);
                        const hidden = document.getElementById('variant_id');
                        const form = hidden.closest('form');
                        const submit = form.querySelector('button[type="submit"]');
                        const qty = form.querySelector('input[name="quantity"]');
                        const priceEl = document.getElementById('product-price');
                        const stockEl = document.getElementById('product-stock');
                        const basePrice = priceEl ? priceEl.textContent : '';

                        function update() {
                            const picked = {};
                            form.querySelectorAll('.variant-picker input[type="radio"]:checked')
                                .forEach(el => { picked[el.dataset.type] = el.value; });

                            const complete = types.every(t => picked[t] !== undefined);
                            const match = complete
                                ? variants.find(v => types.every(t => v.options[t] === picked[t]))
                                : null;

                            let message = '';
                            let canBuy = false;

                            if (!complete) {
                                message = 'Select all options';
                            } else if (!match) {
                                message = 'This combination is not available';
                            } else if (match.stock < 1) {
                                message = 'Sold out';
                            } else {
                                message = match.stock + ' in stock';
                                canBuy = true;
                            }

                            hidden.value = match ? match.id : '';
                            if (stockEl) stockEl.textContent = message;
                            if (priceEl) {
                                priceEl.textContent = match
                                    ? '\u20B1' + match.price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                    : basePrice;
                            }
                            if (qty) {
                                if (canBuy) {
                                    qty.max = match.stock;
                                    if (Number(qty.value) > match.stock) qty.value = match.stock;
                                } else {
                                    qty.removeAttribute('max');
                                }
                            }
                            submit.disabled = !canBuy;
                        }

                        form.querySelectorAll('.variant-picker input[type="radio"]').forEach(r => r.addEventListener('change', update));
                        update();
                    })();
                    </script>
                @endif
            @else
                <p class="out-of-stock">Out of stock</p>
            @endif
        @else
            <p><a href="{{ route('login') }}">Log in</a> to add this to your cart.</p>
        @endauth
    </div>
</div>
@endsection

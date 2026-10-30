@extends('layouts.customer')

@section('title', 'Your Cart')

@section('content')
<div class="cart-page">
    <h1 class="cart-title">Your Cart</h1>

    @if ($items->isNotEmpty())
        <div class="cart-layout">

            <div class="cart-list">
                @foreach ($items as $item)
                    <div class="cart-item">
                        <div class="cart-item-img">
                            <img src="{{ ($item->variant ?? $item->product)->image_url }}" alt="{{ $item->product->name }}">
                        </div>

                        <div class="cart-item-info">
                            <a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a>
                            @if ($item->variant)
                                <small>{{ $item->variant->name }}</small>
                            @endif
                            <p class="cart-item-price">&#8369;{{ number_format($item->unit_price, 2) }}</p>
                        </div>

                        <div class="qty-box">
                            <form action="{{ route('cart.update', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="qty-btn">-</button>
                            </form>

                            <span class="qty-number">{{ $item->quantity }}</span>

                            <form action="{{ route('cart.update', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="qty-btn">+</button>
                            </form>
                        </div>

                        <div class="cart-item-subtotal">
                            <p>&#8369;{{ number_format($item->subtotal, 2) }}</p>
                            <form action="{{ route('cart.remove', $item) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="remove-btn">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="cart-summary">
                <h3>Order Summary</h3>

                <div class="summary-row summary-address">
                    <span class="summary-label">Delivery Address</span>
                    @if (auth()->user()->hasAddress())
                    <span class="summary-value">{{ auth()->user()->full_address }}</span>
                    @else
                        <span class="summary-value">
                            {{-- TODO 3: replace the # with a route() link once the edit page exists --}}
                            <a href="#">Please add your address first before making an order</a>
                        </span>
                    @endif
                </div>

                <div class="summary-row">
                    <span>Items</span>
                    <span>{{ $items->sum('quantity') }}</span>
                </div>

                <form action="{{ route('checkout.store') }}" method="POST">
                    @csrf
                    <fieldset class="payment-methods">
                        <legend>Payment Method</legend>

                        <label class="payment-option" for="cod">
                            <input type="radio" id="cod" name="payment_method" value="cod" required>
                            <span>Cash on delivery <small>COD</small></span>
                        </label>

                        <label class="payment-option" for="qrph">
                            <input type="radio" id="qrph" name="payment_method" value="qrph" required>
                            <span>QRPh</span>
                        </label>

                        <label class="payment-option payment-option-disabled" for="gcash">
                            <input type="radio" id="gcash" name="payment_method" value="gcash" disabled required>
                            <span>GCash <small>Unavailable</small></span>
                        </label>

                        <label class="payment-option payment-option-disabled" for="maya">
                            <input type="radio" id="maya" name="payment_method" value="maya" disabled required>
                            <span>Maya <small>Unavailable</small></span>
                        </label>

                        @if (auth()->user()->credit_approved)
                            <label class="payment-option" for="paylater">
                                <input type="radio" id="paylater" name="payment_method" value="paylater" required>
                                <span>Pay later</span>
                            </label>
                        @endif
                    </fieldset>

                    @if (auth()->user()->credit_approved)
                    <div id="paylater-options"
                         hidden
                         data-order-date="{{ now()->toDateString() }}"
                         data-principal="{{ number_format($total, 2, '.', '') }}">

                        <label for="credit_term_months">Choose an installment term</label>
                        <select name="credit_term_months" id="credit_term_months" disabled>
                            <option value="">Choose a term</option>
                            <option value="3">3 months</option>
                            <option value="6">6 months</option>
                            <option value="9">9 months</option>
                            <option value="12">12 months</option>
                        </select>

                        @error('credit_term_months')
                            <p class="error">{{ $message }}</p>
                        @enderror

                        <div id="paylater-preview" hidden>
                            <p>Order total: <strong id="credit-principal"></strong></p>
                            <p>Interest: 3.5% per month on the original order total</p>
                            <p>Finance charge: <strong id="credit-charge"></strong></p>
                            <p>Total payable: <strong id="credit-payable"></strong></p>
                            <p>Installment schedule:</p>
                            <ol id="credit-installments"></ol>
                        </div>

                        <label>
                            <input type="checkbox" name="confirm_credit_plan" value="1" disabled>
                            I confirm the Pay Later term and the finance charge, total payable, and
                            installment schedule shown above.
                        </label>

                    </div>

                    @endif
                
                    <div class="summary-row total-row">
                        <span>Total</span>
                        <strong>&#8369;{{ number_format($total, 2) }}</strong>
                    </div>

                    <button type="submit" class="checkout-btn">Checkout</button>
                </form>
            </div>
        </div>
    @else
        <div class="empty-cart">
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added anything yet.</p>
            <a href="{{ route('products.index') }}" class="shop-now-btn">Shop Now</a>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const options = document.getElementById('paylater-options');

    if (!options) {
        return;
    }

    const radios = document.querySelectorAll('input[name="payment_method"]');
    const termSelect = document.getElementById('credit_term_months');
    const termsCheckbox = document.querySelector('input[name="confirm_credit_plan"]');
    const preview = document.getElementById('paylater-preview');

    const money = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    });

    function updatePayLaterForm() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        const isPayLater = selected && selected.value === 'paylater';

        options.hidden = !isPayLater;
        termSelect.disabled = !isPayLater;
        termSelect.required = isPayLater;
        termsCheckbox.disabled = !isPayLater;
        termsCheckbox.required = isPayLater;

        const months = Number(termSelect.value);
        const principal = Number(options.dataset.principal);

        if (!isPayLater || !months) {
            preview.hidden = true;
            return;
        }

        const charge = Math.round(principal * 0.035 * months * 100) / 100;
        const payable = Math.round((principal + charge) * 100) / 100;
        const totalCents = Math.round(payable * 100);
        const baseCents = Math.floor(totalCents / months);
        const extraCents = totalCents % months;

        const [year, month, day] = options.dataset.orderDate.split('-').map(Number);
        const startDate = new Date(year, month - 1, day);
        const schedule = document.getElementById('credit-installments');
        const dateFormat = new Intl.DateTimeFormat('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        schedule.replaceChildren();

        for (let number = 1; number <= months; number++) {
            const monthIndex = startDate.getMonth() + number;
            const dueYear = startDate.getFullYear() + Math.floor(monthIndex / 12);
            const dueMonth = monthIndex % 12;
            const lastDay = new Date(dueYear, dueMonth + 1, 0).getDate();
            const dueDate = new Date(dueYear, dueMonth, Math.min(day, lastDay));
        
            const cents = baseCents + (number <= extraCents ? 1 : 0);
            const item = document.createElement('li');
            item.textContent = `${money.format(cents / 100)} due ${dateFormat.format(dueDate)}`;
            schedule.appendChild(item);
        }

        document.getElementById('credit-principal').textContent = money.format(principal);
        document.getElementById('credit-charge').textContent = money.format(charge);
        document.getElementById('credit-payable').textContent = money.format(payable);

        preview.hidden = false;
    }

    radios.forEach(radio => radio.addEventListener('change', updatePayLaterForm));
    termSelect.addEventListener('change', updatePayLaterForm);

    updatePayLaterForm();
});
</script>
@endsection

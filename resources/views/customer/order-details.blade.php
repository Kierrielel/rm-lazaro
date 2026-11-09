@extends('layouts.customer')

@section('title', 'Order #'.$order->id)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/account-overview.css') }}">
    <link rel="stylesheet" href="{{ asset('css/customer-order-details.css') }}">
@endpush

@section('content')
<div class="account-overview account-layout">
    @include('customer.account-sidebar')

    <div class="account-main customer-order-details">
        <a class="order-details-back" href="{{ route('customer.accountOverview') }}">&larr; Back to my orders</a>

        <section class="order-details-status" aria-labelledby="order-title">
            <div class="order-details-heading">
                <div>
                    <p class="order-details-eyebrow">Order #{{ $order->id }}</p>
                    <h1 id="order-title">{{ $statusDetails['title'] }}</h1>
                </div>
                <span class="account-order-status status-{{ $order->order_status }}">{{ $order->status_label }}</span>
            </div>
            <p>{{ $statusDetails['message'] }}</p>
            <p class="order-details-placed">Placed {{ $order->order_date->format('M j, Y \a\t g:i A') }}</p>

            @if ($order->order_status !== 'cancelled')
                <ol class="order-details-progress" aria-label="Order progress">
                    @foreach (['Order placed', 'Processing', 'Out for delivery', 'Completed'] as $index => $step)
                        <li class="{{ $progressStep >= $index ? 'is-reached' : '' }}"
                            @if ($progressStep === $index) aria-current="step" @endif>
                            <span class="order-details-step-dot" aria-hidden="true"></span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <div class="order-details-columns">
            <div>
                <section class="account-card order-details-address">
                    <h2>Delivery address</h2>
                    <p><strong>{{ $user->name ?: $user->username }}</strong></p>
                    <p>{{ $user->phone_no }}</p>
                    <p>{{ $user->hasAddress() ? $user->full_address : 'No delivery address saved on your account.' }}</p>
                    <small>Current address saved on your account.</small>
                </section>

                <section class="account-card">
                    <h2>Items in your order</h2>
                    @forelse ($order->items as $item)
                        <article class="order-details-product">
                            <img src="{{ ($item->variant ?? $item->product)?->image_url ?? asset('images/logo.png') }}"
                                 alt="{{ $item->product?->name ?? 'Product image' }}"
                                 width="88" height="88" loading="lazy">
                            <div class="order-details-product-info">
                                @if ($item->product)
                                    <a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a>
                                @else
                                    <strong>Product no longer available</strong>
                                @endif
                                @if ($item->variant_name)
                                    <p class="order-details-muted">{{ $item->variant_name }}</p>
                                @endif
                                <p>&#8369;{{ number_format((float) $item->price, 2) }} each</p>
                                <p class="order-details-muted">Quantity: {{ $item->quantity }}</p>
                            </div>
                            <strong class="order-details-line-total">&#8369;{{ number_format((float) $item->price * $item->quantity, 2) }}</strong>
                        </article>
                    @empty
                        <p>No item details are available for this order.</p>
                    @endforelse
                </section>

                @if ($order->payment_method === 'paylater')
                    <section class="account-card">
                        <h2>Installment schedule</h2>
                        @php
                            $paidInstallments = $order->installments->filter(fn ($installment) =>
                                round((float) $installment->payments->sum('amount'), 2) >= (float) $installment->amount_due
                            )->count();
                        @endphp
                        <p class="order-details-muted">Installments paid: {{ $paidInstallments }}/{{ $order->installments->count() }}</p>
                        @if ($order->order_status === 'cancelled')
                            <p>This order is cancelled. Its installments are excluded from your active Pay Later bills.</p>
                        @else
                            <div class="account-orders-scroll">
                                <table class="account-orders-table order-details-installments">
                                    <thead>
                                        <tr>
                                            <th scope="col">Installment</th>
                                            <th scope="col">Due date</th>
                                            <th scope="col">Amount</th>
                                            <th scope="col">Remaining</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($order->installments as $installment)
                                            @php
                                                $balance = max(0, round((float) $installment->amount_due - (float) $installment->payments->sum('amount'), 2));
                                                $installmentStatus = $balance <= 0 ? 'Paid' : ($installment->due_date->isBefore(today()) ? 'Overdue' : 'Unpaid');
                                            @endphp
                                            <tr>
                                                <td>{{ $installment->installment_number }}</td>
                                                <td>{{ $installment->due_date->format('M j, Y') }}</td>
                                                <td>&#8369;{{ number_format((float) $installment->amount_due, 2) }}</td>
                                                <td>&#8369;{{ number_format($balance, 2) }}</td>
                                                <td>{{ $installmentStatus }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5">No installment schedule is available for this order.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endif
            </div>

            <div>
                <section class="account-card order-details-summary">
                    <h2>Payment summary</h2>
                    <dl>
                        <div><dt>Payment method</dt><dd>{{ $paymentLabel }}</dd></div>
                        <div><dt>Order total</dt><dd>&#8369;{{ number_format((float) $order->total_amount, 2) }}</dd></div>
                        @if ($order->payment_method === 'paylater')
                            <div><dt>Term</dt><dd>{{ $order->credit_term_months ?? '—' }} months</dd></div>
                            <div><dt>Monthly interest rate</dt><dd>{{ number_format((float) $order->credit_interest_rate, 2) }}%</dd></div>
                            <div><dt>Finance charge</dt><dd>&#8369;{{ number_format((float) $order->credit_finance_charge, 2) }}</dd></div>
                            <div class="order-details-summary-total"><dt>Total payable</dt><dd>&#8369;{{ number_format($order->payable_amount, 2) }}</dd></div>
                            <div><dt>Payments recorded</dt><dd>&#8369;{{ number_format($paidAmount, 2) }}</dd></div>
                            @if ($order->order_status !== 'cancelled')
                                <div><dt>Remaining balance</dt><dd>&#8369;{{ number_format($remainingBalance, 2) }}</dd></div>
                            @endif
                        @endif
                    </dl>
                    @if ($order->payment_method === 'paylater' && $order->order_status !== 'cancelled' && $remainingBalance > 0)
                        <a class="order-details-pay-link" href="{{ route('credit.apply') }}">Manage Pay Later payments</a>
                    @endif
                </section>

                @if ($order->order_status === 'pending')
                    <section class="account-card order-details-cancellation">
                        <h2>Need to cancel?</h2>
                        @if ($canCancel)
                            <p>You can cancel while this order is pending.</p>
                            <form method="POST" action="{{ route('customer.orders.cancel', $order) }}"
                                  onsubmit="return confirm('Cancel this order? This action cannot be undone.');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="order-details-cancel-button">Cancel order</button>
                            </form>
                        @elseif ($hasBlockingPayment)
                            <p>This order has a recorded or unresolved payment. Please contact the store before cancelling.</p>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.customer')

@section('title', 'My Account')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/account-overview.css') }}">
@endpush

@section('content')
<div class="account-overview account-layout">
    @include('customer.account-sidebar')

    <div class="account-main">
    <h1>My Account</h1>

    <div class="account-details-grid">
        <section class="account-card">
            <h2>Account Details</h2>
            <p>{{ $user->username }}</p>
            <p>{{ $user->email }}</p>
            <p>{{ $user->phone_no }}</p>
        </section>

        <section class="account-card">
            <h2>Billing Address</h2>
            <p>Name: {{ $user->name ?: $user->username }}</p>
            <p>Province: {{ $user->province ?: 'Not provided' }}</p>
            <p>City: {{ $user->city ?: 'Not provided' }}</p>
            <p>Barangay: {{ $user->barangay ?: 'Not provided' }}</p>
            <p>Unit: {{ $user->unit ?: 'Not provided' }}</p>
        </section>
    </div>

    <section class="account-card" aria-labelledby="my-orders-title">
        <h2 id="my-orders-title">My Orders</h2>

        <form method="GET" action="{{ route('customer.accountOverview') }}" class="account-order-filters">
            <div>
                <label for="order-status">Status</label>
                <select id="order-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="order-payment">Payment method</label>
                <select id="order-payment" name="payment_method">
                    <option value="">All payment methods</option>
                    @foreach ($paymentOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedPaymentMethod === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="order-sort">Sort by</label>
                <select id="order-sort" name="sort">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="account-filter-button">Apply</button>
            <a href="{{ route('customer.accountOverview') }}" class="account-filter-reset">Reset</a>
        </form>

        <p class="account-order-summary">
            Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
        </p>

        <div class="account-orders-scroll">
            <table class="account-orders-table">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Date</th>
                        <th scope="col">Items</th>
                        <th scope="col">Payment method</th>
                        <th scope="col">Order total</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <a class="account-order-details-link" href="{{ route('customer.orders.show', $order) }}" aria-label="View order {{ $order->id }}">
                                    #{{ $order->id }}
                                    <small>View details</small>
                                </a>
                            </td>
                            <td>{{ $order->order_date->format('M j, Y') }}</td>
                            <td>
                                @forelse ($order->items as $item)
                                    <div class="account-order-item">
                                        <img
                                            src="{{ ($item->variant ?? $item->product)?->image_url ?? asset('images/logo.png') }}"
                                            alt="{{ $item->product?->name ?? 'Product image' }}"
                                            width="48" height="48" loading="lazy"
                                        >
                                        <div>
                                            <span>{{ $item->product?->name ?? 'Product no longer available' }}</span>
                                            @if ($item->variant_name)
                                                <small>{{ $item->variant_name }}</small>
                                            @endif
                                            <small>Quantity: {{ $item->quantity }}</small>
                                        </div>
                                    </div>
                                @empty
                                    <span>No items available.</span>
                                @endforelse
                            </td>
                            <td>{{ $paymentOptions[$order->payment_method] ?? ucfirst($order->payment_method) }}</td>
                            <td class="account-order-amount">&#8369;{{ number_format((float) $order->total_amount, 2) }}</td>
                            <td><span class="account-order-status status-{{ $order->order_status }}">{{ $order->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="account-orders-empty">No orders found. Try changing the filters or place your first order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->onEachSide(1)->links('vendor.pagination.admin-compact') }}
    </section>
    </div>
</div>
@endsection

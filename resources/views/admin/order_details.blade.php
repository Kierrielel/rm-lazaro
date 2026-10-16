@extends('layouts.admin')

@section('title', 'Order #' . $order->id)

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/orders.css') }}">
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}">

<div class="details-page">

    {{-- Header --}}
    <a href="{{ route('admin.orders') }}" class="back-link">&larr; Back to orders</a>

    <div class="details-header">
        <h2>Order #{{ $order->id }}</h2>
        <span class="order-status order-{{ strtolower($order->order_status) }}">
            {{ ucfirst($order->order_status) }}
        </span>
    </div>

    <div class="details-layout">

        {{-- Left: items --}}
        <div class="table-card details-items">
            <div class="card-title">Items</div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th class="amount-col">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td data-label="Product" class="item-name">{{ $item->product->name ?? 'Deleted product' }}</td>
                            <td data-label="Quantity">{{ $item->quantity }}</td>
                            <td data-label="Price">&#8369;{{ number_format($item->price, 2) }}</td>
                            <td data-label="Subtotal" class="amount-col">&#8369;{{ number_format($item->price * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="total-label">Total</td>
                        <td class="amount-col total-amount">&#8369;{{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="details-side">

            <div class="info-card">
                <div class="card-title">Customer</div>

                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span>{{ $order->user->username ?? 'Deleted user' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span>{{ $order->user->email ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span>{{ $order->order_date->format('M d, Y g:i A') }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="info-card status-form">
                @csrf
                @method('PATCH')

                <div class="card-title">Update Status</div>

                <label for="order_status">Status</label>
                <select name="order_status" id="order_status">
                    @foreach (['pending', 'processing', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($order->order_status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>

        </div>
    </div>

</div>
@endsection
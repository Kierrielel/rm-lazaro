@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/orders.css') }}">

<div class="orders-page">

    <div class="orders-header">
        <div>
            <h2>Orders</h2>
            <p class="subtitle">Track and manage customer orders.</p>
        </div>

        <form method="GET" action="{{ route('admin.orders') }}" class="filter-form">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['pending', 'processing', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="table-card">
        <table class="order-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td data-label="Order" class="order-id">#{{ $order->id }}</td>

                        <td data-label="Customer">{{ $order->user->username ?? 'Deleted user' }}</td>

                        <td data-label="Total" class="order-total">&#8369;{{ number_format($order->total_amount, 2) }}</td>

                        <td data-label="Status">
                            <span class="order-status order-{{ strtolower($order->order_status) }}">
                                {{ ucfirst($order->order_status) }}
                            </span>
                        </td>

                        <td data-label="Date" class="order-date">{{ $order->order_date->format('M d, Y') }}</td>

                        <td data-label="Actions" class="actions-col">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-blue">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-row">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        {{ $orders->links() }}
    </div>

</div>
@endsection
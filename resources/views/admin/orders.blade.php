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

        <form method="GET" action="{{ route('admin.orders') }}" class="filter-form orders-filter-form" role="search" aria-label="Search and filter orders">
            <div class="orders-filter-field orders-search-field">
                <label for="order-search">Search orders</label>
                <input type="search" id="order-search" name="search" maxlength="100" value="{{ $search }}" placeholder="Order #, customer, email or phone">
            </div>
            <div class="orders-filter-field">
                <label for="order-status">Status</label>
                <select id="order-status" name="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Order::STATUS_LABELS as $status => $label)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="orders-filter-field">
                <label for="order-sort">Sort by</label>
                <select id="order-sort" name="sort">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="orders-filter-actions">
                <button type="submit" class="btn btn-blue">Apply</button>
                <a href="{{ route('admin.orders') }}" class="btn orders-reset">Reset</a>
            </div>
        </form>
    </div>

    <p class="orders-result-summary" role="status">{{ number_format($orders->total()) }} {{ $orders->total() === 1 ? 'order' : 'orders' }} found</p>
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
                                {{ $order->status_label }}
                            </span>
                        </td>

                        <td data-label="Date" class="order-date">{{ $order->order_date->format('M d, Y') }}</td>

                        <td data-label="Actions" class="actions-col">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-blue">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-row">No orders match your search or filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        {{ $orders->links('vendor.pagination.admin-compact') }}
    </div>

</div>
@endsection

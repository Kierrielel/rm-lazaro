@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-page">
    <h2>Dashboard</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Customers</h4>
            <p>{{ $totalCustomers }}</p>
        </div>
        <div class="stat-card">
            <h4>Monthly Revenue</h4>
            <p>&#8369;{{ number_format($monthlyRevenue, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>Total Orders</h4>
            <p>{{ $totalOrders }}</p>
        </div>
        <div class="stat-card">
            <h4>Average Order Value</h4>
            <p>&#8369;{{ number_format($averageOrderValue, 2) }}</p>
        </div>
    </div>

    <div class="table-card">
        <h3>Recent Orders</h3>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a></td>
                        <td>{{ $order->user->username ?? 'Deleted user' }}</td>
                        <td>&#8369;{{ number_format($order->total_amount, 2) }}</td>
                        <td>
                            <span class="status-badge status-{{ strtolower($order->order_status) }}">
                                {{ ucfirst($order->order_status) }}
                            </span>
                        </td>
                        <td>{{ $order->order_date->format('M d, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="table-card">
        <h3>Low Stock Alert</h3>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lowStockProducts as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->stock_quantity }} left</td>
                    </tr>
                @empty
                     <tr>
                        <td colspan='3' style='text-align:center; padding: 20px; color: #888;'>
                            No low stock items
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
                {{ $lowStockProducts->links('vendor.pagination.admin-compact') }}
    </div>
</div>
@endsection

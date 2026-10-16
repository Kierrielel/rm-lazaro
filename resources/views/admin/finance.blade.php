@extends('layouts.admin')

@section('title', 'Finance Report')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">

<div class="finance-page">

    <div class="finance-header">
        <h2>Finance Report</h2>
        <p class="subtitle">A quick look at your sales and revenue.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-main">
            <span class="stat-label">Total Revenue</span>
            <span class="stat-value">&#8369;{{ number_format($totalRevenue, 2) }}</span>
            <span class="stat-note">From completed orders</span>
        </div>

        <div class="stat-card">
            <span class="stat-label">Total Orders</span>
            <span class="stat-value">{{ $totalOrders }}</span>
            <span class="stat-note">All statuses</span>
        </div>

        <div class="stat-card">
            <span class="stat-label">Completed Orders</span>
            <span class="stat-value">{{ $completedOrders }}</span>
            <span class="stat-note">Successfully delivered</span>
        </div>

        <div class="stat-card">
            <span class="stat-label">Average Order Value</span>
            <span class="stat-value">&#8369;{{ number_format($averageOrderValue, 2) }}</span>
            <span class="stat-note">Per order</span>
        </div>
    </div>

    <div class="table-card">
        <div class="card-title">Revenue by Month</div>

        <table class="finance-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="amount-col">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($monthlyRevenue as $row)
                    <tr>
                        <td data-label="Month" class="month-name">{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                        <td data-label="Revenue" class="amount-col">&#8369;{{ number_format($row->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="empty-row">No revenue yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
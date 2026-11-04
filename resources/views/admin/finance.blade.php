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

    <form method="GET" action="{{ route('admin.finance') }}" class="finance-filters">
        <div class="finance-date-field">
            <label for="finance-start">From</label>
            <input type="date" id="finance-start" name="start_date" value="{{ $startDate }}">
        </div>
        <div class="finance-date-field">
            <label for="finance-end">To</label>
            <input type="date" id="finance-end" name="end_date" value="{{ $endDate }}">
        </div>
        <div class="finance-filter-actions">
            <button type="submit" class="btn finance-apply">Apply dates</button>
            <a href="{{ route('admin.finance') }}" class="btn finance-reset">All dates</a>
            <button type="submit" formaction="{{ route('admin.finance.export', ['format' => 'pdf']) }}" class="btn finance-export">Export PDF</button>
            <button type="submit" formaction="{{ route('admin.finance.export', ['format' => 'xlsx']) }}" class="btn finance-export">Export Excel</button>
        </div>
    </form>
    <p class="finance-period"><strong>{{ $periodLabel }}</strong> &middot; Filtered by order date. Both selected dates are included.</p>

    <div class="stats-grid">
        <div class="stat-card stat-main">
            <span class="stat-label">Total Revenue</span>
            <span class="stat-value">&#8369;{{ number_format($totalRevenue, 2) }}</span>
            <span class="stat-note">Completed order totals, excluding finance charges</span>
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
            <span class="stat-note">All statuses in the selected dates</span>
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
                        <td data-label="Month" class="month-name">{{ $row['label'] }}</td>
                        <td data-label="Revenue" class="amount-col">&#8369;{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="empty-row">No completed sales in the selected dates.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot><tr><th scope="row">Total</th><td class="amount-col">&#8369;{{ number_format($totalRevenue, 2) }}</td></tr></tfoot>
        </table>
    </div>
</div>
@endsection

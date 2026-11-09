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

    @php
        $salesTotalCents = array_sum(array_column($salesChart, 'total_cents'));
        $salesMaxCents = max(10000, ceil(max(array_column($salesChart, 'total_cents')) / 10000) * 10000);
        $salesPoints = collect($salesChart)->map(fn ($month, $index) => [
            'x' => 72 + $index * 104,
            'y' => 212 - ($month['total_cents'] / $salesMaxCents) * 172,
            'month' => $month,
        ]);
        $salesPolyline = $salesPoints->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
        $creditTotalCents = array_sum($creditChart);
        $creditSegments = [
            ['key' => 'paid', 'label' => 'Paid', 'color' => '#0b8064'],
            ['key' => 'current', 'label' => 'Current / upcoming', 'color' => '#2459b8'],
            ['key' => 'overdue', 'label' => 'Overdue', 'color' => '#ce443f'],
        ];
    @endphp

    <div class="dashboard-charts">
        <section class="dashboard-chart-card" aria-labelledby="sales-chart-heading">
            <div class="dashboard-chart-heading">
                <div>
                    <p class="dashboard-chart-eyebrow">Last 6 months</p>
                    <h3 id="sales-chart-heading">Completed sales trend</h3>
                </div>
                <strong class="dashboard-chart-total">&#8369;{{ number_format($salesTotalCents / 100, 2) }}</strong>
            </div>
            <p class="dashboard-chart-description">Completed order totals by order month. Current month is to date; finance charges are excluded.</p>
            <svg class="dashboard-sales-chart" viewBox="0 0 620 260" role="img" aria-labelledby="sales-chart-title sales-chart-desc">
                <title id="sales-chart-title">Completed sales over the last six months</title>
                <desc id="sales-chart-desc">Hover over or focus each point for its exact sales amount. Full values are available in View chart data.</desc>
                <defs>
                    <linearGradient id="dashboard-sales-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2459b8" stop-opacity="0.22" />
                        <stop offset="100%" stop-color="#2459b8" stop-opacity="0.02" />
                    </linearGradient>
                </defs>
                @foreach (range(0, 4) as $tick)
                    @php $tickY = 212 - $tick * 43; @endphp
                    <line x1="72" y1="{{ $tickY }}" x2="592" y2="{{ $tickY }}" class="dashboard-chart-gridline" />
                    <text x="62" y="{{ $tickY + 4 }}" text-anchor="end" class="dashboard-chart-axis">{{ number_format($salesMaxCents * $tick / 400, 0) }}</text>
                @endforeach
                <text x="10" y="19" class="dashboard-chart-axis">PHP</text>
                <polygon points="72,212 {{ $salesPolyline }} 592,212" fill="url(#dashboard-sales-fill)" />
                <polyline points="{{ $salesPolyline }}" class="dashboard-chart-line" />
                @foreach ($salesPoints as $point)
                    <text x="{{ $point['x'] }}" y="239" text-anchor="middle" class="dashboard-chart-axis">{{ $point['month']['short_label'] }}</text>
                    <g class="dashboard-chart-point" tabindex="0" role="img" aria-label="{{ $point['month']['label'] }}: PHP {{ number_format($point['month']['total_cents'] / 100, 2) }}">
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="6" />
                        @php
                            $tooltipX = max(8, min(452, $point['x'] - 80));
                            $tooltipY = max(2, $point['y'] - 45);
                        @endphp
                        <g class="dashboard-chart-tooltip" aria-hidden="true">
                            <rect x="{{ $tooltipX }}" y="{{ $tooltipY }}" width="160" height="32" rx="6" />
                            <text x="{{ $tooltipX + 9 }}" y="{{ $tooltipY + 21 }}">{{ $point['month']['label'] }}: &#8369;{{ number_format($point['month']['total_cents'] / 100, 2) }}</text>
                        </g>
                    </g>
                @endforeach
            </svg>
            @if ($salesTotalCents === 0)
                <p class="dashboard-chart-empty">No completed orders in this period.</p>
            @endif
            <details class="dashboard-chart-data">
                <summary>View chart data</summary>
                <table>
                    <caption class="dashboard-sr-only">Completed sales by month</caption>
                    <thead><tr><th scope="col">Month</th><th scope="col">Sales</th></tr></thead>
                    <tbody>
                        @foreach ($salesChart as $month)
                            <tr><th scope="row">{{ $month['label'] }}</th><td>&#8369;{{ number_format($month['total_cents'] / 100, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <section class="dashboard-chart-card" aria-labelledby="credit-chart-heading">
            <div class="dashboard-chart-heading">
                <div>
                    <p class="dashboard-chart-eyebrow">All Pay Later installments</p>
                    <h3 id="credit-chart-heading">Pay Later repayments</h3>
                </div>
                <span class="dashboard-chart-badge">Includes finance charges</span>
            </div>
            <p class="dashboard-chart-description">Recorded payments and remaining balances. Overdue means unpaid after the due date. Cancelled orders are excluded.</p>
            <div class="dashboard-credit-chart-layout">
                <svg class="dashboard-credit-chart" viewBox="0 0 220 220" role="img" aria-labelledby="credit-chart-title credit-chart-desc">
                    <title id="credit-chart-title">Pay Later amounts paid, current or upcoming, and overdue</title>
                    <desc id="credit-chart-desc">Total scheduled: PHP {{ number_format($creditTotalCents / 100, 2) }}. Exact amounts appear in the legend.</desc>
                    <circle cx="110" cy="110" r="78" fill="none" stroke="#edf1f6" stroke-width="24" />
                    @php $creditOffset = 0; $circumference = 2 * pi() * 78; @endphp
                    @foreach ($creditSegments as $segment)
                        @php
                            $segmentLength = $creditTotalCents > 0 ? $creditChart[$segment['key']] / $creditTotalCents * $circumference : 0;
                        @endphp
                        @if ($segmentLength > 0)
                            <circle class="dashboard-credit-segment" cx="110" cy="110" r="78" fill="none" stroke="{{ $segment['color'] }}" stroke-width="24"
                                stroke-dasharray="{{ $segmentLength }} {{ $circumference }}" stroke-dashoffset="{{ -$creditOffset }}" transform="rotate(-90 110 110)"
                                tabindex="0" role="img" aria-label="{{ $segment['label'] }}: PHP {{ number_format($creditChart[$segment['key']] / 100, 2) }}">
                                <title>{{ $segment['label'] }}: &#8369;{{ number_format($creditChart[$segment['key']] / 100, 2) }}</title>
                            </circle>
                        @endif
                        @php $creditOffset += $segmentLength; @endphp
                    @endforeach
                    <text x="110" y="103" text-anchor="middle" class="dashboard-donut-label">Total scheduled</text>
                    <text x="110" y="128" text-anchor="middle" class="dashboard-donut-total">&#8369;{{ number_format($creditTotalCents / 100, 2) }}</text>
                </svg>
                <ul class="dashboard-chart-legend">
                    @foreach ($creditSegments as $segment)
                        <li>
                            <span class="dashboard-legend-dot" style="background:{{ $segment['color'] }}" aria-hidden="true"></span>
                            <div><span>{{ $segment['label'] }}</span><strong>&#8369;{{ number_format($creditChart[$segment['key']] / 100, 2) }}</strong></div>
                            <span class="dashboard-legend-percent">{{ $creditTotalCents > 0 ? number_format($creditChart[$segment['key']] / $creditTotalCents * 100, 1) : '0.0' }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($creditTotalCents === 0)
                <p class="dashboard-chart-empty">No Pay Later installments yet.</p>
            @endif
        </section>
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
                                {{ $order->status_label }}
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
                        <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
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

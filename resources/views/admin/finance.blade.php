@extends('layouts.admin')

@section('title', 'Finance Report')

@section('content')
<h2>Finance Report</h2>

<div class="stats-grid">
    <div class="stat-card">
        <h4>Total Revenue (Completed Orders)</h4>
        <p>&#8369;{{ number_format($totalRevenue, 2) }}</p>
    </div>
    <div class="stat-card">
        <h4>Total Orders</h4>
        <p>{{ $totalOrders }}</p>
    </div>
    <div class="stat-card">
        <h4>Completed Orders</h4>
        <p>{{ $completedOrders }}</p>
    </div>
    <div class="stat-card">
        <h4>Average Order Value</h4>
        <p>&#8369;{{ number_format($averageOrderValue, 2) }}</p>
    </div>
</div>

<h3>Revenue by Month</h3>
<table>
    <thead>
        <tr>
            <th>Month</th>
            <th>Revenue</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($monthlyRevenue as $row)
            <tr>
                <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                <td>&#8369;{{ number_format($row->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection

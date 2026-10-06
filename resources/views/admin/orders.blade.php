@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<h2>Orders</h2>

<form method="GET" action="{{ route('admin.orders') }}" class="filter-form">
    <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach (['pending', 'processing', 'completed', 'cancelled'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</form>

<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orders as $order)
            <tr>
                <td>#{{ $order->id }}</td>
                <td>{{ $order->user->username ?? 'Deleted user' }}</td>
                <td>&#8369;{{ number_format($order->total_amount, 2) }}</td>
                <td>{{ ucfirst($order->order_status) }}</td>
                <td>{{ $order->order_date->format('M d, Y') }}</td>
                <td><a href="{{ route('admin.orders.show', $order) }}">View</a></td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $orders->links() }}
@endsection

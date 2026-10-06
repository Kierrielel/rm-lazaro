@extends('layouts.admin')

@section('title', 'Order #' . $order->id)

@section('content')
<h2>Order #{{ $order->id }}</h2>

<p><strong>Customer:</strong> {{ $order->user->username ?? 'Deleted user' }} ({{ $order->user->email ?? '—' }})</p>
<p><strong>Date:</strong> {{ $order->order_date->format('M d, Y g:i A') }}</p>

<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product->name ?? 'Deleted product' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>&#8369;{{ number_format($item->price, 2) }}</td>
                <td>&#8369;{{ number_format($item->price * $item->quantity, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">Total</td>
            <td>&#8369;{{ number_format($order->total_amount, 2) }}</td>
        </tr>
    </tfoot>
</table>

<form method="POST" action="{{ route('admin.orders.update', $order) }}" class="status-form">
    @csrf
    @method('PATCH')
    <label for="order_status">Status</label>
    <select name="order_status" id="order_status">
        @foreach (['pending', 'processing', 'completed', 'cancelled'] as $status)
            <option value="{{ $status }}" @selected($order->order_status === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <button type="submit">Update Status</button>
</form>
@endsection

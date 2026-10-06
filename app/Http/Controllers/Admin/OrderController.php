<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('user');

        if ($status = $request->get('status')) {
            $query->where('order_status', $status);
        }

        $orders = $query->latest('order_date')->paginate(15)->withQueryString();

        return view('admin.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('items.product', 'user');
        return view('admin.order_details', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'order_status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]);

        $order->update($data);

        return back()->with('success', 'Order status updated.');
    }
}

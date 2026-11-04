<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LiteralSearch;
use App\Models\Order;
use App\OrderStatusUpdater;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $sortOptions = [
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'total_desc' => 'Total: highest first',
            'total_asc' => 'Total: lowest first',
            'customer_asc' => 'Customer: A to Z',
            'customer_desc' => 'Customer: Z to A',
        ];
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,processing,completed,cancelled'],
            'sort' => ['nullable', Rule::in(array_keys($sortOptions))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');
        $selectedStatus = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'newest';
        $query = Order::query()->with('user');

        if ($selectedStatus) {
            $query->where('orders.order_status', $selectedStatus);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->whereHas('user', function ($query) use ($search) {
                    LiteralSearch::apply($query, ['username', 'name', 'email', 'phone_no'], $search);
                });
                $orderId = ltrim($search, '#');
                if (ctype_digit($orderId) && strlen($orderId) <= 18) {
                    $query->orWhere('orders.id', (int) $orderId);
                }
            });
        }

        $sortColumns = [
            'newest' => ['orders.order_date', 'desc'],
            'oldest' => ['orders.order_date', 'asc'],
            'total_desc' => ['orders.total_amount', 'desc'],
            'total_asc' => ['orders.total_amount', 'asc'],
            'customer_asc' => ['users.username', 'asc'],
            'customer_desc' => ['users.username', 'desc'],
        ];
        if (str_starts_with($sort, 'customer_')) {
            $query->leftJoin('users', 'users.id', '=', 'orders.user_id')->select('orders.*');
        }
        [$column, $direction] = $sortColumns[$sort];
        $orders = $query->orderBy($column, $direction)
            ->orderBy('orders.id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(15)->withQueryString();

        return view('admin.orders', compact('orders', 'search', 'selectedStatus', 'sort', 'sortOptions'));
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'user', 'installments.payments');

        return view('admin.order_details', compact('order'));
    }

    public function update(Request $request, Order $order, OrderStatusUpdater $updater): RedirectResponse
    {
        $data = $request->validate([
            'order_status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]);
        if (
            $data['order_status'] === 'cancelled'
            && $order->payment_method === 'paylater'
            && $order->payments()->exists()
        ) {
            return back()->withErrors([
                'order_status' => 'This Pay Later order has recorded payments. Refund them before cancelling the order.',
            ]);
        }

        $updater->update($order, $data['order_status']);

        return back()->with('success', 'Order status updated.');
    }
}

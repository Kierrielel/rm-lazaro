<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $statusOptions = Order::STATUS_LABELS;
        $paymentOptions = [
            'cod' => 'Cash on delivery',
            'paylater' => 'Pay Later',
            'qrph' => 'QR Ph',
            'gcash' => 'GCash',
            'maya' => 'Maya',
        ];
        $sortOptions = [
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'total_desc' => 'Order total: highest first',
            'total_asc' => 'Order total: lowest first',
        ];
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys($statusOptions))],
            'payment_method' => ['nullable', Rule::in(array_keys($paymentOptions))],
            'sort' => ['nullable', Rule::in(array_keys($sortOptions))],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $sort = $filters['sort'] ?? 'newest';
        $sortColumns = [
            'newest' => ['order_date', 'desc'],
            'oldest' => ['order_date', 'asc'],
            'total_desc' => ['total_amount', 'desc'],
            'total_asc' => ['total_amount', 'asc'],
        ];
        $query = $request->user()->orders()->with(['items.product', 'items.variant']);
        if (filled($filters['status'] ?? null)) {
            $query->where('order_status', $filters['status']);
        }
        if (filled($filters['payment_method'] ?? null)) {
            $query->where('payment_method', $filters['payment_method']);
        }
        [$column, $direction] = $sortColumns[$sort];
        $orders = $query->orderBy($column, $direction)
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(10)->appends($request->only('status', 'payment_method', 'sort'));

        return view('customer.accountOverview', [
            'user' => $request->user(),
            'orders' => $orders,
            'statusOptions' => $statusOptions,
            'paymentOptions' => $paymentOptions,
            'sortOptions' => $sortOptions,
            'selectedStatus' => $filters['status'] ?? '',
            'selectedPaymentMethod' => $filters['payment_method'] ?? '',
            'selectedSort' => $sort,
        ]);
    }
}

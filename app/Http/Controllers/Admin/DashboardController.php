<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditInstallment;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;

// use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalCustomers = User::where('role', 'customer')->count();

        $monthlyRevenue = Order::where('order_status', 'completed')
            ->whereMonth('order_date', now()->month)
            ->whereYear('order_date', now()->year)
            ->sum('total_amount');

        $totalOrders = Order::count();
        $averageOrderValue = Order::avg('total_amount') ?? 0;

        $recentOrders = Order::with('user')->latest('order_date')->take(5)->get();

        $lowStockProducts = Product::with('category')
            ->where('stock_quantity', '<=', StoreSetting::current()->low_stock_threshold)
            ->orderBy('stock_quantity')
            ->paginate(5);

        $chartNow = now();
        $salesByMonth = Order::query()
            ->where('order_status', 'completed')
            ->whereBetween('order_date', [$chartNow->copy()->startOfMonth()->subMonths(5), $chartNow])
            ->get(['order_date', 'total_amount'])
            ->groupBy(fn (Order $order) => $order->order_date->format('Y-m'))
            ->map(fn ($orders) => $orders->sum(fn (Order $order) => (int) round((float) $order->total_amount * 100)));

        $salesChart = collect(range(5, 0))->map(function (int $monthsAgo) use ($chartNow, $salesByMonth) {
            $month = $chartNow->copy()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->format('M Y'),
                'short_label' => $month->format('M'),
                'total_cents' => (int) $salesByMonth->get($month->format('Y-m'), 0),
            ];
        })->all();

        $creditChart = ['paid' => 0, 'current' => 0, 'overdue' => 0];
        CreditInstallment::query()
            ->whereHas('order', fn ($query) => $query->where('payment_method', 'paylater')->where('order_status', '!=', 'cancelled'))
            ->withSum('payments', 'amount')
            ->each(function (CreditInstallment $installment) use (&$creditChart) {
                $dueCents = max(0, (int) round((float) $installment->amount_due * 100));
                $paidCents = min($dueCents, max(0, (int) round((float) $installment->payments_sum_amount * 100)));
                $creditChart['paid'] += $paidCents;
                $balanceType = $installment->due_date->isBefore(today()) ? 'overdue' : 'current';
                $creditChart[$balanceType] += $dueCents - $paidCents;
            });

        return view('admin.dashboard', compact(
            'totalCustomers', 'monthlyRevenue', 'totalOrders', 'averageOrderValue', 'recentOrders', 'lowStockProducts',
            'salesChart', 'creditChart'
        ));
    }
}

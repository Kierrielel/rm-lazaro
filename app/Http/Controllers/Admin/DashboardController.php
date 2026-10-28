<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
//use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
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
            ->where('stock_quantity', '<=', 20)
            ->orderBy('stock_quantity')
            ->paginate(5);

        return view('admin.dashboard', compact(
            'totalCustomers', 'monthlyRevenue', 'totalOrders', 'averageOrderValue', 'recentOrders', 'lowStockProducts'
        ));
    }
}

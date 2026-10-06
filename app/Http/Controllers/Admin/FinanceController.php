<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function index()
    {
        $monthlyRevenue = Order::where('order_status', 'completed')
            ->selectRaw("strftime('%Y-%m', order_date) as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->take(12)
            ->get();

        $totalRevenue = Order::where('order_status', 'completed')->sum('total_amount');
        $totalOrders = Order::count();
        $completedOrders = Order::where('order_status', 'completed')->count();
        $averageOrderValue = Order::avg('total_amount') ?? 0;

        return view('admin.finance', compact(
            'monthlyRevenue',
            'totalRevenue',
            'totalOrders',
            'completedOrders',
            'averageOrderValue'
        ));
    }

    // To add PDF export like the original mPDF-based report:
    // composer require mpdf/mpdf
    // then build the PDF from the same $monthlyRevenue data here and
    // return it with ->download('finance-report.pdf') instead of a view.
}

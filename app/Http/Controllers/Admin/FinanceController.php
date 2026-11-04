<?php

namespace App\Http\Controllers\Admin;

use App\FinanceReportExports;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance', $this->report($request));
    }

    public function export(Request $request, string $format, FinanceReportExports $exports): StreamedResponse
    {
        $report = $this->report($request);
        $content = $format === 'pdf' ? $exports->pdf($report) : $exports->excel($report);
        $period = ($report['startDate'] ?? 'all').'_to_'.($report['endDate'] ?? 'all');

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'finance-report_'.$period.'.'.$format, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function report(Request $request): array
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
        ]);
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $query = Order::query();
        if ($startDate) {
            $query->where('order_date', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->where('order_date', '<', Carbon::parse($endDate)->addDay()->startOfDay());
        }
        $completed = (clone $query)->where('order_status', 'completed');
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', order_date)"
            : "DATE_FORMAT(order_date, '%Y-%m')";

        $monthlyRevenue = (clone $completed)
            ->selectRaw("{$monthExpression} as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()->map(fn (Order $row) => [
                'month' => $row->month,
                'label' => Carbon::createFromFormat('!Y-m', $row->month)->format('F Y'),
                'total' => (float) $row->total,
            ]);

        $totalRevenue = (float) (clone $completed)->sum('total_amount');
        $totalOrders = (clone $query)->count();
        $completedOrders = (clone $completed)->count();
        $averageOrderValue = (float) ((clone $query)->avg('total_amount') ?? 0);
        $periodLabel = match (true) {
            $startDate !== null && $endDate !== null => Carbon::parse($startDate)->format('M j, Y').' to '.Carbon::parse($endDate)->format('M j, Y'),
            $startDate !== null => 'From '.Carbon::parse($startDate)->format('M j, Y'),
            $endDate !== null => 'Through '.Carbon::parse($endDate)->format('M j, Y'),
            default => 'All dates',
        };
        $generatedAt = now();
        $storeName = StoreSetting::current()->store_name;

        return compact(
            'monthlyRevenue',
            'totalRevenue',
            'totalOrders',
            'completedOrders',
            'averageOrderValue', 'startDate', 'endDate', 'periodLabel', 'generatedAt', 'storeName'
        );
    }
}

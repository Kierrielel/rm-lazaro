<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Report</title>
    <style>
        @page { margin: 35px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #24344b; }
        h1 { font-size: 20px; color: #173d7a; margin: 0 0 6px; }
        h2 { font-size: 15px; margin: 6px 0; }
        p { margin: 6px 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 18px; }
        th, td { padding: 10px; border-bottom: 1px solid #dce3ec; text-align: left; }
        thead th { background: #edf2fa; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .amount { text-align: right; }
        .note { color: #617187; font-size: 9px; line-height: 1.6; }
        .total { font-weight: bold; background: #edf2fa; }
    </style>
</head>
<body>
    <h1>{{ $storeName }}</h1>
    <h2>Finance Report</h2>
    <p><strong>Period:</strong> {{ $periodLabel }}</p>
    <p class="note">Generated {{ $generatedAt->format('M j, Y g:i A') }} ({{ config('app.timezone') }})</p>
    <p class="note">Filtered by order date, including both selected dates. Sales are completed order totals excluding finance charges. Pay Later repayments are tracked separately.</p>
    <table>
        <thead><tr><th>Summary</th><th class="amount">Value</th></tr></thead>
        <tbody>
            <tr><td>Completed sales</td><td class="amount">&#8369;{{ number_format($totalRevenue, 2) }}</td></tr>
            <tr><td>Total orders (all statuses)</td><td class="amount">{{ $totalOrders }}</td></tr>
            <tr><td>Completed orders</td><td class="amount">{{ $completedOrders }}</td></tr>
            <tr><td>Average order value (all statuses)</td><td class="amount">&#8369;{{ number_format($averageOrderValue, 2) }}</td></tr>
        </tbody>
    </table>
    <h2 style="margin-top:24px">Sales by month</h2>
    <table>
        <thead><tr><th>Month</th><th class="amount">Completed sales (PHP)</th></tr></thead>
        <tbody>
            @forelse ($monthlyRevenue as $row)
                <tr><td>{{ $row['label'] }}</td><td class="amount">&#8369;{{ number_format($row['total'], 2) }}</td></tr>
            @empty
                <tr><td colspan="2">No completed sales in the selected dates.</td></tr>
            @endforelse
            <tr class="total"><td>Total</td><td class="amount">&#8369;{{ number_format($totalRevenue, 2) }}</td></tr>
        </tbody>
    </table>
</body>
</html>

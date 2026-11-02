<?php

namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;
use Shuchkin\SimpleXLSXGen;

class FinanceReportExports
{
    /** @param array<string, mixed> $report */
    public function pdf(array $report): string
    {
        $pdf = new Dompdf(new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'tempDir' => storage_path('framework/cache'),
            'fontCache' => storage_path('framework/cache'),
        ]));
        $pdf->loadHtml(view('admin.finance_pdf', $report)->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    /** @param array<string, mixed> $report */
    public function excel(array $report): string
    {
        $summary = [
            ["\0".$report['storeName'], null], ['Finance Report', null],
            ['Period', "\0".$report['periodLabel']],
            ['Generated', "\0".$report['generatedAt']->format('Y-m-d H:i:s')],
            ['Completed sales (PHP)', $this->money($report['totalRevenue'])],
            ['Total orders (all statuses)', $report['totalOrders']],
            ['Completed orders', $report['completedOrders']],
            ['Average order value (PHP, all statuses)', $this->money($report['averageOrderValue'])],
            ['Basis', 'Filtered by order date. Sales use completed order totals and exclude finance charges.'],
        ];
        $monthly = [['Month', 'Completed sales (PHP)']];
        foreach ($report['monthlyRevenue'] as $row) {
            $monthly[] = ["\0".$row['label'], $this->money($row['total'])];
        }
        $monthly[] = ['Total', $this->money($report['totalRevenue'])];
        $workbook = SimpleXLSXGen::fromArray($summary, 'Summary')->setColWidth(1, 46)->setColWidth(2, 85);
        $workbook->addSheet($monthly, 'Monthly Sales')->setColWidth(1, 28)->setColWidth(2, 28);

        return (string) $workbook;
    }

    private function money(float $amount): string
    {
        return '<style nf="#,##0.00">'.number_format($amount, 2, '.', '').'</style>';
    }
}

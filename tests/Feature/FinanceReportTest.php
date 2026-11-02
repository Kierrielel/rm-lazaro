<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PharData;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleXMLElement;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_all_summary_and_monthly_values_use_the_inclusive_selected_dates(): void
    {
        $admin = $this->createUser();
        $this->createOrder($admin, '2026-10-31 23:59:59', 900);
        $this->createOrder($admin, '2026-11-01 00:00:00', 100);
        $this->createOrder($admin, '2026-11-30 23:59:59', 200);
        $this->createOrder($admin, '2026-12-01 00:00:00', 900);
        $this->createOrder($admin, '2026-11-10', 50, 'pending');
        $this->createOrder($admin, '2026-11-20', 25, 'cancelled');

        $response = $this->actingAs($admin)->get(route('admin.finance', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30']));

        $response->assertOk()->assertViewHas('totalRevenue', 300.0)->assertViewHas('totalOrders', 4)
            ->assertViewHas('completedOrders', 2)->assertViewHas('averageOrderValue', 93.75);
        $response->assertViewHas('monthlyRevenue', fn ($rows) => $rows->all() === [['month' => '2026-11', 'label' => 'November 2026', 'total' => 300.0]]);
        $response->assertSee('Export PDF')->assertSee('Export Excel')->assertSee('2026-11-01');
    }

    #[DataProvider('dateRanges')]
    public function test_single_sided_same_day_and_all_date_ranges(array $dates, float $expectedTotal): void
    {
        $admin = $this->createUser();
        $this->createOrder($admin, '2026-10-31', 10);
        $this->createOrder($admin, '2026-11-15 23:59:59', 20);
        $this->createOrder($admin, '2026-12-01', 30);

        $this->actingAs($admin)->get(route('admin.finance', $dates))->assertOk()->assertViewHas('totalRevenue', $expectedTotal);
    }

    public static function dateRanges(): array
    {
        return [
            'same day' => [['start_date' => '2026-11-15', 'end_date' => '2026-11-15'], 20.0],
            'from only' => [['start_date' => '2026-11-01'], 50.0],
            'to only' => [['end_date' => '2026-11-30'], 30.0],
            'all dates' => [[], 60.0],
            'blank dates' => [['start_date' => '', 'end_date' => ''], 60.0],
        ];
    }

    public function test_selected_range_is_not_silently_truncated_to_twelve_months(): void
    {
        $admin = $this->createUser();
        foreach (range(0, 13) as $offset) {
            $this->createOrder($admin, Carbon::parse('2025-01-01')->addMonths($offset)->toDateString(), 100);
        }

        $response = $this->actingAs($admin)->get(route('admin.finance', ['start_date' => '2025-01-01', 'end_date' => '2026-02-28']));

        $response->assertOk()->assertViewHas('totalRevenue', 1400.0)->assertViewHas('monthlyRevenue', fn ($rows) => $rows->count() === 14);
    }

    public function test_pdf_export_downloads_a_real_pdf_file(): void
    {
        $this->freezeTime();
        $admin = $this->createUser();
        $this->createOrder($admin, '2026-11-10', 100);

        $response = $this->actingAs($admin)->get(route('admin.finance.export', ['format' => 'pdf', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('finance-report_2026-11-01_to_2026-11-30.pdf');
        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', trim($pdf));
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_excel_export_contains_the_filtered_totals_and_two_worksheets(): void
    {
        $this->freezeTime();
        $admin = $this->createUser();
        $this->createOrder($admin, '2026-11-01', 100);
        $this->createOrder($admin, '2026-10-31', 999);
        $this->createOrder($admin, '2026-11-30 23:59:59', 50, 'pending');

        $response = $this->actingAs($admin)->get(route('admin.finance.export', ['format' => 'xlsx', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']));

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('finance-report_2026-11-01_to_2026-11-30.xlsx');
        $files = $this->workbookXml($response->streamedContent());
        $this->assertStringContainsString('name="Summary"', $files['workbook']);
        $this->assertStringContainsString('name="Monthly Sales"', $files['workbook']);
        $this->assertSame(100.0, $this->numericCell($files['summary'], 'B5'));
        $this->assertSame(2.0, $this->numericCell($files['summary'], 'B6'));
        $this->assertSame(1.0, $this->numericCell($files['summary'], 'B7'));
        $this->assertSame(75.0, $this->numericCell($files['summary'], 'B8'));
        $this->assertSame(100.0, $this->numericCell($files['monthly'], 'B2'));
        $this->assertSame(100.0, $this->numericCell($files['monthly'], 'B3'));
    }

    #[DataProvider('exportFormats')]
    public function test_empty_report_can_be_exported(string $format): void
    {
        $this->freezeTime();

        $response = $this->actingAs($this->createUser())->get(route('admin.finance.export', ['format' => $format, 'start_date' => '2090-01-01']));

        $response->assertOk()->assertDownload('finance-report_2090-01-01_to_all.'.$format);
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_empty_report_page_has_zero_values(): void
    {
        $response = $this->actingAs($this->createUser())->get(route('admin.finance'));

        $response->assertOk()->assertSee('No completed sales in the selected dates.')
            ->assertViewHas('totalRevenue', 0.0)->assertViewHas('totalOrders', 0)->assertViewHas('averageOrderValue', 0.0);
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_are_rejected_on_screen_and_both_exports(array $dates, string $field): void
    {
        $this->actingAs($this->createUser());

        $this->getJson(route('admin.finance', $dates))->assertUnprocessable()->assertJsonValidationErrors($field);
        foreach (['pdf', 'xlsx'] as $format) {
            $this->getJson(route('admin.finance.export', ['format' => $format, ...$dates]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public static function invalidDates(): array
    {
        return [
            'invalid calendar date' => [['start_date' => '2026-02-30'], 'start_date'],
            'reversed range' => [['start_date' => '2026-11-30', 'end_date' => '2026-11-01'], 'end_date'],
            'array date' => [['start_date' => ['2026-11-01']], 'start_date'],
            'invalid format' => [['end_date' => 'tomorrow'], 'end_date'],
        ];
    }

    #[DataProvider('exportFormats')]
    public function test_customer_cannot_export_reports(string $format): void
    {
        $this->actingAs($this->createUser('customer'))->get(route('admin.finance.export', ['format' => $format]))->assertForbidden();
    }

    #[DataProvider('exportFormats')]
    public function test_guest_cannot_export_reports(string $format): void
    {
        $this->get(route('admin.finance.export', ['format' => $format]))->assertRedirect(route('login'));
    }

    public static function exportFormats(): array
    {
        return ['pdf' => ['pdf'], 'excel' => ['xlsx']];
    }

    public function test_unsupported_export_formats_are_not_routed(): void
    {
        $this->actingAs($this->createUser())->get('/admin/finance/export/csv')->assertNotFound();
    }

    private function createUser(string $role = 'admin'): User
    {
        $user = User::factory()->make(['username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'), 'role' => $role]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }

    private function createOrder(User $user, string $date, float $amount, string $status = 'completed'): Order
    {
        return $user->orders()->create(['order_date' => $date, 'total_amount' => $amount, 'order_status' => $status, 'payment_method' => 'cod']);
    }

    /** @return array{workbook: string, summary: string, monthly: string} */
    private function workbookXml(string $content): array
    {
        $this->assertStringStartsWith("PK\x03\x04", $content);
        $temporary = tempnam(sys_get_temp_dir(), 'finance_');
        $path = $temporary.'.zip';
        rename($temporary, $path);
        file_put_contents($path, $content);
        try {
            $archive = new PharData($path);

            return [
                'workbook' => $archive['xl/workbook.xml']->getContent(),
                'summary' => $archive['xl/worksheets/sheet1.xml']->getContent(),
                'monthly' => $archive['xl/worksheets/sheet2.xml']->getContent(),
            ];
        } finally {
            unset($archive);
            unlink($path);
        }
    }

    private function numericCell(string $xml, string $cell): float
    {
        $sheet = new SimpleXMLElement($xml, LIBXML_NONET);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = $sheet->xpath('//s:c[@r="'.$cell.'"]/s:v');
        $this->assertCount(1, $values);

        return (float) $values[0];
    }
}

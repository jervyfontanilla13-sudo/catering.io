<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summaries_use_calendar_period_boundaries_and_live_database_values(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);

        $this->createReservation('2026-09-29 23:59:59', 'confirmed', 20134);
        $this->createReservation('2026-09-28 00:00:00', 'completed', 9000);
        $this->createReservation('2026-09-01 00:00:00', 'cancelled', 1000);
        $this->createReservation('2026-01-01 00:00:00', 'pending', 4000);
        $this->createReservation('2026-08-31 23:59:59', 'confirmed', 5000);

        foreach (['2026-09-29 10:00:00', '2026-09-28 10:00:00', '2026-09-01 10:00:00', '2026-01-01 10:00:00', '2026-08-31 10:00:00'] as $createdAt) {
            Inquiry::unguarded(fn () => Inquiry::create([
                'full_name' => 'Report Client',
                'contact_number' => '09171234567',
                'email' => 'report@example.com',
                'subject' => 'Report inquiry',
                'category' => 'Catering',
                'message' => 'Please send a report.',
                'status' => 'new',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        $service = app(ReportService::class);

        $this->assertSame(1, $service->getSummary('daily')['reservation_count']);
        $this->assertSame(2, $service->getSummary('weekly')['reservation_count']);
        $this->assertSame(3, $service->getSummary('monthly')['reservation_count']);
        $this->assertSame(5, $service->getSummary('yearly')['reservation_count']);
        $this->assertArrayNotHasKey('estimated_revenue', $service->getSummary('daily'));
        $this->assertSame(2, $service->getSummary('weekly')['inquiry_count']);
    }

    public function test_report_page_and_csv_and_excel_downloads_use_the_live_period_summaries(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);
        $this->createReservation('2026-09-29 09:00:00', 'confirmed', 20134);
        $this->createReservation('2026-09-28 12:00:00', 'completed', 5000);
        $this->createReservation('2026-09-01 08:00:00', 'cancelled', 1000);
        $this->createReservation('2026-01-01 08:00:00', 'pending', 4000);
        $this->createReservation('2026-08-31 23:59:59', 'confirmed', 7000);

        $session = ['is_admin' => true, 'admin_role' => 'full'];
        $page = $this->withSession($session)->get(route('admin.reports'));
        $page->assertOk();
        $page->assertSee('Today · Sep 29, 2026');
        $page->assertSee('Download Excel');
        $page->assertSee('Download CSV');
        $page->assertSee('Contract value');
        $page->assertDontSee('Estimated Revenue');
        $page->assertDontSee('estimated revenue');
        $page->assertSee('This Week · Sep 28 – Oct 4, 2026');
        $page->assertSee('Bookings created in period');
        $page->assertSee('Transactions in period');
        $page->assertDontSee('(bookings created in period)');
        $page->assertDontSee('(transactions in period)');
        $this->assertSame(4, substr_count($page->getContent(), '>Bookings created in period</h3>'));
        $this->assertSame(4, substr_count($page->getContent(), '>Transactions in period</h3>'));
        $page->assertDontSee('<dt>Refunded</dt>', false);
        $page->assertSee('<dt>Refunds</dt><dd class="report-refunded">₱0.00</dd>', false);

        $periods = [
            'daily' => ['count' => 1, 'filename' => '2026-09-29'],
            'weekly' => ['count' => 2, 'filename' => '2026-09-29'],
            'monthly' => ['count' => 3, 'filename' => '2026-09'],
            'yearly' => ['count' => 5, 'filename' => '2026'],
        ];

        foreach ($periods as $period => $expected) {
            $csv = $this->withSession($session)->get(route('admin.reports.export', ['period' => $period]));
            $csv->assertOk();
            $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $csv->assertHeader('Content-Disposition', 'attachment; filename="3YOS-Catering-'.ucfirst($period).'-Report-'.$expected['filename'].'.csv"');
            $csv->assertSee('Reservations,'.$expected['count']);
            $csv->assertDontSee('Estimated Revenue');
            $csv->assertDontSee('Refunded (Bookings Created in Period)');

            $excel = $this->withSession($session)->get(route('admin.reports.export.excel', ['period' => $period]));
            $excel->assertDownload('3YOS-Catering-'.ucfirst($period).'-Report-'.$expected['filename'].'.xlsx');

            $archive = new \ZipArchive();
            $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $styles = $archive->getFromName('xl/styles.xml');
            $workbook = $archive->getFromName('xl/workbook.xml');
            $this->assertNotFalse($sheet);
            $this->assertNotFalse($styles);
            $this->assertNotFalse($workbook);
            $this->assertStringNotContainsString('Estimated Revenue', $sheet);
            $this->assertStringNotContainsString('Refunded (Bookings Created in Period)', $sheet);
            $this->assertStringContainsString('3YOS CATERING', $sheet);
            $this->assertStringContainsString('pane', $sheet);
            $this->assertStringContainsString('Catering Management System', $sheet);
            $this->assertStringContainsString('REPORT SUMMARY', $sheet);
            $this->assertStringContainsString('FINANCIAL SUMMARY', $sheet);
            $this->assertStringContainsString('numFmt', $styles);
            $this->assertStringContainsString(ucfirst($period).' Report', $workbook);
            $this->assertStringContainsString('fitToWidth="1"', $sheet);
            $archive->close();

            if ($period === 'daily') {
                $this->assertSame('1', $this->excelCellValue($sheet, 'B9'));
                $this->assertSame('1', $this->excelCellValue($sheet, 'B10'));
                $this->assertSame('0', $this->excelCellValue($sheet, 'B13'));
            }
        }
    }

    public function test_empty_daily_excel_report_has_a_complete_zero_value_summary(): void
    {
        Carbon::setTestNow('2026-10-03 15:45:00');
        config(['app.timezone' => 'Asia/Manila']);

        Inquiry::unguarded(fn () => Inquiry::create([
            'full_name' => 'Report Client',
            'contact_number' => '09171234567',
            'email' => 'report@example.com',
            'subject' => 'Daily report inquiry',
            'category' => 'Catering',
            'message' => 'Please send a report.',
            'status' => 'new',
            'created_at' => '2026-10-03 10:00:00',
            'updated_at' => '2026-10-03 10:00:00',
        ]));

        $excel = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.reports.export.excel', ['period' => 'daily']));
        $excel->assertDownload('3YOS-Catering-Daily-Report-2026-10-03.xlsx');

        $archive = new \ZipArchive();
        $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('DAILY BUSINESS REPORT', $sheet);
        $this->assertStringContainsString('October 3, 2026', $sheet);
        $this->assertStringContainsString('October 3, 2026 3:45 PM PST', $sheet);
        $this->assertStringContainsString('Reservations', $sheet);
        $this->assertStringNotContainsString('Estimated Revenue', $sheet);
        $this->assertStringContainsString('FINANCIAL SUMMARY', $sheet);
        $this->assertSame('0', $this->excelCellValue($sheet, 'B9'));
        $this->assertSame('0', $this->excelCellValue($sheet, 'B10'));
        $this->assertSame('0', $this->excelCellValue($sheet, 'B11'));
        $this->assertSame('0', $this->excelCellValue($sheet, 'B12'));
        $this->assertSame('1', $this->excelCellValue($sheet, 'B13'));
        $this->assertStringContainsString('pageMargins', $sheet);
        $this->assertStringContainsString('pageSetup', $sheet);
        $archive->close();
    }

    public function test_reports_separate_booking_financials_from_payment_and_refund_transactions(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);

        $reservation = $this->createReservation('2026-09-29 09:00:00', 'confirmed', 1000);
        $reservation->update(['total_cost' => 1000]);
        $session = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Report Tester'];

        $this->withSession($session)->post(route('admin.reservations.payments.store', $reservation), [
            'payment_date' => '2026-09-29',
            'payment_type' => 'Downpayment',
            'amount' => 800,
            'payment_method' => 'Cash',
        ])->assertRedirect();

        $this->withSession($session)->post(route('admin.reservations.refunds.store', $reservation), [
            'request_key' => (string) Str::uuid(),
            'refund_date' => '2026-09-29',
            'refund_amount' => 300,
            'refund_method' => 'Cash',
        ])->assertRedirect();

        $this->assertSame('2026-09-29', $reservation->payments()->firstOrFail()->payment_date->toDateString());
        $this->assertSame('2026-09-29', $reservation->refunds()->firstOrFail()->refund_date->toDateString());
        $summary = app(ReportService::class)->getSummary('daily');
        $this->assertSame('2026-09-29', $summary['period_start']->toDateString());
        $this->assertEquals(1000.0, $summary['contract_value']);
        $this->assertEquals(800.0, $summary['gross_paid']);
        $this->assertEquals(300.0, $summary['total_refunded']);
        $this->assertEquals(500.0, $summary['net_paid']);
        $this->assertEquals(500.0, $summary['outstanding_balance']);
        $this->assertEquals(800.0, $summary['gross_payments_in_period']);
        $this->assertEquals(300.0, $summary['refunds_in_period']);
        $this->assertEquals(500.0, $summary['net_collected_in_period']);

        $page = $this->withSession($session)->get(route('admin.reports'));
        $page->assertOk()
            ->assertSee('Payments, refunds & balances')
            ->assertSee('Gross paid')
            ->assertDontSee('<dt>Refunded</dt>', false)
            ->assertSee('<dt>Refunds</dt>', false)
            ->assertSee('−₱300.00')
            ->assertSee('₱500.00');

        $csv = $this->withSession($session)->get(route('admin.reports.export', ['period' => 'daily']));
        $csv->assertOk()
            ->assertSee('"Gross Paid (Bookings Created in Period)",800', false)
            ->assertDontSee('"Refunded (Bookings Created in Period)"', false)
            ->assertSee('"Net Collected (Transactions in Period)",500', false);

        $excel = $this->withSession($session)->get(route('admin.reports.export.excel', ['period' => 'daily']));
        $excel->assertDownload();
        $archive = new \ZipArchive();
        $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('Gross Paid (Bookings Created in Period)', $sheet);
        $this->assertStringContainsString('Refunds (Transactions in Period)', $sheet);
        $this->assertStringNotContainsString('Refunded (Bookings Created in Period)', $sheet);
        $this->assertStringContainsString('500', $sheet);
        $this->assertSame('1000', $this->excelCellValue($sheet, 'B17'));
        $this->assertSame('800', $this->excelCellValue($sheet, 'B18'));
        $this->assertSame('500', $this->excelCellValue($sheet, 'B19'));
        $this->assertSame('500', $this->excelCellValue($sheet, 'B20'));
        $this->assertSame('800', $this->excelCellValue($sheet, 'B21'));
        $this->assertSame('300', $this->excelCellValue($sheet, 'B22'));
        $this->assertSame('500', $this->excelCellValue($sheet, 'B23'));
        $archive->close();
    }

    public function test_report_keeps_exact_cents_for_the_reported_gross_refund_and_net_values(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);
        $reservation = $this->createReservation('2026-09-29 09:00:00', 'confirmed', 1000);
        $reservation->update(['total_cost' => 741000]);
        $this->recordPayment($reservation, '2026-09-29', '700000.00');
        $this->recordPayment($reservation, '2026-09-29', '31011.01');
        $this->recordRefund($reservation, '2026-09-29', '54001.00');

        $periods = ['daily', 'weekly', 'monthly', 'yearly'];
        foreach ($periods as $period) {
            $summary = app(ReportService::class)->getSummary($period);

            $this->assertSame(73101101, (int) round($summary['gross_paid'] * 100));
            $this->assertSame(5400100, (int) round($summary['total_refunded'] * 100));
            $this->assertSame(67701001, (int) round($summary['net_paid'] * 100));
            $this->assertSame(
                (int) round($summary['gross_paid'] * 100) - (int) round($summary['total_refunded'] * 100),
                (int) round($summary['net_paid'] * 100),
            );

            $excel = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
                ->get(route('admin.reports.export.excel', ['period' => $period]));
            $excel->assertDownload();
            $archive = new \ZipArchive();
            $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse($sheet);
            $this->assertSame('731011.01', $this->excelCellValue($sheet, 'B18'));
            $this->assertSame('677010.01', $this->excelCellValue($sheet, 'B19'));
            $archive->close();
        }

        $page = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])->get(route('admin.reports'));
        $page->assertOk()
            ->assertSee('₱731,011.01')
            ->assertSee('−₱54,001.00')
            ->assertSee('₱677,010.01');
    }

    public function test_booking_created_and_transaction_period_totals_keep_their_separate_scopes(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);

        $bookingCreatedThisWeek = $this->createReservation('2026-09-29 09:00:00', 'cancelled', 1000);
        $bookingCreatedThisWeek->update(['total_cost' => 200]);
        $this->recordPayment($bookingCreatedThisWeek, '2026-09-27', '100.01');
        $this->recordRefund($bookingCreatedThisWeek, '2026-09-27', '100.01');

        $bookingCreatedEarlier = $this->createReservation('2026-09-20 09:00:00', 'cancelled', 1000);
        $bookingCreatedEarlier->update(['total_cost' => 50]);
        $this->recordPayment($bookingCreatedEarlier, '2026-09-29', '49.99');
        $this->recordPayment($bookingCreatedEarlier, '2026-09-29', '0.01');
        $this->recordRefund($bookingCreatedEarlier, '2026-09-29', '20.00');

        $noPayment = $this->createReservation('2026-09-29 10:00:00', 'pending', 1000);
        $noPayment->update(['total_cost' => 10]);

        $summary = app(ReportService::class)->getSummary('weekly');

        $this->assertSame(2, $summary['reservation_count']);
        $this->assertSame(21000, (int) round($summary['contract_value'] * 100));
        $this->assertSame(10001, (int) round($summary['gross_paid'] * 100));
        $this->assertSame(10001, (int) round($summary['total_refunded'] * 100));
        $this->assertSame(0, (int) round($summary['gross_paid'] * 100) - (int) round($summary['total_refunded'] * 100) - (int) round($summary['net_paid'] * 100));
        $this->assertSame(0, (int) round($summary['net_paid'] * 100));
        $this->assertSame(21000, (int) round($summary['outstanding_balance'] * 100));
        $this->assertSame(5000, (int) round($summary['gross_payments_in_period'] * 100));
        $this->assertSame(2000, (int) round($summary['refunds_in_period'] * 100));
        $this->assertSame(3000, (int) round($summary['net_collected_in_period'] * 100));
        $this->assertSame(
            (int) round($summary['gross_payments_in_period'] * 100) - (int) round($summary['refunds_in_period'] * 100),
            (int) round($summary['net_collected_in_period'] * 100),
        );

        $page = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])->get(route('admin.reports'));
        $page->assertOk()
            ->assertSee('Bookings created in period')
            ->assertSee('Transactions in period')
            ->assertSee('₱100.01')
            ->assertSee('−₱20.00')
            ->assertSee('₱0.00');
        $this->assertSame(4, substr_count($page->getContent(), '>Bookings created in period</h3>'));
        $this->assertSame(4, substr_count($page->getContent(), '>Transactions in period</h3>'));

        $excel = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.reports.export.excel', ['period' => 'weekly']));
        $excel->assertDownload();
        $archive = new \ZipArchive();
        $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet);
        foreach ([
            'B17' => '210',
            'B18' => '100.01',
            'B19' => '0',
            'B20' => '210',
            'B21' => '50',
            'B22' => '20',
            'B23' => '30',
        ] as $cell => $expectedValue) {
            $this->assertSame($expectedValue, $this->excelCellValue($sheet, $cell), $cell);
        }
        $archive->close();
    }

    private function createReservation(string $createdAt, string $status, int $budget): Reservation
    {
        return Reservation::unguarded(fn () => Reservation::create([
            'full_name' => 'Report Client',
            'contact_number' => '09171234567',
            'email' => 'report@example.com',
            'address' => 'Report Street',
            'event_type' => 'Wedding',
            'event_date' => '2026-10-01',
            'event_time' => '18:00',
            'venue' => 'Report Hall',
            'guest_count' => 50,
            'estimated_budget' => $budget,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]));
    }

    private function recordPayment(Reservation $reservation, string $paymentDate, string $amount): ReservationPayment
    {
        return $reservation->payments()->create([
            'payment_date' => $paymentDate,
            'payment_type' => 'Partial Payment',
            'amount' => $amount,
            'payment_method' => 'Cash',
        ]);
    }

    private function recordRefund(Reservation $reservation, string $refundDate, string $amount): ReservationRefund
    {
        return $reservation->refunds()->create([
            'refund_date' => $refundDate,
            'amount' => $amount,
            'refund_method' => 'Cash',
            'status' => 'completed',
            'request_key' => (string) Str::uuid(),
        ]);
    }

    private function excelCellValue(string $sheetXml, string $cellReference): ?string
    {
        $sheet = simplexml_load_string($sheetXml);
        $this->assertNotFalse($sheet);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = $sheet->xpath('//x:c[@r="'.$cellReference.'"]/x:v');

        return isset($values[0]) ? (string) $values[0] : null;
    }
}
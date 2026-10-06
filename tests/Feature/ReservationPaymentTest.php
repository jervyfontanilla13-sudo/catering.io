<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Payment Tester', 'admin_email' => 'tester@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Gold', 'slug' => 'gold-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Payment Client',
            'contact_number' => '09171234567',
            'email' => 'payment@example.com',
            'address' => '1 Payment Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Payment Hall',
            'guest_count' => 100,
            'estimated_budget' => 75000,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-PAY'.random_int(1000, 9999),
        ]);
    }

    private function pay(Reservation $reservation, float $amount, array $overrides = [])
    {
        return $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), $overrides + [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => $amount,
            'payment_method' => 'Cash',
        ]);
    }

    private function refund(Reservation $reservation, float $amount, array $overrides = [])
    {
        return $this->withSession(self::ADMIN)->post(route('admin.reservations.refunds.store', $reservation), $overrides + [
            'request_key' => (string) Str::uuid(),
            'refund_date' => now()->toDateString(),
            'refund_amount' => $amount,
            'refund_method' => 'Cash',
        ]);
    }

    private function pngUpload(string $name = 'receipt.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }

    private function assertTotals(Reservation $reservation, float $paid, float $balance, string $status): void
    {
        $fresh = $reservation->fresh();
        $this->assertSame($paid, (float) $fresh->amount_paid, 'total paid');
        $this->assertSame($balance, (float) $fresh->balance, 'balance');
        $this->assertSame($status, $fresh->payment_status, 'status');
    }

    public function test_payments_accumulate_and_status_moves_from_downpayment_to_fully_paid(): void
    {
        $reservation = $this->reservation();

        // TEST 1
        $this->pay($reservation, 100)->assertRedirect(route('admin.reservations.payments', $reservation))->assertSessionHas('success');
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');

        // TEST 2
        $this->pay($reservation, 400, ['payment_type' => 'Partial Payment', 'payment_method' => 'GCash']);
        $this->assertTotals($reservation, 500.0, 500.0, 'Partially Paid');

        // TEST 3
        $this->pay($reservation, 500, ['payment_type' => 'Final Payment', 'payment_method' => 'Bank Transfer']);
        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');
        $this->assertSame('Final Payment', $reservation->fresh()->payment_type);
        $this->assertSame(3, $reservation->payments()->count());
    }

    public function test_receipt_image_is_optional_and_payment_totals_remain_unchanged(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();

        $this->pay($reservation, 100)->assertRedirect();

        $payment = $reservation->payments()->firstOrFail();
        $this->assertNull($payment->receipt_image_path);
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('No receipt');
    }

    public function test_admin_can_upload_preview_and_replace_payment_receipts(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $reservation = $this->reservation();
        $url = route('admin.reservations.payments.store', $reservation);

        $this->withSession(self::ADMIN)->post($url, [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
            'receipt_image' => $this->pngUpload(),
        ])->assertRedirect();

        $payment = $reservation->payments()->firstOrFail();
        $oldPath = $payment->receipt_image_path;
        $this->assertNotNull($oldPath);
        $uploadActivity = ActivityLog::where('action', 'Official Receipt uploaded')->firstOrFail();
        $this->assertSame('Payment Tester', $uploadActivity->actor_name);
        $this->assertStringContainsString('Official Receipt uploaded for ₱100.00 payment', $uploadActivity->description);
        $this->assertStringNotContainsString($oldPath, $uploadActivity->description);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');

        $receiptUrl = route('admin.reservations.payments.receipt', [$reservation, $payment]);
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('View Receipt')
            ->assertSee($receiptUrl);
        $this->withSession(self::ADMIN)
            ->get($receiptUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->flushSession();
        $this->get($receiptUrl)->assertRedirect(route('admin.login'));

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.update', [$reservation, $payment]), [
            '_method' => 'PUT',
            'current_admin_password' => 'password',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
        ])->assertRedirect();
        $this->assertSame($oldPath, $payment->fresh()->receipt_image_path);
        Storage::disk('local')->assertExists($oldPath);

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.update', [$reservation, $payment]), [
            '_method' => 'PUT',
            'current_admin_password' => 'password',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
            'receipt_image' => $this->pngUpload('replacement.png'),
        ])->assertRedirect();

        $newPath = $payment->fresh()->receipt_image_path;
        $this->assertNotSame($oldPath, $newPath);
        $replacementActivity = ActivityLog::where('action', 'Official Receipt replaced')->firstOrFail();
        $this->assertSame('Payment Tester', $replacementActivity->actor_name);
        $this->assertStringNotContainsString($newPath, $replacementActivity->description);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');
        $this->assertModelExists($payment);
        $this->assertSame($newPath, $payment->fresh()->receipt_image_path);
        $this->withSession(self::ADMIN)->get($receiptUrl)->assertOk();
    }

    public function test_payment_receipt_upload_validates_images_and_receipt_preview_requires_admin(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $fields = [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'Cash',
            'receipt_image' => UploadedFile::fake()->create('receipt.txt', 10, 'text/plain'),
        ];

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.payments.store', $reservation), $fields)
            ->assertSessionHasErrors('receipt_image');
        $this->assertSame(0, $reservation->payments()->count());

        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $this->flushSession();
        $this->get(route('admin.reservations.payments.receipt', [$reservation, $payment]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_payment_receipt_image_cannot_exceed_five_megabytes(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $validPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.payments.store', $reservation), [
                'payment_date' => now()->toDateString(),
                'payment_type' => 'Downpayment',
                'amount' => 100,
                'payment_method' => 'Cash',
                'receipt_image' => UploadedFile::fake()->createWithContent('large.png', $validPng.str_repeat('0', 5 * 1024 * 1024)),
            ])
            ->assertSessionHasErrors('receipt_image');

        $this->assertSame(0, $reservation->payments()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-receipts'));
    }

    public function test_payment_larger_than_the_remaining_balance_is_rejected(): void
    {
        $reservation = $this->reservation();

        // TEST 4
        $this->pay($reservation, 1500)->assertSessionHasErrors(['amount' => 'The amount cannot exceed the remaining balance of ₱1,000.00.']);
        $this->assertSame(0, $reservation->payments()->count());
        $this->assertTotals($reservation, 0.0, 0.0, 'Unpaid');
    }

    public function test_payment_history_has_no_delete_action_or_delete_endpoint(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('Edit')
            ->assertDontSee('Delete');

        $this->withSession(self::ADMIN)
            ->delete("/admin/reservations/{$reservation->id}/payments/{$payment->id}", [
                'current_admin_password' => 'password',
                'reason' => 'Duplicate entry',
            ])
            ->assertStatus(405);

        $this->assertModelExists($payment);
        $this->assertSame(1, $reservation->payments()->count());
        $this->assertSame(100.0, (float) $payment->fresh()->amount);
        $this->assertSame(0, ActivityLog::where('action', 'Payment deleted')->count());
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');
    }

    public function test_editing_a_payment_recalculates_and_cannot_exceed_the_contract(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), [
            'current_admin_password' => 'password',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'payment_method' => 'Cash',
            'amount' => 200,
        ]);
        $amountOnlyActivity = ActivityLog::where('action', 'Payment updated')->firstOrFail();
        $this->assertStringContainsString('Amount: ₱100.00 → ₱200.00', $amountOnlyActivity->description);
        $this->assertStringNotContainsString('Payment method:', $amountOnlyActivity->description);
        $this->assertStringNotContainsString('Payment date:', $amountOnlyActivity->description);
        $this->assertStringNotContainsString('Payment type:', $amountOnlyActivity->description);
        $this->assertTotals($reservation, 200.0, 800.0, 'Partially Paid');

        $fields = ['payment_date' => now()->toDateString(), 'payment_type' => 'Downpayment', 'payment_method' => 'GCash'];

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields + [
            'current_admin_password' => 'password',
            'amount' => 1000,
        ]);
        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');
        $activity = ActivityLog::where('action', 'Payment updated')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Amount: ₱200.00 → ₱1,000.00', $activity->description);
        $this->assertStringContainsString('Payment method: Cash → GCash', $activity->description);
        $this->assertStringNotContainsString('Payment date:', $activity->description);
        $this->assertStringNotContainsString('Payment type:', $activity->description);
        $this->assertSame('Payment Tester', $activity->actor_name);

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), [
            'current_admin_password' => 'password',
            'payment_date' => now()->subDay()->toDateString(),
            'payment_type' => 'Final Payment',
            'amount' => 1000,
            'payment_method' => 'GCash',
            'notes' => 'Updated payment reference',
        ])->assertRedirect();
        $detailsActivity = ActivityLog::where('action', 'Payment updated')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Payment date:', $detailsActivity->description);
        $this->assertStringContainsString('Payment type: Downpayment → Final Payment', $detailsActivity->description);
        $this->assertStringContainsString('Payment notes were changed.', $detailsActivity->description);
        $this->assertStringNotContainsString('password', $detailsActivity->description);
        $this->assertSame(3, ActivityLog::where('action', 'Payment updated')->count());

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields + [
            'current_admin_password' => 'password',
            'amount' => 1000.01,
        ])
            ->assertSessionHasErrorsIn('editPayment', 'amount')
            ->assertSessionHas('editing_payment', $payment->id);
        $this->assertSame(1000.0, (float) $payment->fresh()->amount);
    }

    public function test_payment_edit_requires_the_current_admin_password(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $updateRoute = route('admin.reservations.payments.update', [$reservation, $payment]);

        $this->withSession(self::ADMIN)
            ->from(route('admin.reservations.payments', $reservation))
            ->put($updateRoute, [
                'payment_date' => now()->toDateString(),
                'payment_type' => 'Downpayment',
                'amount' => 250,
                'payment_method' => 'GCash',
            ])
            ->assertRedirect(route('admin.reservations.payments', $reservation))
            ->assertSessionHasErrors('current_admin_password');
        $this->assertSame(100.0, (float) $payment->fresh()->amount);

        $this->withSession(self::ADMIN)
            ->from(route('admin.reservations.payments', $reservation))
            ->put($updateRoute, [
                'current_admin_password' => 'incorrect-password',
                'payment_date' => now()->toDateString(),
                'payment_type' => 'Downpayment',
                'amount' => 250,
                'payment_method' => 'GCash',
            ])
            ->assertSessionHasErrors('current_admin_password');
        $this->assertSame(100.0, (float) $payment->fresh()->amount);
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('data-password-confirm', false)
            ->assertSee('Confirm your administrator password to edit this payment.')
            ->assertDontSee('delete this payment');
    }

    public function test_payment_validation_messages(): void
    {
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [])
            ->assertSessionHasErrors(['payment_date', 'payment_type', 'amount', 'payment_method']);
        $this->pay($reservation, 0)->assertSessionHasErrors(['amount' => 'The amount must be greater than ₱0.00.']);
        $this->pay($reservation, -50)->assertSessionHasErrors('amount');
        $this->pay($reservation, 10.555)->assertSessionHasErrors('amount');
        $this->pay($reservation, 100, ['payment_method' => 'Crypto'])->assertSessionHasErrors('payment_method');
        $this->pay($reservation, 100, ['payment_date' => now()->addDay()->toDateString()])->assertSessionHasErrors(['payment_date' => 'The payment date cannot be in the future.']);
        $this->assertSame(0, $reservation->payments()->count());
    }

    public function test_payments_require_a_contract_price_and_contract_cannot_drop_below_paid(): void
    {
        $noContract = $this->reservation(['total_cost' => null]);
        $this->pay($noContract, 100)->assertSessionHasErrors(['amount' => 'Set the contract price before recording payments.']);

        $reservation = $this->reservation();
        $this->pay($reservation, 600);
        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 500])
            ->assertSessionHasErrors('total_cost');
        $this->assertSame(1000.0, (float) $reservation->fresh()->total_cost);

        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 600, 'payment_due_date' => '2026-10-15'])
            ->assertSessionHas('success');
        $fresh = $reservation->fresh();
        $this->assertSame('2026-10-15', $fresh->payment_due_date->toDateString());
        $this->assertNotSame($fresh->event_date, $fresh->payment_due_date->toDateString(), 'due date is independent of the event date');
        $this->assertTotals($reservation, 600.0, 0.0, 'Fully Paid');
    }

    public function test_setting_a_contract_price_logs_not_set_to_the_new_price(): void
    {
        $reservation = $this->reservation(['total_cost' => null]);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => '10000.00'])
            ->assertSessionHas('success');

        $activity = ActivityLog::where('action', 'Updated contract price')->firstOrFail();
        $this->assertStringContainsString('Contract price: Not set → ₱10,000.00', $activity->description);
        $this->assertStringContainsString('Reservation: '.$reservation->reservation_code, $activity->description);
        $this->assertSame('Payment Tester', $activity->actor_name);
        $this->assertSame('PATCH', $activity->method);
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_contract_price_changes_record_each_actual_old_and_new_value(): void
    {
        $reservation = $this->reservation([
            'total_cost' => 10000,
            'payment_due_date' => '2026-10-10',
        ]);

        foreach ([
            ['new' => '8000.00', 'description' => 'Contract price: ₱10,000.00 → ₱8,000.00'],
            ['new' => '15000.00', 'description' => 'Contract price: ₱8,000.00 → ₱15,000.00'],
            ['new' => '500.00', 'description' => 'Contract price: ₱15,000.00 → ₱500.00'],
        ] as $change) {
            $this->withSession(self::ADMIN)
                ->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => $change['new']])
                ->assertSessionHas('success');

            $this->assertStringContainsString(
                $change['description'],
                ActivityLog::where('action', 'Updated contract price')->latest('id')->firstOrFail()->description,
            );
            $this->assertSame('2026-10-10', $reservation->fresh()->payment_due_date->toDateString());
        }

        $this->assertSame(3, ActivityLog::where('action', 'Updated contract price')->count());
        $this->assertSame(0, ActivityLog::where('action', 'Updated contract and due date')->count());
        $this->assertSame(500.0, (float) $reservation->fresh()->total_cost);
    }

    public function test_unchanged_contract_price_and_due_date_do_not_create_an_activity(): void
    {
        $reservation = $this->reservation([
            'total_cost' => 10000,
            'payment_due_date' => '2026-10-10',
        ]);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.payments.details', $reservation), [
                'total_cost' => '10000.00',
                'payment_due_date' => '2026-10-10',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_due_date_only_and_combined_contract_changes_are_logged_accurately(): void
    {
        $reservation = $this->reservation([
            'total_cost' => 10000,
            'payment_due_date' => '2026-10-10',
        ]);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.payments.details', $reservation), [
                'total_cost' => '10000.00',
                'payment_due_date' => '2026-10-15',
            ])
            ->assertSessionHas('success');

        $dueDateActivity = ActivityLog::firstOrFail();
        $this->assertSame('Updated payment due date', $dueDateActivity->action);
        $this->assertSame('Payment due date: October 10, 2026 → October 15, 2026'."\nReservation: ".$reservation->reservation_code.'.', $dueDateActivity->description);
        $this->assertStringNotContainsString('Contract price:', $dueDateActivity->description);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.payments.details', $reservation), [
                'total_cost' => '500.00',
                'payment_due_date' => '2026-10-20',
            ])
            ->assertSessionHas('success');

        $combinedActivity = ActivityLog::latest('id')->firstOrFail();
        $this->assertSame('Updated contract and due date', $combinedActivity->action);
        $this->assertSame(
            'Contract price: ₱10,000.00 → ₱500.00'."\n".
            'Payment due date: October 15, 2026 → October 20, 2026'."\n".
            'Reservation: '.$reservation->reservation_code.'.',
            $combinedActivity->description,
        );
        $this->assertSame(2, ActivityLog::count());
    }

    public function test_a_double_submitted_payment_is_only_recorded_once(): void
    {
        $reservation = $this->reservation();

        $this->pay($reservation, 250)->assertSessionHas('success');
        $this->pay($reservation, 250)->assertSessionHas('success', 'That payment was already recorded, so the repeat submission was ignored.');

        $this->assertSame(1, $reservation->payments()->count());
        $this->assertTotals($reservation, 250.0, 750.0, 'Partially Paid');
    }

    public function test_payment_page_shows_summary_history_and_records_who_paid(): void
    {
        $reservation = $this->reservation(['payment_due_date' => '2026-10-15']);
        $this->pay($reservation, 100, ['notes' => 'Reference 12345']);

        $payment = $reservation->payments()->firstOrFail();
        $this->assertSame('Payment Tester', $payment->recorded_by_name);

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSeeInOrder(['Contract price', '₱1,000.00', 'Payment status', 'Partially Paid', 'Gross paid', '₱100.00', 'Net paid', '₱100.00', 'Remaining balance', '₱900.00', 'Payment due date', 'October 15, 2026'])
            ->assertSee('Reference 12345')
            ->assertSee('Payment history');

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments.print', $reservation))
            ->assertOk()->assertSee('Payment record')->assertSee('₱900.00');

        // The reservation list links to the detail page; the detail page links onward to payment history.
        $this->withSession(self::ADMIN)->get(route('admin.reservations'))
            ->assertOk()->assertSee(route('admin.reservations.show', $reservation), false);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertOk()->assertSee(route('admin.reservations.payments', $reservation), false);

        $activity = ActivityLog::where('action', 'Payment recorded')->firstOrFail();
        $this->assertStringContainsString('Amount: ₱100.00', $activity->description);
        $this->assertStringContainsString('Payment method: Cash', $activity->description);
        $this->assertStringContainsString('Payment type: Downpayment', $activity->description);
        $this->assertSame('Payment Tester', $activity->actor_name);
        $this->assertSame('tester@3yos.com', $activity->actor_email);
        $this->assertSame($payment->recorded_by_user_id, $activity->user_id);
        $this->assertStringContainsString($reservation->reservation_code, $activity->description);
        $this->assertSame(1, ActivityLog::where('action', 'Payment recorded')->count());
        $this->assertSame(0, ActivityLog::where('action', 'Recorded payment')->count());

        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertSee('Payment recorded')
            ->assertSee('Amount: ₱100.00');
    }

    public function test_partial_refunds_preserve_the_payment_ledger_and_are_idempotent(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $requestKey = (string) Str::uuid();

        $this->refund($reservation, 200, ['request_key' => $requestKey, 'reason' => 'Event change'])
            ->assertRedirect(route('admin.reservations.payments', $reservation))
            ->assertSessionHas('success', 'Refund of ₱200.00 processed.');

        $refund = $reservation->refunds()->firstOrFail();
        $this->assertSame(200.0, (float) $refund->amount);
        $this->assertSame('Event change', $refund->reason);
        $this->assertSame('Payment Tester', $refund->recorded_by_name);
        $this->assertSame(1, $reservation->payments()->count());
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertTotals($reservation, 600.0, 400.0, 'Partially Paid');
        $refundActivity = ActivityLog::where('action', 'Refund recorded')->firstOrFail();
        $this->assertStringContainsString('Amount: ₱200.00', $refundActivity->description);
        $this->assertStringContainsString('Refund method: Cash', $refundActivity->description);
        $this->assertStringContainsString('Refund date: '.now()->format('F j, Y'), $refundActivity->description);
        $this->assertStringContainsString('Reason: Event change', $refundActivity->description);
        $this->assertSame('Payment Tester', $refundActivity->actor_name);
        $this->assertSame($refund->recorded_by_user_id, $refundActivity->user_id);
        $this->assertSame(1, ActivityLog::where('action', 'Refund recorded')->count());

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('Partially Paid')
            ->assertDontSee('Partially Refunded')
            ->assertSee('Gross paid')
            ->assertSee('Total refunded')
            ->assertSee('Net paid')
            ->assertSee('Event change')
            ->assertSee('−₱200.00');
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments.print', $reservation))
            ->assertOk()
            ->assertSee('Refund')
            ->assertSee('−₱200.00')
            ->assertSee('Remaining balance');
        // The refund breakdown lives on the reservation detail page, not the summary list.
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Refunded')
            ->assertSee('&#8369;200.00', false)
            ->assertSee('Refund recorded')
            ->assertSee('Refund method: Cash');
        $this->withSession(self::ADMIN)->get(route('admin.reservations.export'))
            ->assertOk()
            ->assertSee('"Gross Paid",Refunded,"Net Paid",Balance', false)
            ->assertSee(',800.00,200.00,600.00,400.00', false);

        $this->refund($reservation, 200, ['request_key' => $requestKey])
            ->assertSessionHas('success', 'That refund submission was already processed.');
        $this->assertSame(1, $reservation->refunds()->count());
        $this->assertSame(1, ActivityLog::where('action', 'Refund recorded')->count());
        $this->assertTotals($reservation, 600.0, 400.0, 'Partially Paid');
    }

    public function test_full_refund_on_active_reservation_uses_unpaid_status_without_changing_reservation_status(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 1000, ['payment_type' => 'Full Payment']);

        $this->refund($reservation, 400)->assertSessionHas('success', 'Refund of ₱400.00 processed.');
        $this->assertTotals($reservation, 600.0, 400.0, 'Partially Paid');
        $this->refund($reservation, 600)->assertSessionHas('success', 'Refund of ₱600.00 processed.');

        $this->assertTotals($reservation, 0.0, 1000.0, 'Unpaid');
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1000.0, (float) $reservation->payments()->firstOrFail()->amount);
        $this->assertSame(2, $reservation->refunds()->count());
        $this->assertEquals(1000.0, $reservation->refunds()->sum('amount'));
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('No Payment')
            ->assertDontSee('Fully Refunded');
    }

    public function test_payment_status_matches_contract_balance_for_all_refund_scenarios(): void
    {
        $cases = [
            ['contract' => 10000, 'payments' => [], 'refunds' => [], 'reservation_status' => 'confirmed', 'status' => 'Unpaid'],
            ['contract' => 10000, 'payments' => [5000], 'refunds' => [], 'reservation_status' => 'confirmed', 'status' => 'Partially Paid'],
            ['contract' => 10000, 'payments' => [10000], 'refunds' => [1000], 'reservation_status' => 'confirmed', 'status' => 'Partially Paid'],
            ['contract' => 10000, 'payments' => [5000, 6000], 'refunds' => [1000], 'reservation_status' => 'confirmed', 'status' => 'Fully Paid'],
            ['contract' => 10000, 'payments' => [10000], 'refunds' => [10000], 'reservation_status' => 'confirmed', 'status' => 'Unpaid'],
            ['contract' => 50000, 'payments' => [60000], 'refunds' => [10000], 'reservation_status' => 'confirmed', 'status' => 'Fully Paid'],
            ['contract' => 50000, 'payments' => [50000], 'refunds' => [5000], 'reservation_status' => 'confirmed', 'status' => 'Partially Paid'],
            ['contract' => 50000, 'payments' => [50000], 'refunds' => [50000], 'reservation_status' => 'cancelled', 'status' => 'Fully Refunded'],
            ['contract' => 10000, 'payments' => [10000], 'refunds' => [5000], 'reservation_status' => 'cancelled', 'status' => 'Partially Refunded'],
            ['contract' => 10000, 'payments' => [5000], 'refunds' => [], 'reservation_status' => 'cancelled', 'status' => 'Partially Paid'],
            ['contract' => 10000, 'payments' => [], 'refunds' => [], 'reservation_status' => 'cancelled', 'status' => 'Unpaid'],
        ];

        foreach ($cases as $case) {
            $reservation = $this->reservation([
                'total_cost' => $case['contract'],
                'status' => $case['reservation_status'],
            ]);

            foreach ($case['payments'] as $amount) {
                $reservation->payments()->create([
                    'payment_date' => now()->toDateString(),
                    'payment_type' => 'Downpayment',
                    'amount' => $amount,
                    'payment_method' => 'Cash',
                ]);
            }

            foreach ($case['refunds'] as $amount) {
                $reservation->refunds()->create([
                    'refund_date' => now()->toDateString(),
                    'amount' => $amount,
                    'refund_method' => 'Cash',
                    'status' => 'completed',
                    'request_key' => (string) Str::uuid(),
                ]);
            }

            $financials = $reservation->financials();
            $grossPaid = array_sum($case['payments']);
            $totalRefunded = array_sum($case['refunds']);

            $this->assertSame($grossPaid * 100, $financials['gross_paid_cents']);
            $this->assertSame($totalRefunded * 100, $financials['total_refunded_cents']);
            $this->assertSame(($grossPaid - $totalRefunded) * 100, $financials['net_paid_cents']);
            $this->assertSame($case['status'], $financials['payment_status']);
            $this->assertSame(count($case['payments']), $reservation->payments()->count());
            $this->assertSame(count($case['refunds']), $reservation->refunds()->count());
            $this->assertSame($case['reservation_status'], $reservation->status);
        }
    }

    public function test_decimal_net_paid_status_depends_on_whether_reservation_is_cancelled(): void
    {
        $reservation = $this->reservation([
            'total_cost' => '500.03',
            'status' => 'confirmed',
        ]);
        $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Full Payment',
            'amount' => '1000.00',
            'payment_method' => 'Cash',
        ]);
        $reservation->refunds()->create([
            'refund_date' => now()->toDateString(),
            'amount' => '500.00',
            'refund_method' => 'Cash',
            'status' => 'completed',
            'request_key' => (string) Str::uuid(),
        ]);

        $financials = $reservation->financials();
        $this->assertSame(100000, $financials['gross_paid_cents']);
        $this->assertSame(50000, $financials['total_refunded_cents']);
        $this->assertSame(50000, $financials['net_paid_cents']);
        $this->assertSame(3, $financials['remaining_balance_cents']);
        $this->assertSame('Partially Paid', $financials['payment_status']);

        $reservation->update(['status' => 'cancelled']);
        $cancelledFinancials = $reservation->fresh()->financials();
        $this->assertSame(100000, $cancelledFinancials['gross_paid_cents']);
        $this->assertSame(50000, $cancelledFinancials['total_refunded_cents']);
        $this->assertSame(50000, $cancelledFinancials['net_paid_cents']);
        $this->assertSame(3, $cancelledFinancials['remaining_balance_cents']);
        $this->assertSame('Partially Refunded', $cancelledFinancials['payment_status']);
    }

    public function test_overpayment_refund_keeps_fully_paid_status_and_payment_history(): void
    {
        $reservation = $this->reservation(['total_cost' => 10000]);

        $this->pay($reservation, 5000)->assertRedirect();
        $this->refund($reservation, 1000)->assertRedirect();
        $this->pay($reservation, 6000, ['payment_type' => 'Final Payment'])->assertRedirect();

        $financials = $reservation->fresh()->financials();
        $this->assertSame(1100000, $financials['gross_paid_cents']);
        $this->assertSame(100000, $financials['total_refunded_cents']);
        $this->assertSame(1000000, $financials['net_paid_cents']);
        $this->assertSame(0, $financials['remaining_balance_cents']);
        $this->assertSame('Fully Paid', $financials['payment_status']);
        $this->assertSame(2, $reservation->payments()->count());
        $this->assertSame(1, $reservation->refunds()->count());

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations'))
            ->assertOk()
            ->assertSee('Accepted')
            ->assertSee('Fully Paid');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Fully Paid');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('Fully Paid')
            ->assertSee('₱11,000.00')
            ->assertSee('₱1,000.00')
            ->assertSee('₱10,000.00');
    }

    public function test_multiple_payments_and_refunds_with_zero_net_are_fully_refunded_and_remain_visible(): void
    {
        $reservation = $this->reservation([
            'total_cost' => 52000,
            'status' => 'cancelled',
        ]);

        $this->pay($reservation, 10000, ['payment_type' => 'Downpayment']);
        $this->refund($reservation, 1000);
        $this->pay($reservation, 41000, ['payment_type' => 'Final Payment']);
        $this->refund($reservation, 50000);
        $this->pay($reservation, 1000, ['payment_type' => 'Partial Payment']);
        $this->refund($reservation, 1000);

        $financials = $reservation->fresh()->financials();
        $this->assertSame(5200000, $financials['gross_paid_cents']);
        $this->assertSame(5200000, $financials['total_refunded_cents']);
        $this->assertSame(0, $financials['net_paid_cents']);
        $this->assertTrue($financials['has_payment_history']);
        $this->assertSame('Fully Refunded', $financials['payment_status']);
        $this->assertSame(3, $reservation->payments()->count());
        $this->assertSame(3, $reservation->refunds()->count());
        $this->assertSame('cancelled', $reservation->fresh()->status);

        $paymentHistory = $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('Fully Refunded')
            ->assertSee('Gross paid')
            ->assertSee('Total refunded')
            ->assertSee('Net paid')
            ->assertSee('₱52,000.00')
            ->assertSee('₱0.00');
        $this->assertCount(3, $paymentHistory->viewData('payments'));
        $this->assertCount(3, $paymentHistory->viewData('refunds'));

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Fully Refunded');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.export'))
            ->assertOk()
            ->assertSee('Fully Refunded');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations'))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('Fully Refunded');

        $analytics = $this->withSession(self::ADMIN)->get(route('admin.analytics'))->assertOk();
        $analyticsTotals = $analytics->viewData('totals');
        $this->assertEquals(52000.0, $analyticsTotals['paid']);
        $this->assertEquals(52000.0, $analyticsTotals['refunded']);
        $this->assertEquals(0.0, $analyticsTotals['net']);
    }

    public function test_no_payment_reservation_remains_unpaid_without_payment_history(): void
    {
        $reservation = $this->reservation([
            'status' => 'cancelled',
        ]);

        $financials = $reservation->financials();

        $this->assertSame(0, $financials['gross_paid_cents']);
        $this->assertSame(0, $financials['total_refunded_cents']);
        $this->assertFalse($financials['has_payment_history']);
        $this->assertSame('Unpaid', $financials['payment_status']);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations'))
            ->assertOk()
            ->assertSee('No Payment');
    }

    public function test_customer_can_pay_again_after_a_partial_refund(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $this->refund($reservation, 300);

        $this->pay($reservation, 500, ['payment_type' => 'Final Payment'])
            ->assertSessionHas('success', 'Payment of ₱500.00 recorded.');

        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');
        $this->assertEquals(1300.0, $reservation->payments()->sum('amount'));
        $this->assertEquals(300.0, $reservation->refunds()->sum('amount'));
    }

    public function test_refunds_cannot_exceed_net_paid_or_use_invalid_amounts_or_methods(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 500);

        $this->refund($reservation, 600)->assertSessionHasErrors(['refund_amount' => 'Refund cannot exceed the total amount paid.']);
        $this->refund($reservation, 0)->assertSessionHasErrors('refund_amount');
        $this->refund($reservation, 10.555)->assertSessionHasErrors('refund_amount');
        $this->refund($reservation, 100, ['refund_method' => 'Crypto'])->assertSessionHasErrors('refund_method');
        $this->refund($reservation, 100, ['refund_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('refund_date');

        $this->assertSame(0, $reservation->refunds()->count());
        $this->assertSame(0, ActivityLog::where('action', 'Refund recorded')->count());
        $this->assertTotals($reservation, 500.0, 500.0, 'Partially Paid');
    }

    public function test_refunded_amounts_cannot_be_undone_by_editing_payments(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $this->refund($reservation, 300);
        $payment = $reservation->payments()->firstOrFail();
        $paymentFields = [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'payment_method' => 'Cash',
        ];

        $this->withSession(self::ADMIN)
            ->put(route('admin.reservations.payments.update', [$reservation, $payment]), $paymentFields + [
                'current_admin_password' => 'password',
                'amount' => 200,
            ])
            ->assertSessionHasErrorsIn('editPayment', 'amount');
        $this->assertModelExists($payment);
        $this->assertSame(1, $reservation->refunds()->count());
        $this->assertSame(300.0, (float) $reservation->refunds()->firstOrFail()->amount);
        $this->assertTotals($reservation, 500.0, 500.0, 'Partially Paid');

        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 500])
            ->assertSessionHas('success');
        $this->assertTotals($reservation, 500.0, 0.0, 'Fully Paid');
    }

    public function test_bookings_paid_before_the_ledger_keep_their_totals(): void
    {
        $reservation = $this->reservation(['total_cost' => 30000, 'amount_paid' => 8000, 'balance' => 22000, 'payment_status' => 'Downpayment']);

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();

        $this->assertSame(1, $reservation->payments()->count());
        $this->assertTotals($reservation, 8000.0, 22000.0, 'Partially Paid');

        $this->pay($reservation, 2000, ['payment_type' => 'Partial Payment']);
        $this->assertTotals($reservation, 10000.0, 20000.0, 'Partially Paid');

        $fullyPaidWithoutContract = $this->reservation([
            'total_cost' => null,
            'amount_paid' => 8000,
            'balance' => 0,
            'payment_status' => 'Fully Paid',
            'payment_type' => 'Full Payment',
        ]);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $fullyPaidWithoutContract))->assertOk();
        $this->assertTotals($fullyPaidWithoutContract, 8000.0, 0.0, 'Fully Paid');
    }

    public function test_guests_cannot_see_or_change_payments(): void
    {
        // TEST 6 & 7: the system has no customer accounts, so customers are unauthenticated visitors.
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $this->flushSession();

        $fields = ['payment_date' => now()->toDateString(), 'payment_type' => 'Downpayment', 'amount' => 900, 'payment_method' => 'Cash'];
        $requests = [
            $this->get(route('admin.reservations.payments', $reservation)),
            $this->get(route('admin.reservations.payments.print', $reservation)),
            $this->post(route('admin.reservations.payments.store', $reservation), $fields),
            $this->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields),
            $this->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 1]),
            $this->patch(route('admin.reservations.update', $reservation), ['amount_paid' => 1000]),
            $this->post(route('admin.reservations.refunds.store', $reservation), [
                'request_key' => (string) Str::uuid(),
                'refund_date' => now()->toDateString(),
                'refund_amount' => 50,
                'refund_method' => 'Cash',
            ]),
        ];
        foreach ($requests as $response) {
            $response->assertRedirect(route('admin.login'));
        }

        $this->assertSame(1, ReservationPayment::count());
        $this->assertSame(100.0, (float) $payment->fresh()->amount);
        $this->assertTotals($reservation, 100.0, 900.0, 'Partially Paid');

        // The customer-facing status page never exposes payment details.
        $this->get(route('reservation.status', ['code' => $reservation->reservation_code]))
            ->assertOk()->assertDontSee('₱900.00')->assertDontSee('Payment history')->assertDontSee('Cash');
    }

    public function test_a_payment_cannot_be_changed_through_another_reservations_url(): void
    {
        $first = $this->reservation();
        $second = $this->reservation();
        $this->pay($first, 100);
        $payment = $first->payments()->firstOrFail();

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$second, $payment]), [
            'current_admin_password' => 'password',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 200,
            'payment_method' => 'GCash',
        ])->assertNotFound();
        $this->assertModelExists($payment);
    }

    public function test_backups_include_payment_and_refund_history(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 300);
        $this->refund($reservation, 100);

        $service = app(BackupService::class);
        $path = $service->pathFor(basename($service->create()));
        try {
            $contents = json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $contents['tables']['reservation_payments']);
        $this->assertSame(300.0, (float) $contents['tables']['reservation_payments'][0]['amount']);
        $this->assertArrayHasKey('reservation_refunds', $contents['tables']);
        $this->assertCount(1, $contents['tables']['reservation_refunds']);
        $this->assertSame(100.0, (float) $contents['tables']['reservation_refunds'][0]['amount']);
    }

    public function test_restoring_a_backup_made_before_payment_history_clears_stale_payments(): void
    {
        $reservation = $this->reservation(['total_cost' => 5000, 'amount_paid' => 2000, 'balance' => 3000, 'payment_status' => 'Downpayment']);
        $service = app(BackupService::class);
        $path = $service->create();
        $name = basename($path);

        try {
            // Simulate an older backup file that predates the payments table.
            $backup = json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
            unset($backup['tables']['reservation_payments']);
            unset($backup['tables']['reservation_refunds']);
            file_put_contents($path, Crypt::encryptString(json_encode($backup, JSON_THROW_ON_ERROR)), LOCK_EX);

            $this->pay($reservation, 500);
            $this->assertSame(2, ReservationPayment::count());
            $this->refund($reservation, 100);
            $this->assertSame(1, ReservationRefund::count());

            $service->restore($name);
        } finally {
            $service->delete($name);
        }

        $this->assertSame(0, ReservationPayment::count());
        $this->assertSame(0, ReservationRefund::count());
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();
        $this->assertTotals($reservation, 2000.0, 3000.0, 'Partially Paid');
    }
}

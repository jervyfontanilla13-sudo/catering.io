<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationStatusNotification;
use App\Models\User;
use App\Mail\ReservationUpdatedMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReservationDetailTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Detail Tester', 'admin_email' => 'detail@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Detail Package', 'slug' => 'detail-'.uniqid(), 'price' => 800, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Detail Client',
            'contact_number' => '09171234567',
            'email' => 'detail-'.uniqid().'@example.com',
            'address' => '1 Detail Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Detail Hall',
            'guest_count' => 60,
            'estimated_budget' => 48000,
            'total_cost' => 48000,
            'status' => 'pending',
            'reservation_code' => 'RES-DET'.random_int(100000, 999999),
        ]);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }

    public function test_guest_cannot_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation();

        $this->get(route('admin.reservations.show', $reservation))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);
        $bookedAt = \Illuminate\Support\Carbon::create(2026, 10, 1, 9, 42, 0, config('app.timezone'));
        $reservation->forceFill(['created_at' => $bookedAt])->save();

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));

        $response->assertOk();
        $response->assertSee($reservation->full_name);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee('Booked on');
        $response->assertSee($bookedAt->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A'));
        $response->assertSee(\Illuminate\Support\Carbon::parse($reservation->event_date)->format('F j, Y'));
        $response->assertSee($reservation->event_time);
        $response->assertSee('Customer');
        $response->assertSee('Event');
        $response->assertSee('Package');
        $response->assertSee('Contract');
        $response->assertSee('Payment');
        $response->assertSee('Notes');
        $response->assertSee('Activity');
        $response->assertSee('Submitted');
        $response->assertSeeText('Reservation Status: Accepted');
        $response->assertSee('Under Review');
        $response->assertSee('Accepted');
        $response->assertSee('Completed');
        $response->assertDontSee('Change status manually');
        $response->assertDontSee('name="status"', false);
        $response->assertDontSee('>Complete</button>', false);
    }

    public function test_reservation_detail_compacts_and_collapses_secondary_sections_by_default(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));
        $response->assertOk();
        $response->assertSee('id="reservation-information"', false);
        $response->assertSee('id="reservation-payment"', false);
        $response->assertSee('id="reservation-status"', false);
        $response->assertSee('id="reservation-editor" data-reservation-editor', false);
        $response->assertSee('id="reservation-notes-editor"', false);
        $response->assertSee('id="reservation-activity"', false);
        $response->assertSee('Edit confirmed reservation');
        $response->assertSee('View payment history');
        $response->assertSee('id="contract-preview-dialog"', false);
        $response->assertSee('No internal notes yet.');
        $response->assertSee('Reservation history and administrative changes');

        $content = $response->getContent();
        $informationPosition = strpos($content, 'id="reservation-information"');
        $contractPosition = strpos($content, 'id="reservation-contract-heading"');
        $statusPosition = strpos($content, 'id="reservation-status"');
        $paymentPosition = strpos($content, 'id="reservation-payment"');
        $editorPosition = strpos($content, 'id="reservation-editor"');
        $notesPosition = strpos($content, 'id="reservation-notes-heading"');
        $activityPosition = strpos($content, 'id="reservation-activity"');

        $this->assertNotFalse($informationPosition);
        $this->assertNotFalse($contractPosition);
        $this->assertNotFalse($statusPosition);
        $this->assertNotFalse($paymentPosition);
        $this->assertNotFalse($editorPosition);
        $this->assertNotFalse($notesPosition);
        $this->assertNotFalse($activityPosition);
        $this->assertTrue($informationPosition < $contractPosition);
        $this->assertTrue($contractPosition < $statusPosition);
        $this->assertTrue($statusPosition < $paymentPosition);
        $this->assertTrue($paymentPosition < $editorPosition);
        $this->assertTrue($editorPosition < $notesPosition);
        $this->assertTrue($notesPosition < $activityPosition);
        foreach (['schedule-event-type', 'schedule-package', 'schedule-date', 'schedule-time', 'schedule-venue', 'schedule-guests', 'schedule-services', 'schedule-special-requests', 'schedule-additional-notes', 'schedule-reason'] as $fieldId) {
            $this->assertStringContainsString('id="'.$fieldId.'"', $content);
        }
        $this->assertStringNotContainsString('id="reservation-editor" data-reservation-editor open', $content);
        $this->assertStringNotContainsString('id="reservation-notes-editor" open', $content);
        $this->assertStringNotContainsString('<details id="reservation-activity" open>', $content);
    }

    public function test_admin_can_preview_download_and_delete_multiple_contract_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.contract', $reservation), [
                'service_contract' => [
                    $this->pngUpload('contract-one.png'),
                    $this->pngUpload('contract-two.png'),
                ],
            ])
            ->assertRedirect();

        $files = $reservation->fresh()->contractFiles();
        $this->assertCount(2, $files);
        $this->assertTrue(Storage::disk('local')->exists($files[0]));
        $this->assertTrue(Storage::disk('local')->exists($files[1]));
        Storage::disk('public')->assertMissing($files[0]);
        Storage::disk('public')->assertMissing($files[1]);

        $detail = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));
        $detail->assertOk();
        $detail->assertSee('Contract 1');
        $detail->assertSee('Contract 2');
        $detail->assertSee(basename($files[0]));
        $detail->assertSee(basename($files[1]));
        $detail->assertSee('id="contract-preview-dialog"', false);
        $detail->assertSee('data-contract-preview', false);
        $detail->assertSee('data-contract-preview-close', false);
        $detail->assertSee('Download');
        $detail->assertDontSee('File is no longer available.');
        $detail->assertDontSee('storage/service-contracts/');

        $preview = $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 0]));
        $preview->assertOk();
        $preview->assertHeader('Content-Type', 'image/png');
        $preview->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheDirectives = explode(', ', $preview->headers->get('Cache-Control'));
        sort($cacheDirectives);
        $this->assertSame(['max-age=0', 'no-store', 'private'], $cacheDirectives);
        $preview->assertHeader('Content-Disposition', 'inline; filename='.basename($files[0]));

        $download = $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.download', [$reservation, 1]));
        $download->assertOk();
        $download->assertHeader('Content-Disposition', 'attachment; filename='.basename($files[1]));

        $this->withSession(self::ADMIN)
            ->delete(route('admin.reservations.contract.delete', [$reservation, 1]))
            ->assertRedirect();

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 1]))
            ->assertNotFound();
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Contract 1')
            ->assertDontSee('Contract 2');
    }

    public function test_contract_preview_and_download_require_admin_access(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $contractPath = $this->pngUpload('private-contract.png')->store('service-contracts', 'local');
        $reservation->update(['service_contracts' => [$contractPath]]);

        $this->get(route('admin.reservations.contract.preview', [$reservation, 0]))
            ->assertRedirect(route('admin.login'));
        $this->get(route('admin.reservations.contract.download', [$reservation, 0]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_missing_contract_files_are_reported_and_not_previewed(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation([
            'service_contracts' => ['service-contracts/missing-contract.png'],
        ]);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Contract 1')
            ->assertSee('File is no longer available.')
            ->assertDontSee(route('admin.reservations.contract.download', [$reservation, 0]), false);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 0]))
            ->assertNotFound();
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.download', [$reservation, 0]))
            ->assertNotFound();
    }

    public function test_reservations_list_links_to_the_detail_page(): void
    {
        $reservation = $this->reservation();

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee(route('admin.reservations.show', $reservation), false);
    }

    public function test_admin_can_save_an_internal_note_from_the_detail_page(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $timestamp = Carbon::create(2026, 10, 2, 23, 15, 0, config('app.timezone'));
        Carbon::setTestNow($timestamp);
        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), ['admin_notes' => 'Client requested vegan menu.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Client requested vegan menu.', $reservation->fresh()->admin_notes);
        $this->assertSame('pending', $reservation->fresh()->status);
        $activity = ActivityLog::where('action', 'Internal note added')->firstOrFail();
        $admin = User::where('email', 'detail@3yos.com')->firstOrFail();
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertSame('Detail Tester', $activity->actor_name);
        $this->assertSame('detail@3yos.com', $activity->actor_email);
        $this->assertSame('Added an internal note to reservation #'.$reservation->id.'.', $activity->description);
        $this->assertSame('2026-10-02', $activity->activity_date);
        $this->assertSame('23:15:00', $activity->activity_time);
        $this->assertSame($timestamp->toDateTimeString(), $activity->created_at->toDateTimeString());
        $this->assertSame(1, ActivityLog::where('action', 'Internal note added')->count());
        $this->assertSame(0, ActivityLog::where('description', 'like', 'Changed reservation #'.$reservation->id.' status to .')->count());
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Added an internal note to reservation #'.$reservation->id.'.');

        Carbon::setTestNow();
    }

    public function test_updating_an_internal_note_creates_one_activity_entry_per_change(): void
    {
        $reservation = $this->reservation(['admin_notes' => 'Original internal note.']);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), ['admin_notes' => 'Revised internal note.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Revised internal note.', $reservation->fresh()->admin_notes);
        $activity = ActivityLog::where('action', 'Internal note updated')->firstOrFail();
        $this->assertSame('Updated the internal note for reservation #'.$reservation->id.'.', $activity->description);
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee($activity->description);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), ['admin_notes' => 'Revised internal note.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ActivityLog::where('action', 'Internal note updated')->count());
        $this->assertSame(0, ActivityLog::where('action', 'Internal note added')->count());
    }

    public function test_workflow_status_changes_log_canonical_old_and_new_labels_once_per_change(): void
    {
        Mail::fake();
        $reservation = $this->reservation(['status' => 'pending']);
        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHasNoErrors();
        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertSessionHasNoErrors();

        $activities = ActivityLog::where('action', 'Reservation status changed')->orderBy('id')->get();
        $this->assertCount(2, $activities);
        $this->assertSame([
            'Changed reservation #'.$reservation->id.' status from Under Review to Accepted.',
            'Changed reservation #'.$reservation->id.' status from Accepted to Cancelled.',
        ], $activities->pluck('description')->all());
        $this->assertSame(0, ActivityLog::where('description', 'like', 'Changed reservation #'.$reservation->id.' status to .')->count());
        $admin = User::where('email', 'detail@3yos.com')->firstOrFail();
        $this->assertSame($admin->id, $activities->first()->user_id);
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Changed reservation #'.$reservation->id.' status from Under Review to Accepted.');
    }

    public function test_admin_created_booking_is_blocked_once_four_active_reservations_exist_for_the_date(): void
    {
        Mail::fake();
        $date = now()->addMonths(2)->toDateString();
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);

        $package = Package::create(['name' => 'Overflow Package', 'slug' => 'overflow-'.uniqid(), 'price' => 500]);

        $response = $this->withSession(self::ADMIN)->post(route('admin.reservations.store'), [
            'full_name' => 'Overflow Client',
            'contact_number' => '09171234567',
            'email' => 'overflow@example.com',
            'address' => '1 Overflow Street',
            'event_type' => 'Birthday',
            'event_date' => $date,
            'event_time' => '18:00',
            'venue' => 'Overflow Hall',
            'guest_count' => 30,
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors('event_date');
        $this->assertDatabaseMissing('reservations', ['email' => 'overflow@example.com']);
    }

    public function test_admin_can_update_confirmed_reservation_in_place_and_audit_only_changed_fields(): void
    {
        Mail::fake();
        $reservation = $this->reservation([
            'status' => 'confirmed',
            'additional_services' => 'Buffet setup',
            'special_requests' => 'Vegetarian meals',
            'additional_notes' => 'Keep the original note',
            'total_cost' => 48000,
            'service_contract' => 'service-contracts/existing-contract.png',
        ]);
        $bookedAt = \Illuminate\Support\Carbon::create(2026, 10, 1, 9, 42, 0, config('app.timezone'));
        $reservation->forceFill(['created_at' => $bookedAt])->save();
        $payment = $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 1000,
            'payment_method' => 'Cash',
            'receipt_image_path' => 'payment-receipts/existing-receipt.png',
        ]);
        $newPackage = Package::create(['name' => 'Platinum', 'slug' => 'platinum-'.uniqid(), 'price' => 1200]);
        $originalId = $reservation->id;
        $newDate = now()->addMonths(2)->toDateString();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), [
                'event_type' => 'Wedding',
                'event_date' => $newDate,
                'event_time' => '19:30',
                'venue' => 'Updated Garden Hall',
                'guest_count' => 70,
                'package_id' => $newPackage->id,
                'additional_services' => 'Buffet and styling',
                'special_requests' => 'Vegetarian meals',
                'additional_notes' => 'Keep the original note',
                'reason' => 'Client meeting - final event details',
            ])
            ->assertSessionHas('success', 'Reservation updated. An update notification was sent to the client.');

        $updated = $reservation->fresh();
        $this->assertSame($originalId, $updated->id);
        $this->assertSame($bookedAt->toDateTimeString(), $updated->created_at->toDateTimeString());
        $this->assertSame('confirmed', $updated->status);
        $this->assertSame($newDate, $updated->event_date);
        $this->assertSame('19:30', $updated->event_time);
        $this->assertSame('Updated Garden Hall', $updated->venue);
        $this->assertSame(70, $updated->guest_count);
        $this->assertSame($newPackage->id, $updated->package_id);
        $this->assertSame(84000.0, (float) $updated->estimated_budget);
        $this->assertSame(48000.0, (float) $updated->total_cost);
        $this->assertSame('service-contracts/existing-contract.png', $updated->service_contract);
        $this->assertSame('Buffet and styling', $updated->additional_services);
        $this->assertSame('Keep the original note', $updated->additional_notes);
        $this->assertSame(1, Reservation::whereKey($originalId)->count());
        $this->assertSame(1, ReservationPayment::whereKey($payment->id)->count());
        $this->assertSame('payment-receipts/existing-receipt.png', $payment->fresh()->receipt_image_path);

        $activity = ActivityLog::where('action', 'Reservation details updated')->latest('id')->firstOrFail();
        $this->assertSame('Detail Tester', $activity->actor_name);
        $this->assertStringStartsWith("Reservation #{$originalId} ({$updated->reservation_code})", $activity->description);
        $this->assertStringContainsString('Date: '.now()->addMonth()->format('F j, Y').' → '.\Carbon\Carbon::parse($newDate)->format('F j, Y'), $activity->description);
        $this->assertStringContainsString('Time: 6:00 PM → 7:30 PM', $activity->description);
        $this->assertStringContainsString('Venue: Detail Hall → Updated Garden Hall', $activity->description);
        $this->assertStringContainsString('Guests: 60 → 70', $activity->description);
        $this->assertStringContainsString('Package: Detail Package → Platinum', $activity->description);
        $this->assertStringContainsString('Additional services: Buffet setup → Buffet and styling', $activity->description);
        $this->assertStringContainsString('Reason: Client meeting - final event details', $activity->description);
        $this->assertStringNotContainsString('Special requests:', $activity->description);
        $this->assertStringNotContainsString('Additional notes:', $activity->description);
        $this->assertSame(1, ActivityLog::where('action', 'Reservation details updated')->count());

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $updated))
            ->assertOk()
            ->assertSee('Booked on')
            ->assertSee($bookedAt->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A'));

        Mail::assertSent(ReservationUpdatedMail::class, fn (ReservationUpdatedMail $mail) => $mail->hasTo($updated->email)
            && str_contains($mail->render(), 'Your reservation has been updated.')
            && str_contains($mail->render(), '7:30 PM'));
        $this->assertDatabaseHas('reservation_status_notifications', [
            'reservation_id' => $originalId,
            'notification_type' => 'updated',
            'status' => 'sent',
        ]);
    }

    public function test_contract_storage_migration_moves_public_files_without_changing_database_paths(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $path = 'service-contracts/legacy-contract.png';
        Storage::disk('public')->put($path, 'contract-file');
        $reservation = $this->reservation(['service_contracts' => [$path]]);

        $migration = require database_path('migrations/2026_10_01_000005_move_service_contracts_to_private_storage.php');
        $migration->up();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([$path], $reservation->fresh()->service_contracts);
    }

    public function test_contract_storage_migration_does_not_delete_a_conflicting_public_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $path = 'service-contracts/conflicting-contract.png';
        Storage::disk('public')->put($path, 'public-contract');
        Storage::disk('local')->put($path, 'private-contract');

        $migration = require database_path('migrations/2026_10_01_000005_move_service_contracts_to_private_storage.php');

        try {
            $migration->up();
            $this->fail('Expected the migration to reject the conflicting contract file.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflicting private contract file', $exception->getMessage());
        }

        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_unchanged_confirmed_reservation_fields_do_not_create_an_audit_or_send_email(): void
    {
        Mail::fake();
        $reservation = $this->reservation(['status' => 'confirmed']);
        $fields = [
            'event_type' => $reservation->event_type,
            'event_date' => $reservation->event_date,
            'event_time' => '18:00',
            'venue' => $reservation->venue,
            'guest_count' => $reservation->guest_count,
            'package_id' => $reservation->package_id,
            'additional_services' => $reservation->additional_services,
            'special_requests' => $reservation->special_requests,
            'additional_notes' => $reservation->additional_notes,
        ];

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), $fields)
            ->assertSessionHas('success', 'Reservation saved successfully.');

        $this->assertSame(0, ActivityLog::whereIn('action', ['Reservation schedule changed', 'Reservation details updated'])->count());
        Mail::assertNothingSent();
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_confirmed_reservation_date_change_respects_pending_and_confirmed_capacity(): void
    {
        Mail::fake();
        $reservation = $this->reservation(['status' => 'confirmed']);
        $targetDate = now()->addMonths(3)->toDateString();
        foreach (range(1, Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE - 1) as $number) {
            $this->reservation(['status' => 'confirmed', 'event_date' => $targetDate]);
        }
        $this->reservation(['status' => 'pending', 'event_date' => $targetDate]);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), [
                'event_date' => $targetDate,
                'event_time' => '19:30',
            ])
            ->assertSessionHasErrors([
                'event_date' => 'Unable to save this schedule because the selected date already has 4 active reservations.',
            ]);

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertNotSame($targetDate, $reservation->fresh()->event_date);
        $this->assertSame(0, ActivityLog::whereIn('action', ['Reservation schedule changed', 'Reservation details updated'])->count());
        Mail::assertNothingSent();
    }

    public function test_guest_cannot_edit_a_confirmed_reservation(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $this->patch(route('admin.reservations.update', $reservation), [
            'event_date' => now()->addMonths(2)->toDateString(),
            'event_time' => '19:30',
            'venue' => 'Unauthorized venue',
        ])->assertRedirect(route('admin.login'));

        $this->assertSame('Detail Hall', $reservation->fresh()->venue);
    }

    public function test_failed_update_email_does_not_undo_reservation_change(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $reservation = $this->reservation(['status' => 'confirmed']);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), [
                'event_time' => '19:30',
            ])
            ->assertSessionHas('success', 'Reservation updated. The update notification email could not be sent.');

        $this->assertSame('19:30', $reservation->fresh()->event_time);
        $this->assertDatabaseHas('reservation_status_notifications', [
            'reservation_id' => $reservation->id,
            'notification_type' => 'updated',
            'status' => 'failed',
            'error_message' => 'SMTP connection refused',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Reservation schedule changed',
            'actor_name' => 'Detail Tester',
        ]);
        $activity = ActivityLog::where('action', 'Reservation schedule changed')->firstOrFail();
        $this->assertStringContainsString('Time: 6:00 PM → 7:30 PM', $activity->description);
        $this->assertStringNotContainsString('Date:', $activity->description);
        $this->assertStringNotContainsString('Venue:', $activity->description);
    }

    public function test_date_only_change_has_a_compact_activity_and_omits_an_empty_reason(): void
    {
        Mail::fake();
        $reservation = $this->reservation(['status' => 'confirmed']);
        $newDate = now()->addMonths(2)->toDateString();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), [
                'event_date' => $newDate,
                'reason' => '   ',
            ])
            ->assertSessionHasNoErrors();

        $activity = ActivityLog::where('action', 'Reservation schedule changed')->firstOrFail();
        $this->assertSame('Detail Tester', $activity->actor_name);
        $this->assertStringContainsString('Date: '.now()->addMonth()->format('F j, Y').' → '.\Carbon\Carbon::parse($newDate)->format('F j, Y'), $activity->description);
        $this->assertStringNotContainsString('Time:', $activity->description);
        $this->assertStringNotContainsString('Reason:', $activity->description);
        $this->assertStringNotContainsString('Venue:', $activity->description);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Reservation schedule changed')
            ->assertSee('Detail Tester');
    }

    public function test_confirmed_schedule_edit_requires_a_valid_24_hour_time_value(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $reservation), [
                'event_time' => '7:30 PM',
            ])
            ->assertSessionHasErrors('event_time');

        $this->assertSame('18:00', $reservation->fresh()->event_time);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupSafetyTest extends TestCase
{
    public function test_backup_creation_timestamp_uses_application_timezone_and_drives_latest_order(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2036-10-01T23:59:10.123456+08:00'));
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $firstPath = $backupService->create();

        try {
            $firstName = basename($firstPath);
            $firstPayload = json_decode(Crypt::decryptString(file_get_contents($firstPath)), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('2036-10-01T23:59:10.123456+08:00', $firstPayload['created_at']);

            $firstMetadata = $backupService->inspect($firstName);
            $this->assertSame('Asia/Manila', $firstMetadata['created_at']->timezoneName);
            $this->assertSame('October 1, 2036 at 11:59 PM', $firstMetadata['created_at']->format('F j, Y \a\t g:i A'));

            Carbon::setTestNow(Carbon::parse('2036-10-02T00:00:10.654321+08:00'));
            $secondPath = $backupService->create();
            $secondName = basename($secondPath);
            $secondPayload = json_decode(Crypt::decryptString(file_get_contents($secondPath)), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('2036-10-02T00:00:10.654321+08:00', $secondPayload['created_at']);

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-time-admin@example.test',
            ])->get(route('admin.backups'))
                ->assertOk();
            $byName = collect($response->viewData('backups'))->keyBy('name');
            $this->assertTrue($byName[$secondName]['latest'], json_encode($byName->only([$firstName, $secondName])->toArray()));
            $this->assertFalse($byName[$secondName]['older']);
            $this->assertFalse($byName[$firstName]['latest']);
            $this->assertTrue($byName[$firstName]['older']);
            $response->assertSee('Created: October 1, 2036 at 11:59 PM')
                ->assertSee('Created: October 2, 2036 at 12:00 AM');
        } finally {
            Carbon::setTestNow();
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_missing_or_timezone_less_creation_timestamp_is_not_replaced_by_file_time(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 1;
        unset($contents['created_at']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
        touch($backupPath, now()->addYears(10)->getTimestamp());

        try {
            $metadata = $backupService->inspect($backupName);
            $this->assertTrue($metadata['compatible']);
            $this->assertNull($metadata['created_at']);
            $this->assertNull($metadata['sort_timestamp']);

            $contents['created_at'] = '2026-10-01T23:59:00';
            file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
            $this->assertNull($backupService->inspect($backupName)['created_at']);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_database_backup_restores_rows_and_ignores_columns_not_in_the_current_schema(): void
    {
        $package = Package::create([
            'name' => 'Backup package',
            'slug' => 'backup-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $this->assertStringContainsString('/storage/app/private/backups/', str_replace('\\', '/', $backupPath));
            $encrypted = file_get_contents($backupPath);
            $this->assertNotFalse($encrypted);
            $this->assertStringNotContainsString('"packages"', $encrypted);
            $this->assertStringNotContainsString('backup_version', $encrypted);
            $this->assertStringNotContainsString('backup_format', $encrypted);
            $this->assertStringNotContainsString('APP_KEY', $encrypted);
            $backup = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(BackupService::BACKUP_FORMAT, $backup['backup_format']);
            $this->assertSame(BackupService::BACKUP_VERSION, $backup['backup_version']);
            $this->assertArrayNotHasKey('users', $backup['tables']);
            $backup['tables']['packages'][0]['future_column'] = 'ignored during restore';
            file_put_contents($backupPath, Crypt::encryptString(json_encode($backup, JSON_THROW_ON_ERROR)), LOCK_EX);
            $package->update(['description' => 'Changed description']);

            $restoredRows = $backupService->restore($backupName);

            $this->assertGreaterThan(0, $restoredRows);
            $this->assertSame('Original description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_tampered_encrypted_backup_is_rejected_without_restoring_rows(): void
    {
        $package = Package::create([
            'name' => 'Tamper package',
            'slug' => 'tamper-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Keep this description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            file_put_contents($backupPath, file_get_contents($backupPath).'tampered', LOCK_EX);
            try {
                $backupService->restore($backupName);
                $this->fail('A tampered backup must not be restored.');
            } catch (\InvalidArgumentException) {
                // Expected: the authenticated ciphertext was modified.
            }
            $this->assertSame('Keep this description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_wrong_encryption_key_fails_safely_before_any_restore_changes(): void
    {
        $package = Package::create([
            'name' => 'Wrong key package',
            'slug' => 'wrong-key-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current data must remain intact',
        ]);
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentAdmin#2026',
            'is_active' => true,
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $package->update(['description' => 'Changed after backup']);
        $originalEncrypter = Crypt::getFacadeRoot();
        $backupsBeforeRestore = $backupService->listBackups();

        try {
            Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));

            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'admin_session_version' => $admin->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentAdmin#2026',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup' => 'This backup could not be verified. It may be corrupted or invalid. No data was restored.',
                ])
                ->assertDontSee('CurrentAdmin#2026')
                ->assertDontSee('AES-256-CBC');
            $this->assertSame('Changed after backup', $package->fresh()->description);
            $this->assertSame($backupsBeforeRestore, $backupService->listBackups());
            $this->assertTrue(Hash::check('CurrentAdmin#2026', $admin->fresh()->password));
        } finally {
            Crypt::swap($originalEncrypter);
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_tampered_backup_restore_returns_generic_error_without_creating_safety_backup(): void
    {
        $package = Package::create([
            'name' => 'Tampered route package',
            'slug' => 'tampered-route-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current data must remain intact',
        ]);
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentAdmin#2026',
            'is_active' => true,
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        file_put_contents($backupPath, file_get_contents($backupPath).'tampered', LOCK_EX);
        $backupsBeforeRestore = $backupService->listBackups();
        $package->update(['description' => 'Changed after backup']);

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'admin_session_version' => $admin->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentAdmin#2026',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup' => 'This backup could not be verified. It may be corrupted or invalid. No data was restored.',
                ])
                ->assertDontSee('CurrentAdmin#2026');
            $this->assertSame('Changed after backup', $package->fresh()->description);
            $this->assertSame($backupsBeforeRestore, $backupService->listBackups());
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_legacy_plain_json_backup_remains_restorable(): void
    {
        $package = Package::create([
            'name' => 'Legacy package',
            'slug' => 'legacy-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original legacy description',
        ]);
        $backupService = app(BackupService::class);
        $encryptedPath = $backupService->create();
        $backup = json_decode(Crypt::decryptString(file_get_contents($encryptedPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($backup['backup_version']);
        unset($backup['backup_format']);
        $legacyName = 'backup-'.now()->format('YmdHis').'-'.random_int(100, 999).'.json';
        $legacyDirectory = storage_path('app/backups');
        if (! is_dir($legacyDirectory)) {
            mkdir($legacyDirectory, 0700, true);
        }
        $legacyPath = $legacyDirectory.DIRECTORY_SEPARATOR.$legacyName;

        try {
            file_put_contents($legacyPath, json_encode($backup, JSON_THROW_ON_ERROR), LOCK_EX);
            $package->update(['description' => 'Changed after legacy backup']);

            $this->assertFalse($backupService->isEncrypted($legacyName));
            $legacyMetadata = $backupService->inspect($legacyName);
            $this->assertSame('Legacy format', $legacyMetadata['format']);
            $this->assertTrue($legacyMetadata['legacy']);
            $this->assertTrue($legacyMetadata['compatible']);
            $this->assertGreaterThan(0, $backupService->restore($legacyName));
            $this->assertSame('Original legacy description', $package->fresh()->description);
        } finally {
            @unlink($legacyPath);
            $backupService->delete(basename($encryptedPath));
        }
    }

    public function test_backup_page_displays_the_current_format_and_system_compatibility(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => 'backup-admin@example.test',
        ])->get(route('admin.backups'));

        $response->assertOk()
            ->assertSee('Backup format:')
            ->assertSee('JSON v'.BackupService::BACKUP_VERSION)
            ->assertDontSee(BackupService::BACKUP_FORMAT)
            ->assertSee('Current system format')
            ->assertSee('backup-page-controls', false)
            ->assertSee('backup-format-indicator', false)
            ->assertDontSee('backup-format-card', false);
    }

    public function test_backup_page_shows_database_health_and_distinguishes_infrastructure_recovery(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => 'backup-admin@example.test',
        ])->get(route('admin.backups'));

        $response->assertOk()
            ->assertSee('Database status')
            ->assertSee('Healthy')
            ->assertSee('Database Recovery')
            ->assertSee('Administrator account recovery and full database recovery require authorized server access. Follow the documented recovery procedure to restore access or recover the database from a validated backup.')
            ->assertDontSee('This page cannot recover a database it depends on.');
        $this->assertSame('healthy', app(BackupService::class)->databaseStatus()['status']);
    }

    public function test_database_health_response_does_not_expose_connection_errors(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \PDOException('database-password=do-not-expose'));

        $status = app(BackupService::class)->databaseStatus();

        $this->assertSame('unavailable', $status['status']);
        $this->assertSame('Unavailable', $status['label']);
        $this->assertStringNotContainsString('do-not-expose', json_encode($status, JSON_THROW_ON_ERROR));
    }

    public function test_database_health_requires_configured_database_backed_session_tables(): void
    {
        config([
            'session.driver' => 'database',
            'session.table' => 'recovery_sessions',
        ]);
        Schema::shouldReceive('hasTable')
            ->andReturnUsing(static fn (string $table): bool => $table !== 'recovery_sessions');

        $status = app(BackupService::class)->databaseStatus();

        $this->assertSame('recovery_required', $status['status']);
        $this->assertSame('Recovery Required', $status['label']);
        $this->assertStringNotContainsString('recovery_sessions', $status['message']);
    }

    public function test_primary_admin_can_validate_a_backup_and_the_action_is_audited(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.validate'), ['backup' => $backupName]);

            $response->assertRedirect()
                ->assertSessionHas('success', 'Backup validation passed. The backup is compatible and its integrity is verified.');
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Backup validated',
                'actor_email' => 'backup-admin@example.test',
            ]);
            $this->assertSame('validated', $backupService->metadataForDisplay($backupName)['validation_status']);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_complete_current_backup_validates_all_required_tables_and_columns(): void
    {
        $package = Package::create([
            'name' => 'Complete backup package',
            'slug' => 'complete-backup-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Complete structure',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $validation = $backupService->validate($backupName);

            $this->assertSame('validated', $backupService->metadataForDisplay($backupName)['validation_status']);
            $this->assertSame('JSON v'.BackupService::BACKUP_VERSION, $backupService->inspect($backupName)['format']);
            $this->assertSame(Package::query()->count(), $validation['expected_rows']['packages']);
            $this->assertContains('name', $validation['required_columns']['packages']);
            $this->assertArrayHasKey('reservation_payments', $validation['expected_rows']);
            $this->assertArrayHasKey('reservation_refunds', $validation['expected_rows']);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_current_backup_missing_a_required_table_cannot_validate_or_restore(): void
    {
        $package = Package::create([
            'name' => 'Missing table package',
            'slug' => 'missing-table-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Keep current data',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['tables']['packages']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
        $package->update(['description' => 'Changed after backup']);
        $admin = User::factory()->create(['role' => 'full', 'password' => 'validation-admin-password']);

        try {
            $this->assertFalse($backupService->inspect($backupName)['compatible']);
            $this->assertNotSame('validated', $backupService->metadataForDisplay($backupName)['validation_status']);
            try {
                $backupService->validate($backupName);
                $this->fail('A current backup missing packages must fail validation.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('required table is missing', $exception->getMessage());
            }

            $listedBackups = $backupService->listBackups();
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'validation-admin-password',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors('backup');
            $this->assertSame('Changed after backup', $package->fresh()->description);
            $this->assertSame($listedBackups, $backupService->listBackups());
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_current_backup_with_an_incomplete_required_row_fails_validation(): void
    {
        Package::create([
            'name' => 'Incomplete row package',
            'slug' => 'incomplete-row-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Required name is removed',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['tables']['packages'][0]['name']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $this->assertFalse($backupService->inspect($backupName)['compatible']);
            $this->expectException(\InvalidArgumentException::class);
            $backupService->validate($backupName);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_legacy_backup_may_omit_tables_not_present_in_its_format(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['backup_format'], $contents['backup_version']);
        unset(
            $contents['tables']['reservation_payments'],
            $contents['tables']['reservation_refunds'],
            $contents['tables']['gallery_items'],
        );
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $this->assertTrue($backupService->inspect($backupName)['compatible']);
            $this->assertArrayHasKey('expected_rows', $backupService->validate($backupName));
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_restore_verifies_counts_relationships_and_administrator_protection(): void
    {
        $package = Package::create([
            'name' => 'Verified restore package',
            'slug' => 'verified-restore-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Backup description',
        ]);
        $client = Client::create([
            'name' => 'Verified restore client',
            'email' => 'verified-restore-client@example.test',
        ]);
        $reservation = Reservation::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'full_name' => $client->name,
            'contact_number' => '09123456789',
            'email' => $client->email,
            'address' => 'Test address',
            'event_type' => 'Wedding',
            'event_date' => '2030-01-01',
            'event_time' => '10:00 AM',
            'venue' => 'Test venue',
            'guest_count' => 20,
            'estimated_budget' => 500,
            'status' => 'confirmed',
            'reservation_code' => 'VERIFIED-RELATION',
        ]);
        $paymentId = DB::table('reservation_payments')->insertGetId([
            'reservation_id' => $reservation->id,
            'payment_date' => '2026-10-01',
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'Cash',
            'recorded_by_name' => 'Test administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reservation_refunds')->insert([
            'reservation_id' => $reservation->id,
            'payment_id' => $paymentId,
            'refund_date' => '2026-10-02',
            'amount' => 10,
            'refund_method' => 'Cash',
            'request_key' => (string) Str::uuid(),
            'recorded_by_name' => 'Test administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'unchanged-admin-password',
            'is_active' => true,
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $validation = $backupService->validate($backupName);
            $this->assertSame(Package::query()->count(), $validation['expected_rows']['packages']);
            $this->assertSame(1, $validation['expected_rows']['reservations']);
            $this->assertSame(1, $validation['expected_rows']['reservation_payments']);
            $this->assertSame(1, $validation['expected_rows']['reservation_refunds']);
            $package->update(['description' => 'Changed after backup']);

            $restoredRows = $backupService->restore($backupName);
            $verification = $backupService->verifyRestoration($validation);

            $this->assertGreaterThan(0, $restoredRows);
            $this->assertTrue($verification['success'], implode(' ', $verification['warnings']));
            $this->assertSame('Backup description', $package->fresh()->description);
            $this->assertTrue(Hash::check('unchanged-admin-password', $admin->fresh()->password));
            $this->assertSame(1, DB::table('reservation_payments')->where('reservation_id', $reservation->id)->count());
            $this->assertSame(1, DB::table('reservation_refunds')->where('payment_id', $paymentId)->count());

            Package::create([
                'name' => 'Unexpected post-restore package',
                'slug' => 'unexpected-post-restore-package',
                'price' => 0,
                'min_guests' => 0,
                'max_guests' => 0,
            ]);
            $failedVerification = $backupService->verifyRestoration($validation);
            $this->assertFalse($failedVerification['success']);
            $this->assertContains('Restored data counts do not match the validated backup.', $failedVerification['warnings']);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_backup_validation_rejects_invalid_reservation_payment_and_refund_relationships(): void
    {
        $package = Package::create([
            'name' => 'Relationship validation package',
            'slug' => 'relationship-validation-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
        ]);
        $reservation = Reservation::create([
            'full_name' => 'Relationship validation reservation',
            'contact_number' => '09123456789',
            'email' => 'relationship-validation@example.test',
            'address' => 'Test address',
            'event_type' => 'Wedding',
            'event_date' => '2030-01-01',
            'event_time' => '10:00 AM',
            'venue' => 'Test venue',
            'guest_count' => 20,
            'estimated_budget' => 500,
            'status' => 'confirmed',
            'reservation_code' => 'RELATIONSHIP-VALIDATION',
            'package_id' => $package->id,
        ]);
        $paymentId = DB::table('reservation_payments')->insertGetId([
            'reservation_id' => $reservation->id,
            'payment_date' => '2026-10-01',
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'Cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reservation_refunds')->insert([
            'reservation_id' => $reservation->id,
            'payment_id' => $paymentId,
            'refund_date' => '2026-10-02',
            'amount' => 10,
            'refund_method' => 'Cash',
            'request_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        try {
            foreach ([
                ['reservation_payments', 'reservation_id'],
                ['reservation_refunds', 'payment_id'],
            ] as [$table, $column]) {
                $invalidContents = $contents;
                $invalidContents['tables'][$table][0][$column] = 99999999;
                file_put_contents($backupPath, Crypt::encryptString(json_encode($invalidContents, JSON_THROW_ON_ERROR)), LOCK_EX);

                try {
                    $backupService->validate($backupName);
                    $this->fail("Invalid {$table} relationship must fail validation.");
                } catch (\InvalidArgumentException) {
                    $this->assertSame($package->id, DB::table('reservations')->where('id', $reservation->id)->value('package_id'));
                }
            }
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_backup_validation_rejects_duplicate_reservation_codes(): void
    {
        $first = Reservation::create([
            'full_name' => 'First duplicate-code reservation',
            'contact_number' => '09123456789',
            'email' => 'duplicate-code-one@example.test',
            'address' => 'Test address',
            'event_type' => 'Wedding',
            'event_date' => '2030-01-01',
            'event_time' => '10:00 AM',
            'venue' => 'Test venue',
            'guest_count' => 20,
            'estimated_budget' => 500,
            'status' => 'confirmed',
            'reservation_code' => 'DUPLICATE-CODE-ONE',
        ]);
        $second = Reservation::create([
            'full_name' => 'Second duplicate-code reservation',
            'contact_number' => '09123456789',
            'email' => 'duplicate-code-two@example.test',
            'address' => 'Test address',
            'event_type' => 'Wedding',
            'event_date' => '2030-01-02',
            'event_time' => '10:00 AM',
            'venue' => 'Test venue',
            'guest_count' => 20,
            'estimated_budget' => 500,
            'status' => 'confirmed',
            'reservation_code' => 'DUPLICATE-CODE-TWO',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['tables']['reservations'][1]['reservation_code'] = $contents['tables']['reservations'][0]['reservation_code'];
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $this->assertNotSame($first->reservation_code, $second->reservation_code);
            $this->assertFalse($backupService->inspect($backupName)['compatible']);
            $this->expectException(\InvalidArgumentException::class);
            $backupService->validate($backupName);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_generic_settings_values_are_excluded_and_live_settings_survive_restore(): void
    {
        DB::table('settings')->insert([
            'key' => 'arbitrary-sensitive-setting',
            'value' => 'do-not-back-up-this-value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $payload = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([], $payload['tables']['settings']);
            $this->assertStringNotContainsString('arbitrary-sensitive-setting', file_get_contents($backupPath));
            $this->assertStringNotContainsString('do-not-back-up-this-value', file_get_contents($backupPath));
            $validation = $backupService->validate($backupName);

            DB::table('settings')->where('key', 'arbitrary-sensitive-setting')->update([
                'value' => 'changed-live-value',
            ]);
            $backupService->restore($backupName);
            $verification = $backupService->verifyRestoration($validation);

            $this->assertTrue($verification['success'], implode(' ', $verification['warnings']));
            $this->assertDatabaseHas('settings', [
                'key' => 'arbitrary-sensitive-setting',
                'value' => 'changed-live-value',
            ]);
        } finally {
            DB::table('settings')->where('key', 'arbitrary-sensitive-setting')->delete();
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_tampered_backup_validation_fails_without_modifying_database_data(): void
    {
        $package = Package::create([
            'name' => 'Validation package',
            'slug' => 'validation-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current data stays intact',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        file_put_contents($backupPath, file_get_contents($backupPath).'tampered', LOCK_EX);

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.validate'), ['backup' => $backupName]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors(['backup' => 'Backup validation failed. The database was not changed. The backup is corrupted or invalid.']);
            $this->assertSame('Current data stays intact', $package->fresh()->description);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Backup validation failed',
                'actor_email' => 'backup-admin@example.test',
            ]);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_backup_list_uses_cached_metadata_and_marks_changed_files_unknown_until_validated(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $firstPath = $backupService->create();
        $secondPath = $backupService->create();
        $firstName = basename($firstPath);
        $secondName = basename($secondPath);

        try {
            $firstContents = json_decode(Crypt::decryptString(file_get_contents($firstPath)), true, 512, JSON_THROW_ON_ERROR);
            $secondContents = json_decode(Crypt::decryptString(file_get_contents($secondPath)), true, 512, JSON_THROW_ON_ERROR);

            $firstContents['created_at'] = now()->addHour()->toIso8601String();
            $secondContents['backup_version'] = 0;
            $secondContents['created_at'] = now()->subDay()->toIso8601String();
            file_put_contents($firstPath, Crypt::encryptString(json_encode($firstContents, JSON_THROW_ON_ERROR)), LOCK_EX);
            file_put_contents($secondPath, Crypt::encryptString(json_encode($secondContents, JSON_THROW_ON_ERROR)), LOCK_EX);
            touch($firstPath, filemtime($firstPath) + 2);
            touch($secondPath, filemtime($secondPath) + 2);
            $backupService->validate($firstName);
            try {
                $backupService->validate($secondName);
                $this->fail('An unsupported backup version must fail validation.');
            } catch (\InvalidArgumentException) {
                // The page must not decrypt the modified backup again just to render its metadata.
            }

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->get(route('admin.backups'));

            $response->assertOk()->assertViewHas('backups', function (array $backups) use ($firstName, $secondName): bool {
                $byName = collect($backups)->keyBy('name');

                return $byName[$firstName]['format'] === 'JSON v'.BackupService::BACKUP_VERSION
                    && $byName[$firstName]['compatible'] === true
                    && $byName[$firstName]['latest'] === true
                    && $byName[$firstName]['legacy'] === false
                    && $byName[$secondName]['format'] === 'Not validated'
                    && $byName[$secondName]['compatible'] === null
                    && $byName[$secondName]['latest'] === false
                    && $byName[$secondName]['legacy'] === false;
            });
            $response->assertSee($firstName)
                ->assertSee($secondName)
                ->assertSee('Compatibility unknown — validate first')
                ->assertSee('Current')
                ->assertSee('disabled', false);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_unsupported_older_newer_and_other_formats_are_rejected_before_restore(): void
    {
        $package = Package::create([
            'name' => 'Versioned package',
            'slug' => 'versioned-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Must remain unchanged',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $original = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);

        try {
            foreach ([
                ['backup_format' => BackupService::BACKUP_FORMAT, 'backup_version' => 0],
                ['backup_format' => BackupService::BACKUP_FORMAT, 'backup_version' => BackupService::BACKUP_VERSION + 1],
                ['backup_format' => 'OTHER_JSON_BACKUP', 'backup_version' => BackupService::BACKUP_VERSION],
            ] as $incompatibleMetadata) {
                $incompatible = array_merge($original, $incompatibleMetadata);
                file_put_contents($backupPath, Crypt::encryptString(json_encode($incompatible, JSON_THROW_ON_ERROR)), LOCK_EX);

                try {
                    $backupService->restore($backupName);
                    $this->fail('An incompatible backup must not be restored.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString('not compatible', $exception->getMessage());
                }

                $this->assertSame('Must remain unchanged', $package->fresh()->description);
            }
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_unversioned_backup_is_accepted_only_when_it_matches_the_known_legacy_structure(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['backup_format'], $contents['backup_version'], $contents['created_at']);
        file_put_contents($backupPath, json_encode($contents, JSON_THROW_ON_ERROR), LOCK_EX);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $backupService->validate($backupName);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_existing_versioned_backup_without_format_identifier_remains_compatible(): void
    {
        $package = Package::create([
            'name' => 'Existing format package',
            'slug' => 'existing-format-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['backup_format']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $package->update(['description' => 'Changed description']);

            $this->assertGreaterThan(0, $backupService->restore($backupName));
            $this->assertSame('Original description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_version_one_restore_preserves_current_admins_and_audit_history(): void
    {
        $primary = User::factory()->create([
            'name' => 'Current Primary',
            'email' => 'current.primary@example.test',
            'role' => 'full',
            'password' => 'CurrentPrimary#2026',
            'is_active' => true,
        ]);
        $teamAdmin = User::factory()->create([
            'name' => 'Current Team Admin',
            'email' => 'current.team@example.test',
            'role' => 'limited',
            'password' => 'CurrentTeam#2026',
            'is_active' => true,
        ]);
        $disabledAdmin = User::factory()->create([
            'name' => 'Disabled Admin',
            'email' => 'disabled.admin@example.test',
            'role' => 'limited',
            'password' => 'DisabledAdmin#2026',
            'is_active' => false,
        ]);
        $package = Package::create([
            'name' => 'Pre-restore package',
            'slug' => 'pre-restore-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Description from the backup',
        ]);
        ActivityLog::create([
            'user_id' => $primary->id,
            'actor_name' => $primary->name,
            'actor_email' => $primary->email,
            'actor_role' => 'full',
            'action' => 'Archived audit event',
            'method' => 'CLI',
            'activity_date' => now()->subDay()->toDateString(),
            'activity_time' => now()->subDay()->toTimeString(),
            'description' => 'Archived event attribution is preserved.',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
            $contents['backup_version'] = 1;
            $contents['tables']['users'] = User::query()->get()->map(fn (User $user): array => $user->getAttributes())->all();
            foreach ($contents['tables']['users'] as &$archivedUser) {
                $archivedUser['password'] = Hash::make('HistoricalPassword#2020');
                $archivedUser['name'] = 'Historical '.$archivedUser['name'];
                if ($archivedUser['id'] === $teamAdmin->id) {
                    $archivedUser['role'] = 'full';
                }
                if ($archivedUser['id'] === $disabledAdmin->id) {
                    $archivedUser['is_active'] = true;
                }
            }
            unset($archivedUser);
            file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

            $package->update(['description' => 'Changed after backup']);
            ActivityLog::create([
                'user_id' => $primary->id,
                'actor_name' => $primary->name,
                'actor_email' => $primary->email,
                'actor_role' => 'full',
                'action' => 'Current audit event',
                'method' => 'CLI',
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Must survive restore.',
            ]);

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $primary->id,
                'admin_name' => $primary->name,
                'admin_email' => $primary->email,
                'admin_session_version' => $primary->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentPrimary#2026',
            ]);

            $response->assertRedirect()
                ->assertSessionHas('success', fn (string $message): bool => str_starts_with($message, 'Database recovery completed successfully.'));
            $this->assertSame('Description from the backup', $package->fresh()->description);
            $this->assertTrue(Hash::check('CurrentPrimary#2026', $primary->fresh()->password));
            $this->assertSame('full', $primary->fresh()->role);
            $this->assertTrue($primary->fresh()->is_active);
            $this->assertTrue(Hash::check('CurrentTeam#2026', $teamAdmin->fresh()->password));
            $this->assertSame('limited', $teamAdmin->fresh()->role);
            $this->assertTrue($teamAdmin->fresh()->is_active);
            $this->assertSame('limited', $disabledAdmin->fresh()->role);
            $this->assertFalse($disabledAdmin->fresh()->is_active);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Current audit event',
                'user_id' => $primary->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Archived audit event',
                'user_id' => null,
                'actor_name' => 'Current Primary',
                'actor_email' => 'current.primary@example.test',
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Database restore started',
                'user_id' => $primary->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Recovery safety backup created',
                'user_id' => $primary->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Recovery verification completed',
                'user_id' => $primary->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Database restore completed',
                'user_id' => $primary->id,
            ]);

            $this->get(route('admin.dashboard'))->assertOk();
            $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
            $this->post(route('admin.login.post'), [
                'email' => $primary->email,
                'password' => 'CurrentPrimary#2026',
            ])->assertRedirect(route('admin.dashboard'));
            $this->post(route('admin.logout'));
            $this->post(route('admin.login.post'), [
                'email' => $teamAdmin->email,
                'password' => 'CurrentTeam#2026',
            ])->assertRedirect(route('admin.dashboard'));
            $this->post(route('admin.logout'));
            $this->post(route('admin.login.post'), [
                'email' => $disabledAdmin->email,
                'password' => 'DisabledAdmin#2026',
            ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_failed_business_restore_rolls_back_without_touching_authentication(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentPrimary#2026',
            'is_active' => true,
        ]);
        $package = Package::create([
            'name' => 'Rollback package',
            'slug' => 'rollback-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current description',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $duplicatePackage = $contents['tables']['packages'][0];
        $duplicatePackage['id'] = $duplicatePackage['id'] + 1;
        $contents['tables']['packages'][] = $duplicatePackage;
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $package->update(['description' => 'Must roll back to this value']);

            try {
                $backupService->restore($backupName);
                $this->fail('A unique constraint violation should abort the restore.');
            } catch (\InvalidArgumentException|QueryException) {
                // Invalid backup data or a database constraint must leave the current state intact.
            }

            $this->assertSame('Must roll back to this value', $package->fresh()->description);
            $this->assertTrue($admin->fresh()->is_active);
            $this->assertSame('full', $admin->fresh()->role);
            $this->assertTrue(Hash::check('CurrentPrimary#2026', $admin->fresh()->password));
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_incompatible_backup_restore_is_blocked_before_safety_backup_creation(): void
    {
        $package = Package::create([
            'name' => 'Controller version package',
            'slug' => 'controller-version-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Keep this data',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = BackupService::BACKUP_VERSION + 1;
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
        $listedBackups = $backupService->listBackups();
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'correct-password',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors(['backup' => 'Backup format is incompatible with the current system. No data was restored.']);
            $this->assertSame('Keep this data', $package->fresh()->description);
            $this->assertSame($listedBackups, $backupService->listBackups());
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_restore_requires_a_correct_admin_password(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->post(route('admin.backups.restore'), [
            'backup' => 'backup-20260926000000.json',
            'confirm_legacy' => '1',
            'current_admin_password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('current_admin_password');
    }

    public function test_legacy_restore_requires_explicit_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->post(route('admin.backups.restore'), [
            'backup' => 'backup-20260926000000.json',
            'current_admin_password' => 'correct-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('backup');
    }

    public function test_backup_download_requires_admin_authentication(): void
    {
        $response = $this->post(route('admin.backups.download'), [
            'backup' => 'backup-20260926000000.json.enc',
        ]);

        $response->assertRedirect();
    }

    public function test_recovery_and_full_system_restore_have_no_public_routes(): void
    {
        $this->get('/admin/recover')->assertNotFound();
        $this->post('/admin/full-restore')->assertNotFound();

        $teamAdmin = User::factory()->create([
            'role' => 'limited',
            'is_active' => true,
        ]);
        $this->withSession([
            'is_admin' => true,
            'admin_user_id' => $teamAdmin->id,
            'admin_session_version' => $teamAdmin->session_version,
            'admin_role' => 'limited',
        ])->post(route('admin.backups.validate'), [
            'backup' => 'backup-20260926000000.json.enc',
        ])->assertForbidden();
    }

    public function test_admin_downloads_encrypted_backup_without_decrypting_it(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $ciphertext = file_get_contents($backupPath);

        try {
            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.download'), ['backup' => $backupName]);

            $response->assertDownload($backupName);
            $this->assertSame($ciphertext, file_get_contents($backupPath));
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_backup_deletion_requires_a_correct_admin_password(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->delete(route('admin.backups.delete'), [
            'backup' => 'backup-20260926000000.json',
            'current_admin_password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('current_admin_password');
    }

    public function test_admin_can_upload_a_valid_backup_file(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $sourceBackupPath = $backupService->create();

        try {
            $upload = UploadedFile::fake()->createWithContent(
                'uploaded-backup-20260929020000.json',
                Crypt::decryptString(file_get_contents($sourceBackupPath)),
                'application/json'
            );

            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), [
                'backup_file' => $upload,
            ]);

            $response->assertRedirect('/admin/backups');
            $response->assertSessionHas('success', 'Backup uploaded successfully.');
            $uploadedBackups = array_values(array_filter($backupService->listBackups(), fn (string $backup) => str_starts_with($backup, 'uploaded-backup-')));
            $this->assertNotEmpty($uploadedBackups);
            foreach ($uploadedBackups as $uploadedBackup) {
                $this->assertTrue($backupService->isEncrypted($uploadedBackup));
                $stored = json_decode(
                    Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $this->assertSame(BackupService::BACKUP_FORMAT, $stored['backup_format']);
                $this->assertSame(BackupService::BACKUP_VERSION, $stored['backup_version']);
            }

            $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->get(route('admin.backups'))
                ->assertOk()
                ->assertViewHas('backups', function (array $backups) use ($uploadedBackups): bool {
                    $uploaded = collect($backups)->firstWhere('name', $uploadedBackups[0]);

                    return $uploaded !== null
                        && $uploaded['format'] === 'JSON v'.BackupService::BACKUP_VERSION
                        && $uploaded['compatible'] === true;
                });
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                if (str_starts_with($backup, 'uploaded-backup-')) {
                    $backupService->delete($backup);
                }
            }
            $backupService->delete(basename($sourceBackupPath));
        }
    }

    public function test_uploaded_version_one_backup_is_upgraded_without_archived_users(): void
    {
        $backupService = app(BackupService::class);
        $sourcePath = $backupService->create();
        $existingBackups = $backupService->listBackups();
        $contents = json_decode(Crypt::decryptString(file_get_contents($sourcePath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 1;
        $contents['created_at'] = '2020-01-02T03:04:05.123456+08:00';
        $contents['tables']['users'] = [
            User::factory()->make([
                'id' => 999999,
                'role' => 'full',
                'password' => 'ArchivedSecret#2020',
            ])->getAttributes(),
        ];
        $upload = UploadedFile::fake()->createWithContent(
            'version-one-backup.json',
            json_encode($contents, JSON_THROW_ON_ERROR),
            'application/json',
        );

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), ['backup_file' => $upload]);

            $response->assertRedirect('/admin/backups')->assertSessionHas('success');
            $uploadedBackup = collect(array_diff($backupService->listBackups(), $existingBackups))
                ->first(fn (string $name): bool => str_starts_with($name, 'uploaded-backup-'));
            $this->assertNotNull($uploadedBackup);
            $stored = json_decode(
                Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $this->assertSame(BackupService::BACKUP_VERSION, $stored['backup_version']);
            $this->assertArrayNotHasKey('users', $stored['tables']);
            $this->assertSame('2020-01-02T03:04:05.123456+08:00', $stored['created_at']);
            $backupService->restore($uploadedBackup);
            $this->assertSame(
                '2020-01-02T03:04:05.123456+08:00',
                json_decode(Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))), true, 512, JSON_THROW_ON_ERROR)['created_at'],
            );
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
            $backupService->delete(basename($sourcePath));
        }
    }

    public function test_invalid_backup_file_upload_is_rejected(): void
    {
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => 'backup-admin@example.test',
        ])->post(route('admin.backups.upload'), [
            'backup_file' => UploadedFile::fake()->create('invalid.txt', 10, 'text/plain'),
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('backup_file');
    }

    public function test_uploaded_unsupported_version_is_rejected_with_a_clear_compatibility_message(): void
    {
        $backupService = app(BackupService::class);
        $sourcePath = $backupService->create();
        $backupsBeforeUpload = $backupService->listBackups();
        $contents = json_decode(Crypt::decryptString(file_get_contents($sourcePath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 0;
        $upload = UploadedFile::fake()->createWithContent(
            'unsupported-backup.json',
            json_encode($contents, JSON_THROW_ON_ERROR),
            'application/json',
        );

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), ['backup_file' => $upload]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup_file' => 'Backup format is not compatible with the current system.',
                ]);
            $this->assertSame($backupsBeforeUpload, $backupService->listBackups());
        } finally {
            $backupService->delete(basename($sourcePath));
        }
    }
}

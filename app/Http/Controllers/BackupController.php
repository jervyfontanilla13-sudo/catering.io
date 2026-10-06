<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\AdminPasswordVerifier;
use App\Services\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(BackupService $backupService)
    {
        $backups = array_map(function (string $name) use ($backupService): array {
            $path = $backupService->pathFor($name);

            return array_merge([
                'name' => $name,
                'size' => filesize($path) ?: 0,
            ], $backupService->metadataForDisplay($name));
        }, $backupService->listBackups());
        usort($backups, static function (array $left, array $right): int {
            if ($left['sort_timestamp'] === null || $right['sort_timestamp'] === null) {
                return match (true) {
                    $left['sort_timestamp'] === null && $right['sort_timestamp'] !== null => 1,
                    $left['sort_timestamp'] !== null && $right['sort_timestamp'] === null => -1,
                    default => 0,
                };
            }

            return $right['sort_timestamp'] <=> $left['sort_timestamp'];
        });
        $timestampedBackups = array_values(array_filter(
            $backups,
            static fn (array $backup): bool => $backup['sort_timestamp'] !== null,
        ));
        $latestTimestamp = $timestampedBackups[0]['sort_timestamp'] ?? null;
        foreach (array_keys($backups) as $index) {
            $timestamp = $backups[$index]['sort_timestamp'];
            $backups[$index]['latest'] = $index === 0 && $timestamp !== null;
            $backups[$index]['older'] = $latestTimestamp !== null && $timestamp !== null && $timestamp < $latestTimestamp;
        }
        $currentBackupFormat = $backupService->currentFormat();
        $databaseStatus = $backupService->databaseStatus();
        $lastSuccessfulBackup = collect($backups)
            ->filter(static fn (array $backup): bool => $backup['validation_status'] === 'validated' && $backup['created_at'] !== null)
            ->sortByDesc(static fn (array $backup): float => $backup['sort_timestamp'])
            ->first();
        $compatibleBackupCount = collect($backups)->filter(static fn (array $backup): bool => $backup['compatible'] === true)->count();

        return view('admin.backups', compact('backups', 'currentBackupFormat', 'databaseStatus', 'lastSuccessfulBackup', 'compatibleBackupCount'));
    }

    public function checkDatabase(Request $request, BackupService $backupService)
    {
        $databaseStatus = $backupService->databaseStatus();
        if ($databaseStatus['status'] === 'healthy') {
            $this->recordBackupActivity($request, 'Database health checked', 'Confirmed that the database connection and required application tables are available.');
        }

        return back()->with('database_status', $databaseStatus);
    }

    public function createBackup(Request $request, BackupService $backupService)
    {
        if ($backupService->databaseStatus()['status'] !== 'healthy') {
            return back()->with('error', 'Database health is not Healthy. No backup was created.');
        }

        try {
            $path = $backupService->create();
        } catch (\Throwable $exception) {
            report($exception);
            if ($backupService->databaseStatus()['status'] === 'healthy') {
                $this->recordBackupActivity($request, 'Backup creation failed', 'A database backup could not be created.');
            }

            return back()->with('error', 'The database backup could not be created. Check storage permissions and the application log.');
        }

        $this->recordBackupActivity($request, 'Backup created', 'Created encrypted '.$backupService->currentFormat()['name'].' backup '.basename($path).'.');

        return back()->with('success', 'Database backup created successfully.');
    }

    public function uploadBackup(Request $request, BackupService $backupService)
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'mimetypes:application/json,text/plain,application/octet-stream', 'extensions:json,enc', 'max:20480'],
        ], [
            'backup_file.required' => 'Please select a backup file.',
            'backup_file.file' => 'The uploaded backup file is invalid.',
            'backup_file.mimetypes' => 'Unsupported backup file type.',
            'backup_file.extensions' => 'Unsupported backup file type.',
            'backup_file.max' => 'Backup file exceeds the maximum allowed size.',
        ]);

        try {
            $backupName = $backupService->upload($validated['backup_file']);
        } catch (\InvalidArgumentException $exception) {
            $this->recordBackupActivity($request, 'Backup upload failed', 'A submitted backup was rejected during verification.');

            $message = str_contains($exception->getMessage(), 'not compatible')
                ? 'Backup format is not compatible with the current system.'
                : $exception->getMessage();

            return back()->withErrors(['backup_file' => $message])->withInput();
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup upload failed', 'A submitted backup could not be stored.');

            return back()->withErrors(['backup_file' => 'The backup file is invalid or corrupted.'])->withInput();
        }

        $this->recordBackupActivity($request, 'Backup uploaded', 'Uploaded encrypted backup '.$backupName.'.');

        return back()->with('success', 'Backup uploaded successfully.')->withInput(['uploaded_backup' => $backupName]);
    }

    public function validateBackup(Request $request, BackupService $backupService)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D'],
        ]);

        if ($backupService->databaseStatus()['status'] !== 'healthy') {
            return back()->withErrors(['backup' => 'Database health must be Healthy before validation. The database was not changed.']);
        }

        try {
            $backupService->validate($data['backup']);
        } catch (\InvalidArgumentException $exception) {
            $this->recordBackupActivity($request, 'Backup validation failed', 'Validation rejected '.$data['backup'].'.');
            $message = str_contains($exception->getMessage(), 'not compatible')
                ? 'Backup validation failed. The database was not changed. This backup is incompatible with the current system.'
                : 'Backup validation failed. The database was not changed. The backup is corrupted or invalid.';

            return back()->withErrors(['backup' => $message]);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup validation failed', 'Validation could not complete for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'Backup validation failed. The database was not changed.']);
        }

        $this->recordBackupActivity($request, 'Backup validated', 'Validated backup '.$data['backup'].' for compatibility and integrity.');

        return back()->with('success', 'Backup validation passed. The backup is compatible and its integrity is verified.');
    }

    public function restoreBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D'],
            'current_admin_password' => ['required', 'string'],
        ]);

        if ($backupService->databaseStatus()['status'] !== 'healthy') {
            return back()->withErrors(['backup' => 'Database health must be Healthy before restoration can begin.']);
        }

        $isLegacy = ! $backupService->isEncrypted($data['backup']);
        if ($isLegacy && ! $request->boolean('confirm_legacy')) {
            return back()->withErrors(['backup' => 'Confirm that you understand this legacy backup is unencrypted before restoring it.'])->withInput(['backup' => $data['backup']]);
        }

        if (! $passwordVerifier->verify($request, $data['current_admin_password'])) {
            $this->recordBackupActivity($request, 'Backup restore failed', 'Password confirmation failed for a restore attempt.');

            return back()->withErrors(['current_admin_password' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $validation = $backupService->validate($data['backup']);
            $this->recordBackupActivity($request, 'Backup validated', 'Validated backup '.$data['backup'].' before restore.');
            $this->recordBackupActivity($request, 'Database restore started', 'Started restoring backup '.$data['backup'].'.');
            try {
                $safetyBackupPath = $backupService->create();
            } catch (\Throwable $exception) {
                report($exception);
                $this->recordBackupActivity($request, 'Recovery safety backup failed', 'Could not create a safety backup; restore was cancelled for '.$data['backup'].'.');
                $this->recordBackupActivity($request, 'Database restore failed', 'Restore was cancelled because the safety backup could not be created.');

                return back()->withErrors([
                    'backup' => 'A safety backup could not be created. Restoration was cancelled and the database was not changed.',
                ])->withInput(['backup' => $data['backup']]);
            }
            $this->recordBackupActivity($request, 'Recovery safety backup created', 'Created encrypted pre-restore safety backup '.basename($safetyBackupPath).'.');
            $restoredRows = $backupService->restore($data['backup']);
            $verification = $backupService->verifyRestoration($validation);
            $this->recordBackupActivity(
                $request,
                'Recovery verification completed',
                $verification['success']
                    ? 'Post-restore verification passed for '.$data['backup'].'.'
                    : 'Post-restore verification found issues for '.$data['backup'].'.',
            );

            if (! $verification['success']) {
                $this->recordBackupActivity($request, 'Database restore failed', 'Post-restore verification failed for '.$data['backup'].'.');

                return back()->withErrors([
                    'backup' => 'Database recovery completed with warnings. Review the recovery verification before normal use: '.implode(' ', $verification['warnings']),
                ])->withInput(['backup' => $data['backup']]);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->recordBackupActivity($request, 'Backup restore failed', 'Restore verification failed for '.$data['backup'].'.');
            $message = str_contains($exception->getMessage(), 'not compatible')
                ? 'Backup format is incompatible with the current system. No data was restored.'
                : 'This backup could not be verified. It may be corrupted or invalid. No data was restored.';

            return back()->withErrors(['backup' => $message])->withInput(['backup' => $data['backup']]);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup restore failed', 'Restore failed for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'The selected backup could not be restored. Verify that it is a valid backup for this database.'])->withInput(['backup' => $data['backup']]);
        }

        $safetyBackupName = basename($safetyBackupPath);
        $this->recordBackupActivity($request, 'Database restore completed', 'Restored '.$data['backup'].' ('.$restoredRows.' rows). Safety backup: '.$safetyBackupName.'.');

        return back()->with('success', "Database recovery completed successfully. Restored {$restoredRows} rows; post-restore verification passed.");
    }

    public function downloadBackup(Request $request, BackupService $backupService)
    {
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D']]);

        try {
            $path = $backupService->pathFor($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup download failed', 'A requested backup was unavailable.');

            return back()->withErrors(['backup' => 'The selected backup is unavailable.']);
        }

        $this->recordBackupActivity($request, 'Backup downloaded', 'Downloaded '.($backupService->isEncrypted($data['backup']) ? 'encrypted backup ' : 'legacy unencrypted backup ').$data['backup'].'.');

        return response()->download($path, $data['backup'], [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D'],
            'current_admin_password' => ['required', 'string'],
        ]);

        if (! $passwordVerifier->verify($request, $data['current_admin_password'])) {
            $this->recordBackupActivity($request, 'Backup deletion failed', 'Password confirmation failed for a deletion attempt.');

            return back()->withErrors(['current_admin_password' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $backupService->delete($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup deletion failed', 'Deletion failed for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'The selected backup could not be deleted.']);
        }

        $this->recordBackupActivity($request, 'Backup deleted', 'Deleted '.$data['backup'].'.');

        return back()->with('success', 'Backup deleted successfully.');
    }

    private function recordBackupActivity(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'limited'),
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
        ]);
    }
}

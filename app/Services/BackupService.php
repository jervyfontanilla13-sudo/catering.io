<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupService
{
    private const TABLES = [
        'services',
        'packages',
        'clients',
        'reservations',
        'reservation_payments',
        'reservation_refunds',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    private const LEGACY_TABLES = [
        'users',
        'services',
        'packages',
        'clients',
        'reservations',
        'reservation_payments',
        'reservation_refunds',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    private const LEGACY_REQUIRED_TABLES = [
        'services',
        'packages',
        'clients',
        'reservations',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
    ];

    private const REQUIRED_TABLES = [
        'users',
        'services',
        'packages',
        'clients',
        'reservations',
        'reservation_payments',
        'reservation_refunds',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    private const MAX_UPLOAD_SIZE = 20 * 1024 * 1024;

    public const BACKUP_FORMAT = '3YOS_JSON_BACKUP';

    public const BACKUP_VERSION = 2;

    private const VALIDATION_VERSION = 2;

    public function currentFormat(): array
    {
        return [
            'name' => 'JSON v'.self::BACKUP_VERSION,
            'identifier' => self::BACKUP_FORMAT,
            'version' => self::BACKUP_VERSION,
        ];
    }

    public function inspect(string $backup): array
    {
        $path = $this->pathFor($backup);

        try {
            $contents = $this->readBackup($backup);
        } catch (\InvalidArgumentException) {
            return [
                'format' => 'Unknown format',
                'legacy' => false,
                'compatible' => false,
                'created_at' => null,
                'sort_timestamp' => null,
            ];
        }

        $hasVersion = array_key_exists('backup_version', $contents);
        $version = $contents['backup_version'] ?? null;
        $hasKnownFormat = ! array_key_exists('backup_format', $contents)
            || $contents['backup_format'] === self::BACKUP_FORMAT;
        $format = $hasVersion && (is_int($version) || is_string($version)) && $hasKnownFormat
            ? 'JSON v'.$version
            : (! $hasVersion && $this->isRecognizedLegacyBackup($contents) ? 'Legacy format' : 'Unknown format');

        $createdAt = null;
        if (isset($contents['created_at'])
            && is_string($contents['created_at'])
            && preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $contents['created_at']) === 1) {
            try {
                $createdAt = Carbon::parse($contents['created_at'])->setTimezone(config('app.timezone'));
            } catch (\Exception) {
                $createdAt = null;
            }
        }

        try {
            $this->assertBackupCompatible($contents);
            $this->assertBackupRelationships($contents['tables']);
            $compatible = true;
        } catch (\InvalidArgumentException) {
            $compatible = false;
        }

        return [
            'format' => $format,
            'legacy' => ! $hasVersion || (is_numeric($version) && (float) $version < self::BACKUP_VERSION),
            'compatible' => $compatible,
            'created_at' => $createdAt,
            'sort_timestamp' => $createdAt === null ? null : (float) $createdAt->format('U.u'),
        ];
    }

    public function create(): string
    {
        $this->assertDatabaseReady();
        $contents = [
            'backup_format' => self::BACKUP_FORMAT,
            'backup_version' => self::BACKUP_VERSION,
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $contents['tables'][$table] = $table === 'settings'
                    ? []
                    : DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        $createdAt = now(config('app.timezone'));
        $contents['created_at'] = $createdAt->format('Y-m-d\TH:i:s.uP');

        $filename = 'backup-'.$createdAt->format('YmdHis');
        $directory = $this->privateBackupDirectory();
        $this->ensureDirectory($directory);

        $path = $directory.'/'.$filename.'.json.enc';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory.'/'.$filename.'-'.$suffix++.'.json.enc';
        }

        $json = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->writeAtomically($path, Crypt::encryptString($json));
        $this->writeMetadata(basename($path), $contents);

        return $path;
    }

    public function upload(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_SIZE) {
            throw new \InvalidArgumentException('Backup file exceeds the maximum allowed size.');
        }

        $contents = $this->decodeBackup($file);
        $this->assertBackupCompatible($contents);
        $this->assertBackupRelationships($contents['tables']);

        $contents['tables'] = $this->detachArchivedUserReferences($contents['tables']);
        unset($contents['tables']['users']);

        $directory = $this->privateBackupDirectory();
        $this->ensureDirectory($directory);

        $filename = 'uploaded-backup-'.now()->format('Ymd-His');
        $path = $directory.'/'.$filename.'.json.enc';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory.'/'.$filename.'-'.$suffix++.'.json.enc';
        }

        $contents['backup_format'] = self::BACKUP_FORMAT;
        $contents['backup_version'] = self::BACKUP_VERSION;
        $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->writeAtomically($path, Crypt::encryptString($encoded));
        $this->writeMetadata(basename($path), $contents);

        return basename($path);
    }

    public function restore(string $backup): int
    {
        $this->assertDatabaseReady();
        $contents = $this->readBackup($backup);
        $this->assertBackupCompatible($contents);
        $this->assertBackupRelationships($contents['tables']);

        $tables = $this->detachArchivedUserReferences($contents['tables']);

        $restoreTables = array_values(array_filter(
            self::TABLES,
            fn ($table) => $table !== 'settings' && array_key_exists($table, $tables) && Schema::hasTable($table),
        ));
        $rowsByTable = [];
        $columnsByTable = [];
        foreach ($restoreTables as $table) {
            $rows = $tables[$table];
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new \RuntimeException("Invalid data for {$table}.");
            }

            $columnsByTable[$table] = array_flip(Schema::getColumnListing($table));
            $rowsByTable[$table] = array_map(function ($row) use ($table, $columnsByTable) {
                if (! is_array($row)) {
                    throw new \RuntimeException("Invalid row data for {$table}.");
                }

                $compatibleRow = array_intersect_key($row, $columnsByTable[$table]);
                if ($compatibleRow === []) {
                    throw new \RuntimeException("No compatible columns were found for {$table}.");
                }

                return $compatibleRow;
            }, $rows);
        }
        $restoredRows = 0;

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($restoreTables, $rowsByTable, &$restoredRows): void {
                // Backups made before payment history existed carry no payment rows; clear the current
                // ones so they cannot attach to different restored reservations with the same ids.
                if (in_array('reservations', $restoreTables, true) && ! in_array('reservation_payments', $restoreTables, true) && Schema::hasTable('reservation_payments')) {
                    DB::table('reservation_payments')->delete();
                }
                if (in_array('reservations', $restoreTables, true) && ! in_array('reservation_refunds', $restoreTables, true) && Schema::hasTable('reservation_refunds')) {
                    DB::table('reservation_refunds')->delete();
                }

                foreach (array_reverse($restoreTables) as $table) {
                    if ($table !== 'activity_logs') {
                        DB::table($table)->delete();
                    }
                }

                foreach ($restoreTables as $table) {
                    foreach (array_chunk($rowsByTable[$table], 500) as $chunk) {
                        if ($chunk !== []) {
                            if ($table === 'activity_logs') {
                                foreach ($chunk as $row) {
                                    if (! DB::table($table)->where($row)->exists()) {
                                        DB::table($table)->insert($row);
                                        $restoredRows++;
                                    }
                                }
                            } else {
                                DB::table($table)->insert($chunk);
                                $restoredRows += count($chunk);
                            }
                        }
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $restoredRows;
    }

    public function validate(string $backup): array
    {
        $this->assertDatabaseReady();
        $contents = $this->readBackup($backup);
        $this->assertBackupCompatible($contents);
        $this->assertBackupRelationships($contents['tables']);
        $this->writeMetadata($backup, $contents);

        $definition = $this->backupDefinition($contents);
        $expectedRows = [];
        foreach ($contents['tables'] as $table => $rows) {
            if (! in_array($table, ['activity_logs', 'settings', 'users'], true)) {
                $expectedRows[$table] = count($rows);
            }
        }
        if (array_key_exists('reservations', $contents['tables'])) {
            foreach (['reservation_payments', 'reservation_refunds'] as $table) {
                if (! array_key_exists($table, $contents['tables'])) {
                    $expectedRows[$table] = 0;
                }
            }
        }
        $archivedTables = $this->detachArchivedUserReferences($contents['tables']);

        return [
            'expected_rows' => $expectedRows,
            'expected_tables' => $definition['required_tables'],
            'required_columns' => $this->requiredColumnsForTables(array_keys($contents['tables'])),
            'expected_activity_logs' => $archivedTables['activity_logs'] ?? [],
            'protected_administrators' => $this->administratorProtectionState(),
        ];
    }

    public function metadataForDisplay(string $backup): array
    {
        $backupPath = $this->pathFor($backup);
        $metadataPath = $this->metadataPath($backup);

        if (is_file($metadataPath)) {
            try {
                $encryptedMetadata = file_get_contents($metadataPath);
                if ($encryptedMetadata === false) {
                    return $this->unknownMetadata($backup);
                }
                $metadata = json_decode(Crypt::decryptString($encryptedMetadata), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($metadata)) {
                    $metadata = array_merge($this->unknownMetadata($backup), $metadata);
                    clearstatcache(true, $backupPath);
                    if (($metadata['file_size'] ?? null) !== (filesize($backupPath) ?: 0)
                        || ($metadata['file_modified_at'] ?? null) !== (filemtime($backupPath) ?: 0)) {
                        return $this->unknownMetadata($backup);
                    }
                    if (($metadata['validation_version'] ?? null) !== self::VALIDATION_VERSION) {
                        return $this->unknownMetadata($backup);
                    }
                    $metadata['created_at'] = $this->parseCreationTime($metadata['created_at']);
                    $metadata['sort_timestamp'] = $metadata['created_at'] === null
                        ? null
                        : (float) $metadata['created_at']->format('U.u');

                    return $metadata;
                }
            } catch (DecryptException|\JsonException) {
                // Metadata is only a display cache; an explicit validation will rebuild it.
            }
        }

        return $this->unknownMetadata($backup);
    }

    public function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return [
                'status' => 'unavailable',
                'label' => 'Unavailable',
                'message' => 'The application could not connect to the database. Database recovery requires server or hosting access.',
            ];
        }

        try {
            $missingTables = array_values(array_filter(
                $this->requiredTables(),
                static fn (string $table): bool => ! Schema::hasTable($table),
            ));
        } catch (\Throwable) {
            return [
                'status' => 'connection_error',
                'label' => 'Connection Error',
                'message' => 'The database connection could not be checked safely.',
            ];
        }

        if ($missingTables !== []) {
            return [
                'status' => 'recovery_required',
                'label' => 'Recovery Required',
                'message' => 'The database is reachable, but required application tables are missing. Review migrations before restoring.',
            ];
        }

        return [
            'status' => 'healthy',
            'label' => 'Healthy',
            'message' => 'The database connection and required application tables are available.',
        ];
    }

    public function verifyRestoration(array $expectations): array
    {
        $issues = [];
        $expectedTables = $expectations['expected_tables'] ?? [];
        $requiredColumns = $expectations['required_columns'] ?? [];
        $expectedRows = $expectations['expected_rows'] ?? [];

        foreach ($expectedTables as $table) {
            if (! Schema::hasTable($table)) {
                $issues[] = 'A required backup table is missing after restoration.';

                continue;
            }

            $actualColumns = Schema::getColumnListing($table);
            foreach ($requiredColumns[$table] ?? [] as $column) {
                if (! in_array($column, $actualColumns, true)) {
                    $issues[] = 'A required table column is missing after restoration.';
                    break;
                }
            }
        }

        foreach ($expectedRows as $table => $expectedCount) {
            if (! Schema::hasTable($table) || DB::table($table)->count() !== $expectedCount) {
                $issues[] = 'Restored data counts do not match the validated backup.';
                break;
            }
        }

        if (Schema::hasTable('reservations') && $this->hasDuplicateReservationCodes()) {
            $issues[] = 'Duplicate reservation codes were found.';
        }

        foreach ($this->databaseRelationshipIssues() as $issue) {
            $issues[] = $issue;
        }

        foreach ($expectations['expected_activity_logs'] ?? [] as $row) {
            if (! Schema::hasTable('activity_logs')) {
                $issues[] = 'A required archived activity record is missing.';
                break;
            }
            $row = array_intersect_key($row, array_flip(Schema::getColumnListing('activity_logs')));
            if ($row !== [] && ! DB::table('activity_logs')->where($row)->exists()) {
                $issues[] = 'An archived activity record was not restored.';
                break;
            }
        }

        if (isset($expectations['protected_administrators'])
            && $expectations['protected_administrators'] !== $this->administratorProtectionState()) {
            $issues[] = 'Administrator accounts changed during restoration.';
        }

        return [
            'success' => $issues === [],
            'warnings' => array_values(array_unique($issues)),
        ];
    }

    public function pathFor(string $backup): string
    {
        if (preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D', $backup) === 1) {
            foreach ([$this->privateBackupDirectory(), storage_path('app/backups')] as $directory) {
                $path = $directory.'/'.$backup;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        throw new \InvalidArgumentException('Invalid backup file.');
    }

    public function delete(string $backup): void
    {
        $path = $this->pathFor($backup);
        $metadataPath = $this->metadataPath($backup);
        if (is_file($metadataPath) && ! unlink($metadataPath)) {
            throw new \RuntimeException('The selected backup metadata could not be deleted.');
        }
        if (! unlink($path)) {
            throw new \RuntimeException('The selected backup could not be deleted.');
        }
    }

    public function listBackups(): array
    {
        $files = [];
        foreach ([$this->privateBackupDirectory(), storage_path('app/backups')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            $entries = scandir($directory);
            if ($entries !== false) {
                $files = array_merge($files, array_filter(
                    $entries,
                    fn (string $file) => preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D', $file) === 1
                ));
            }
        }

        $files = array_values(array_unique($files));
        rsort($files, SORT_STRING);

        return $files;
    }

    private function decodeBackup(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if (strtolower($file->getClientOriginalExtension()) === 'enc') {
            $contents = $this->decryptBackup($contents);
        } elseif (strtolower($file->getClientOriginalExtension()) !== 'json') {
            throw new \InvalidArgumentException('Unsupported backup file type.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        return $decoded;
    }

    private function assertBackupCompatible(array $contents): void
    {
        $hasFormat = array_key_exists('backup_format', $contents);
        $hasVersion = array_key_exists('backup_version', $contents);
        $version = $contents['backup_version'] ?? null;

        if (($hasFormat && $contents['backup_format'] !== self::BACKUP_FORMAT)
            || ($hasVersion && ! in_array($version, [1, self::BACKUP_VERSION], true))
            || ($hasFormat && ! $hasVersion)) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        if (! $hasVersion && ! $this->isRecognizedLegacyBackup($contents)) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        if (! array_key_exists('tables', $contents) || ! is_array($contents['tables'])) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        $tables = $contents['tables'];
        $definition = $this->backupDefinition($contents);
        $unexpectedTables = array_diff(array_keys($tables), $definition['allowed_tables']);
        if ($unexpectedTables !== []) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        foreach ($definition['required_tables'] as $table) {
            if (! array_key_exists($table, $tables)) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current system: a required table is missing.');
            }
        }

        $legacyLedgerMissing = ! array_key_exists('reservation_payments', $tables)
            && ! array_key_exists('reservation_refunds', $tables)
            && in_array('reservations', $tables, true)
            && (array_key_exists('backup_version', $contents) ? (int) $contents['backup_version'] === self::BACKUP_VERSION : true);
        if ($legacyLedgerMissing) {
            foreach (['reservation_payments', 'reservation_refunds'] as $table) {
                if (array_key_exists($table, $tables)) {
                    continue;
                }
                if (in_array($table, $definition['required_tables'], true)) {
                    $definition['required_tables'] = array_values(array_filter(
                        $definition['required_tables'],
                        static fn (string $requiredTable): bool => $requiredTable !== $table,
                    ));
                }
            }
        }

        foreach ($definition['required_tables'] as $table) {
            if (! array_key_exists($table, $tables)) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current system: a required table is missing.');
            }
        }

        if ($hasVersion && $version === self::BACKUP_VERSION) {
            if (! $this->hasTimezoneAwareCreationTime($contents['created_at'] ?? null)) {
                throw new \InvalidArgumentException('The backup file is invalid or corrupted: required metadata is missing.');
            }
        } elseif (array_key_exists('created_at', $contents)
            && (! is_string($contents['created_at']) || strtotime($contents['created_at']) === false)) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted: creation metadata is invalid.');
        }

        foreach ($tables as $table => $rows) {
            if (! is_string($table) || ! in_array($table, $definition['allowed_tables'], true)) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
            }
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new \InvalidArgumentException("The backup file is invalid or corrupted for {$table}.");
            }
            if (! Schema::hasTable($table)) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
            }

            $requiredColumns = $this->requiredColumnsForTable($table);
            $availableColumns = Schema::getColumnListing($table);
            if (array_diff($requiredColumns, $availableColumns) !== []) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current database schema.');
            }

            if ($table === 'settings' && $rows !== []) {
                throw new \InvalidArgumentException('The backup contains unsupported settings values.');
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new \InvalidArgumentException("The backup file contains an invalid row for {$table}.");
                }
                foreach ($requiredColumns as $column) {
                    if (! array_key_exists($column, $row) || $row[$column] === null) {
                        throw new \InvalidArgumentException("The backup file contains an incomplete {$column} field for {$table}.");
                    }
                }
                if (array_key_exists('service_contracts', $row)
                    && $row['service_contracts'] !== null
                    && ! $this->isValidContractList($row['service_contracts'])) {
                    throw new \InvalidArgumentException('The backup contains invalid contract references.');
                }
            }
        }
    }

    private function backupDefinition(array $contents): array
    {
        $tables = $contents['tables'] ?? [];
        $version = $contents['backup_version'] ?? null;

        if (! array_key_exists('backup_version', $contents)) {
            return [
                'required_tables' => self::LEGACY_REQUIRED_TABLES,
                'allowed_tables' => self::LEGACY_TABLES,
            ];
        }

        $requiredTables = self::TABLES;
        $allowedTables = $version === 1 ? self::LEGACY_TABLES : self::TABLES;

        $legacyLedgerMissing = ! array_key_exists('reservation_payments', $tables)
            && ! array_key_exists('reservation_refunds', $tables)
            && array_key_exists('reservations', $tables)
            && $version === self::BACKUP_VERSION;

        if ($legacyLedgerMissing) {
            $requiredTables = array_values(array_filter(
                $requiredTables,
                static fn (string $table): bool => ! in_array($table, ['reservation_payments', 'reservation_refunds'], true),
            ));
        }

        return [
            'required_tables' => $requiredTables,
            'allowed_tables' => $allowedTables,
        ];
    }

    private function requiredColumnsForTable(string $table): array
    {
        if ($table === 'users') {
            return ['id', 'name', 'email'];
        }

        $requiredColumns = [];
        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] === 'id'
                || (! $column['nullable'] && ($column['default'] ?? null) === null && ! ($column['auto_increment'] ?? false))) {
                $requiredColumns[] = $column['name'];
            }
        }

        return array_values(array_unique($requiredColumns));
    }

    private function requiredColumnsForTables(array $tables): array
    {
        $columns = [];
        foreach ($tables as $table) {
            $columns[$table] = $this->requiredColumnsForTable($table);
        }

        return $columns;
    }

    private function hasTimezoneAwareCreationTime(mixed $createdAt): bool
    {
        if (! is_string($createdAt)
            || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $createdAt) !== 1) {
            return false;
        }

        return $this->parseCreationTime($createdAt) !== null;
    }

    private function isValidContractList(mixed $value): bool
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }
        }

        return is_array($value)
            && array_is_list($value)
            && count(array_filter($value, static fn ($path): bool => is_string($path) && $path !== '')) === count($value);
    }

    private function assertDatabaseReady(): void
    {
        if ($this->databaseStatus()['status'] !== 'healthy') {
            throw new \RuntimeException('The database is not ready for backup or recovery operations.');
        }
    }

    private function requiredTables(): array
    {
        $tables = self::REQUIRED_TABLES;

        if (config('session.driver') === 'database') {
            $tables[] = (string) config('session.table', 'sessions');
        }

        $cacheStore = config('cache.stores.'.config('cache.default'));
        if (($cacheStore['driver'] ?? null) === 'database') {
            $tables[] = (string) ($cacheStore['table'] ?? 'cache');
            $tables[] = (string) ($cacheStore['lock_table'] ?? 'cache_locks');
        }

        $queueConnection = config('queue.connections.'.config('queue.default'));
        if (($queueConnection['driver'] ?? null) === 'database') {
            $tables[] = (string) ($queueConnection['table'] ?? 'jobs');
            $tables[] = (string) config('queue.batching.table', 'job_batches');
            if (str_starts_with((string) config('queue.failed.driver'), 'database')) {
                $tables[] = (string) config('queue.failed.table', 'failed_jobs');
            }
        }

        return array_values(array_unique($tables));
    }

    private function assertBackupRelationships(array $tables): void
    {
        $idsFor = static function (string $table) use ($tables): array {
            $ids = [];
            foreach ($tables[$table] ?? [] as $row) {
                $id = (string) $row['id'];
                if (isset($ids[$id])) {
                    throw new \InvalidArgumentException("The backup contains duplicate {$table} identifiers.");
                }
                $ids[$id] = true;
            }

            return $ids;
        };

        $reservations = $idsFor('reservations');
        $payments = $idsFor('reservation_payments');
        $clients = $idsFor('clients');
        $packages = $idsFor('packages');
        $reservationCodes = [];

        foreach ($tables['reservations'] ?? [] as $row) {
            $code = $row['reservation_code'] ?? null;
            if (is_string($code) && $code !== '') {
                if (isset($reservationCodes[$code])) {
                    throw new \InvalidArgumentException('The backup contains duplicate reservation codes.');
                }
                $reservationCodes[$code] = true;
            }
            if (($row['client_id'] ?? null) !== null && array_key_exists('clients', $tables)
                && ! isset($clients[(string) $row['client_id']])) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
            if (($row['package_id'] ?? null) !== null && array_key_exists('packages', $tables)
                && ! isset($packages[(string) $row['package_id']])) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
        }

        foreach ($tables['reservation_payments'] ?? [] as $row) {
            if (array_key_exists('reservations', $tables)
                && ! isset($reservations[(string) $row['reservation_id']])) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
        }

        foreach ($tables['reservation_refunds'] ?? [] as $row) {
            if (array_key_exists('reservations', $tables)
                && ! isset($reservations[(string) $row['reservation_id']])) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
            if (! array_key_exists('reservation_payments', $tables) && ($row['payment_id'] ?? null) !== null) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
            if (($row['payment_id'] ?? null) !== null && array_key_exists('reservation_payments', $tables)
                && ! isset($payments[(string) $row['payment_id']])) {
                throw new \InvalidArgumentException('The backup contains an invalid relationship.');
            }
        }
    }

    private function hasDuplicateReservationCodes(): bool
    {
        return DB::table('reservations')
            ->select('reservation_code')
            ->whereNotNull('reservation_code')
            ->groupBy('reservation_code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function databaseRelationshipIssues(): array
    {
        $issues = [];
        foreach ([
            ['reservations', 'client_id', 'clients', true],
            ['reservations', 'package_id', 'packages', true],
            ['reservation_payments', 'reservation_id', 'reservations', false],
            ['reservation_refunds', 'reservation_id', 'reservations', false],
        ] as [$child, $foreignKey, $parent, $nullable]) {
            if (! Schema::hasTable($child) || ! Schema::hasTable($parent) || ! Schema::hasColumn($child, $foreignKey)) {
                continue;
            }
            $query = DB::table($child)->leftJoin($parent, $child.'.'.$foreignKey, '=', $parent.'.id');
            if ($nullable) {
                $query->whereNotNull($child.'.'.$foreignKey);
            }
            if ($query->whereNull($parent.'.id')->exists()) {
                $issues[] = 'A restored reservation, payment, or refund has no matching parent record.';
                break;
            }
        }

        if (Schema::hasTable('reservation_refunds')
            && Schema::hasTable('reservation_payments')
            && Schema::hasColumn('reservation_refunds', 'payment_id')
            && DB::table('reservation_refunds')->whereNotNull('payment_id')
                ->leftJoin('reservation_payments', 'reservation_refunds.payment_id', '=', 'reservation_payments.id')
                ->whereNull('reservation_payments.id')->exists()) {
            $issues[] = 'A restored refund has no matching payment.';
        }

        return $issues;
    }

    private function administratorProtectionState(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $availableColumns = Schema::getColumnListing('users');
        $protectedColumns = array_values(array_intersect(
            ['id', 'email', 'password', 'role', 'is_active', 'session_version'],
            $availableColumns,
        ));

        return DB::table('users')->orderBy('id')->get($protectedColumns)->map(
            static fn ($user): array => (array) $user,
        )->all();
    }

    private function isRecognizedLegacyBackup(array $contents): bool
    {
        if (array_key_exists('backup_format', $contents)
            || array_key_exists('backup_version', $contents)
            || array_diff(array_keys($contents), ['created_at', 'tables']) !== []
            || ! isset($contents['created_at'])
            || ! is_string($contents['created_at'])
            || strtotime($contents['created_at']) === false) {
            return false;
        }

        return is_array($contents['tables']);
    }

    private function detachArchivedUserReferences(array $tables): array
    {
        $usersById = collect($tables['users'] ?? [])->keyBy('id');

        foreach ($tables['activity_logs'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $backupUser = $usersById->get($row['user_id'] ?? null);
            foreach (['name' => 'actor_name', 'email' => 'actor_email', 'role' => 'actor_role'] as $userField => $actorField) {
                if (empty($row[$actorField]) && is_array($backupUser) && isset($backupUser[$userField])) {
                    $row[$actorField] = $backupUser[$userField];
                }
            }

            $row['user_id'] = null;
            unset($row['id']);
            $tables['activity_logs'][$index] = $row;
        }

        return $tables;
    }

    public function isEncrypted(string $backup): bool
    {
        return str_ends_with($backup, '.json.enc');
    }

    private function readBackup(string $backup): array
    {
        $raw = file_get_contents($this->pathFor($backup));
        if ($raw === false) {
            throw new \RuntimeException('The selected backup could not be read.');
        }
        if ($this->isEncrypted($backup)) {
            $raw = $this->decryptBackup($raw);
        }

        try {
            $contents = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }

        if (! is_array($contents)) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }

        return $contents;
    }

    private function decryptBackup(string $contents): string
    {
        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }
    }

    private function privateBackupDirectory(): string
    {
        return storage_path('app/private/backups');
    }

    private function metadataPath(string $backup): string
    {
        return $this->privateBackupDirectory().'/.metadata/'.basename($backup).'.meta.enc';
    }

    private function writeMetadata(string $backup, array $contents): void
    {
        $metadataDirectory = $this->privateBackupDirectory().'/.metadata';
        $this->ensureDirectory($metadataDirectory);
        $backupPath = $this->pathFor($backup);
        clearstatcache(true, $backupPath);
        $createdAt = isset($contents['created_at']) && is_string($contents['created_at'])
            ? $contents['created_at']
            : null;
        $metadata = [
            'format' => isset($contents['backup_version'])
                ? 'JSON v'.$contents['backup_version']
                : 'Legacy format',
            'version' => $contents['backup_version'] ?? null,
            'created_at' => $createdAt,
            'compatible' => true,
            'validation_status' => 'validated',
            'validation_version' => self::VALIDATION_VERSION,
            'validated_at' => now()->toIso8601String(),
            'encrypted' => $this->isEncrypted($backup),
            'legacy' => ! isset($contents['backup_version'])
                || (is_numeric($contents['backup_version']) && (float) $contents['backup_version'] < self::BACKUP_VERSION),
            'file_size' => filesize($backupPath) ?: 0,
            'file_modified_at' => filemtime($backupPath) ?: 0,
        ];
        $this->writeAtomically(
            $this->metadataPath($backup),
            Crypt::encryptString(json_encode($metadata, JSON_THROW_ON_ERROR)),
        );
    }

    private function unknownMetadata(string $backup): array
    {
        return [
            'format' => 'Not validated',
            'version' => null,
            'created_at' => null,
            'compatible' => null,
            'validation_status' => 'unknown',
            'validated_at' => null,
            'encrypted' => $this->isEncrypted($backup),
            'legacy' => false,
            'sort_timestamp' => null,
        ];
    }

    private function parseCreationTime(mixed $createdAt): ?Carbon
    {
        if (! is_string($createdAt) || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $createdAt) !== 1) {
            return null;
        }

        try {
            return Carbon::parse($createdAt)->setTimezone(config('app.timezone'));
        } catch (\Exception) {
            return null;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('The backup directory could not be created.');
        }
    }

    private function writeAtomically(string $path, string $contents): void
    {
        $temporaryPath = $path.'.'.Str::uuid().'.tmp';
        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $path)) {
                throw new \RuntimeException('The backup file could not be written.');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}

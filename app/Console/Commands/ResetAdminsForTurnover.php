<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetAdminsForTurnover extends Command
{
    protected $signature = 'admin:reset-for-turnover {--force : Confirm removal without an interactive prompt}';

    protected $description = 'Remove administrator accounts while preserving business data and audit history';

    public function handle(): int
    {
        $admins = User::query()
            ->whereIn('role', ['full', 'limited'])
            ->get(['id', 'name', 'email', 'role']);
        $counts = $admins->countBy('role');

        $this->info('3YOS Catering Management System');
        $this->warn('ADMIN TURNOVER RESET');
        $this->line('Primary/Admin accounts to remove: '.($counts->get('full', 0)));
        $this->line('Team Admin accounts to remove: '.($counts->get('limited', 0)));
        $this->line('Business data and uploaded files will NOT be deleted.');
        $this->line('Existing activity records will be preserved with actor snapshots.');

        if ($admins->isEmpty()) {
            $this->info('Administrator accounts removed: 0');
            $this->info('Primary Admin available for setup: '.(User::where('role', 'full')->where('is_active', true)->exists() ? 'NO' : 'YES'));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Continue and remove these administrator accounts?', false)) {
            $this->comment('Turnover reset cancelled. No database changes were made.');

            return self::SUCCESS;
        }

        $removed = DB::transaction(function () use ($admins): int {
            foreach ($admins as $admin) {
                ActivityLog::query()
                    ->where('user_id', $admin->id)
                    ->get(['id', 'actor_name', 'actor_email', 'actor_role'])
                    ->each(function (ActivityLog $log) use ($admin): void {
                        $log->update([
                            'user_id' => null,
                            'actor_name' => $log->actor_name ?: $admin->name,
                            'actor_email' => $log->actor_email ?: $admin->email,
                            'actor_role' => $log->actor_role ?: $admin->role,
                        ]);
                    });

                DB::table('reservation_payments')
                    ->where('recorded_by_user_id', $admin->id)
                    ->where(fn ($query) => $query->whereNull('recorded_by_name')->orWhere('recorded_by_name', ''))
                    ->update(['recorded_by_name' => $admin->name]);

                DB::table('reservation_refunds')
                    ->where('recorded_by_user_id', $admin->id)
                    ->where(fn ($query) => $query->whereNull('recorded_by_name')->orWhere('recorded_by_name', ''))
                    ->update(['recorded_by_name' => $admin->name]);
            }

            $ids = $admins->pluck('id');
            $removed = User::query()->whereIn('id', $ids)->whereIn('role', ['full', 'limited'])->delete();

            ActivityLog::create([
                'user_id' => null,
                'actor_name' => 'System',
                'actor_role' => 'system',
                'action' => 'Administrator turnover reset',
                'method' => 'CLI',
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => "Removed {$removed} administrator account(s) for beneficiary turnover. Business data and historical activity records were preserved.",
            ]);

            return $removed;
        });

        $remaining = User::query()->whereIn('role', ['full', 'limited'])->count();
        $setupAvailable = ! User::query()->where('role', 'full')->where('is_active', true)->exists();

        $this->info("Administrator accounts removed: {$removed}");
        $this->info('Business data preserved: YES');
        $this->info("Administrator accounts remaining: {$remaining}");
        $this->info('Primary Admin available for setup: '.($setupAvailable ? 'YES' : 'NO'));

        return $remaining === 0 && $setupAvailable ? self::SUCCESS : self::FAILURE;
    }
}

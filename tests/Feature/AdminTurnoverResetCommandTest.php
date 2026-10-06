<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminTurnoverResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_removes_only_admin_accounts_and_preserves_activity_history(): void
    {
        $primary = User::factory()->create(['role' => 'full', 'name' => 'Old Primary']);
        $teamAdmin = User::factory()->create(['role' => 'limited', 'name' => 'Old Team Admin']);
        $otherRole = User::factory()->create(['role' => 'customer']);
        $history = ActivityLog::create([
            'user_id' => $teamAdmin->id,
            'action' => 'Historical action',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Preserve this history.',
        ]);
        $originalHistoryCount = ActivityLog::count();

        $exitCode = Artisan::call('admin:reset-for-turnover', ['--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseMissing('users', ['id' => $primary->id]);
        $this->assertDatabaseMissing('users', ['id' => $teamAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherRole->id, 'role' => 'customer']);
        $this->assertDatabaseHas('activity_logs', [
            'id' => $history->id,
            'user_id' => null,
            'actor_name' => 'Old Team Admin',
            'actor_email' => $teamAdmin->email,
            'actor_role' => 'limited',
        ]);
        $this->assertSame($originalHistoryCount + 1, ActivityLog::count());
        $this->assertSame(0, User::whereIn('role', ['full', 'limited'])->count());
    }

    public function test_command_can_be_cancelled_without_changing_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'full']);

        $this->artisan('admin:reset-for-turnover')
            ->expectsConfirmation('Continue and remove these administrator accounts?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }
}

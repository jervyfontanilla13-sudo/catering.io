<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AdminPasswordVerifier;
use App\Support\AdminPasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::orderBy('name')->get(),
            'currentAdminName' => session('admin_name'),
        ]);
    }

    public function store(Request $request, AdminPasswordVerifier $passwordVerifier): RedirectResponse
    {
        $currentAdminPassword = $request->input('current_admin_password');
        $request->request->remove('current_admin_password');
        $request->json()->remove('current_admin_password');
        $request->query->remove('current_admin_password');
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && User::query()->whereRaw('LOWER(email) = ?', [$value])->exists()) {
                        $fail('That email address is already in use.');
                    }
                },
            ],
            'password' => AdminPasswordRules::rules(),
            'role' => ['required', 'in:full,limited'],
        ], [
            'email.unique' => 'That email address is already in use.',
        ] + AdminPasswordRules::messages());

        if (! is_string($currentAdminPassword) || $currentAdminPassword === '') {
            return back()
                ->withErrors(['current_admin_password' => 'Enter your current Primary Admin password to authorize creating this administrator.'])
                ->withInput($request->except(['password', 'password_confirmation', 'current_admin_password']));
        }

        $adminId = $request->session()->get('admin_user_id');
        $throttleKey = 'admin-account-create:'.$adminId.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withErrors(['current_admin_password' => 'Too many password confirmation attempts. Please try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minute(s).'])
                ->withInput($request->except(['password', 'password_confirmation', 'current_admin_password']));
        }

        $roleLabel = $data['role'] === 'full' ? 'Primary Admin' : 'Team Admin';

        $created = DB::transaction(function () use ($request, $data, $currentAdminPassword, $passwordVerifier, $roleLabel): User|string {
            $actor = User::query()
                ->whereKey($request->session()->get('admin_user_id'))
                ->lockForUpdate()
                ->first();

            if (! $actor
                || $actor->role !== 'full'
                || $actor->is_active !== true
                || (int) $actor->session_version !== (int) $request->session()->get('admin_session_version', 0)) {
                return 'invalid_session';
            }

            if (! $passwordVerifier->verifyUser($actor, $currentAdminPassword)) {
                return 'invalid_password';
            }

            $newAdmin = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => true,
            ]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role,
                'action' => 'Administrator created',
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Created '.$roleLabel.' account for '.$newAdmin->name.' ('.$newAdmin->email.').',
            ]);

            return $newAdmin;
        });

        if ($created === 'invalid_session') {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your Primary Admin session is no longer valid. Please sign in again.');
        }

        if ($created === 'invalid_password') {
            RateLimiter::hit($throttleKey, 300);

            return back()
                ->withErrors(['current_admin_password' => 'Primary Admin password is incorrect.'])
                ->withInput($request->except(['password', 'password_confirmation', 'current_admin_password']));
        }

        RateLimiter::clear($throttleKey);

        return back()->with('success', $roleLabel.' created successfully.');
    }

    public function updateName(Request $request, User $user): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\pM][\\pL\\pM\\s.\\x27’\\-]*$/u"],
        ]);
        $oldName = $user->name;
        $newName = $data['name'];

        if ($oldName === $newName) {
            return back()->with('success', 'Administrator name is unchanged.');
        }

        DB::transaction(function () use ($request, $user, $oldName, $newName): void {
            $user->update(['name' => $newName]);
            $this->recordManagementActivity(
                $request,
                'Changed administrator name',
                "Changed {$this->targetRoleLabel($user)} name from '{$oldName}' to '{$newName}'."
            );
        });

        return back()->with('success', 'Administrator name updated successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $enable = (bool) $data['is_active'];

        if (! $enable && $user->role === 'full'
            && User::query()->where('role', 'full')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Cannot disable the last active Primary Admin. At least one active Primary Admin is required.');
        }

        if (! $enable && (
            (string) $request->session()->get('admin_user_id') === (string) $user->id
            || strcasecmp((string) $request->session()->get('admin_email'), $user->email) === 0
        )) {
            return back()->with('error', 'You cannot disable your own administrator account.');
        }

        if ($user->is_active === $enable) {
            return back()->with('success', 'Administrator account is already '.($enable ? 'active.' : 'disabled.'));
        }

        $error = DB::transaction(function () use ($request, $user, $enable): ?string {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $enable && $lockedUser->role === 'full') {
                $activePrimaryAdmins = User::query()
                    ->where('role', 'full')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count();

                if ($activePrimaryAdmins <= 1) {
                    return 'Cannot disable the last active Primary Admin. At least one active Primary Admin is required.';
                }
            }

            $lockedUser->update([
                'is_active' => $enable,
                'session_version' => $lockedUser->session_version + ($enable ? 0 : 1),
            ]);
            $this->recordManagementActivity(
                $request,
                $enable ? 'Enabled administrator' : 'Disabled administrator',
                ($request->session()->get('admin_name', 'Primary Admin'))
                    .' '.($enable ? 'enabled ' : 'disabled ')
                    .$this->targetRoleLabel($lockedUser).' '.$lockedUser->name.'.'
            );

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Administrator account '.($enable ? 'enabled' : 'disabled').' successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => AdminPasswordRules::rules(),
        ], AdminPasswordRules::messages());

        $user->update([
            'password' => Hash::make($data['password']),
            'session_version' => $user->session_version + 1,
        ]);

        $this->recordManagementActivity($request, 'Changed administrator password', 'Updated administrator password. Existing sessions were revoked.');

        return back()->with('success', 'Password updated for ' . $user->name . '.');
    }

    private function recordManagementActivity(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'full'),
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
        ]);
    }

    private function targetRoleLabel(User $user): string
    {
        return $user->role === 'full' ? 'Primary Admin' : 'Team Admin';
    }
}

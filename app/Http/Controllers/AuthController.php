<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminPasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        return view('admin.login', [
            'setupAvailable' => ! $this->hasPrimaryAdmin()
                && $this->hasSetupKey()
                && ! $this->isTeamAdminSession($request),
        ]);
    }

    public function showPrimaryAdminSetup(Request $request)
    {
        abort_if($this->isTeamAdminSession($request), 403);

        return view('admin.setup', [
            'setupComplete' => $this->hasPrimaryAdmin(),
            'setupAvailable' => $this->hasSetupKey(),
        ]);
    }

    public function createPrimaryAdmin(Request $request)
    {
        abort_if($this->isTeamAdminSession($request), 403);

        if ($this->hasPrimaryAdmin()) {
            return redirect()->route('admin.setup')->with('error', 'Primary Admin setup has already been completed.');
        }

        if (! $this->hasSetupKey()) {
            return redirect()->route('admin.setup')->with('error', 'Primary Admin setup is not available. Contact the system administrator.');
        }

        $providedSetupKey = $request->input('setup_key');
        if (! is_string($providedSetupKey)
            || strlen($providedSetupKey) > 512
            || ! hash_equals((string) config('admin.primary_admin_setup_key'), $providedSetupKey)) {
            return back()->with('error', 'The setup key is invalid.');
        }
        $request->request->remove('setup_key');
        $request->json()->remove('setup_key');

        $name = $request->input('name');
        $email = $request->input('email');
        $request->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\\pL\\pM][\\pL\\pM\\s.\\x27’\\-]*$/u'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if (User::query()->whereRaw('LOWER(email) = ?', [$value])->exists()) {
                        $fail('That email address is already in use.');
                    }
                },
            ],
            'password' => AdminPasswordRules::rules(),
        ], AdminPasswordRules::messages());

        try {
            $user = Cache::lock('3yos-primary-admin-initial-setup', 30)->block(5, function () use ($data): User {
                return DB::transaction(function () use ($data): User {
                    $primaryAdmins = User::query()->where('role', 'full')->lockForUpdate()->exists();
                    if ($primaryAdmins) {
                        throw new \DomainException('Primary Admin setup has already been completed.');
                    }

                    User::query()->lockForUpdate()->orderBy('id')->get(['id']);

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                        'role' => 'full',
                        'is_active' => true,
                    ]);

                    ActivityLog::create([
                        'user_id' => $user->id,
                        'actor_name' => $user->name,
                        'actor_email' => $user->email,
                        'actor_role' => 'full',
                        'action' => 'Primary Admin account created',
                        'method' => 'POST',
                        'ip_address' => request()->ip(),
                        'activity_date' => now()->toDateString(),
                        'activity_time' => now()->toTimeString(),
                        'description' => 'Initial Primary Admin account created during system setup.',
                    ]);

                    return $user;
                });
            });
        } catch (LockTimeoutException) {
            return back()->with('error', 'Primary Admin setup is busy. Please try again.');
        } catch (\DomainException) {
            return redirect()->route('admin.setup')->with('error', 'Primary Admin setup has already been completed.');
        }

        return redirect()->route('admin.login')->with('success', 'Primary Admin created successfully. Please sign in.');
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
        $request->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]);
        $email = $request->input('email');
        $password = $request->input('password');

        // Rate-limit by email+IP so a stolen/guessed credential still can't be brute-forced from
        // a single source, while a normal admin mistyping their password a few times is unaffected.
        $throttleKey = Str::lower($email).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('email'))->with(
                'error',
                'Too many login attempts. Please try again in '.ceil($seconds / 60).' minute(s).'
            );
        }

        $superAdminEmail = config('admin.super_admin_email');
        $superAdminPassword = config('admin.super_admin_password');
        if (is_string($superAdminEmail)
            && $superAdminEmail !== ''
            && hash_equals(Str::lower(trim($superAdminEmail)), $email)) {
            $validSuperAdminPassword = is_string($superAdminPassword)
                && $superAdminPassword !== ''
                && hash_equals($superAdminPassword, $password);

            if ($validSuperAdminPassword) {
                RateLimiter::clear($throttleKey);
                $request->session()->regenerate();
                $request->session()->forget([
                    'admin_user_id',
                    'admin_session_version',
                    'manage_website_auth_user_id',
                    'manage_website_auth_source',
                    'manage_website_auth_email',
                    'manage_website_auth_expires_at',
                    'manage_website_return_to',
                ]);
                $request->session()->put('is_admin', true);
                $request->session()->put('admin_role', 'full');
                $request->session()->put('admin_auth_source', 'emergency');
                $request->session()->put('admin_name', 'Emergency Super Admin');
                $request->session()->put('admin_email', Str::lower(trim($superAdminEmail)));
                $this->logAuthentication($request, 'Signed in');

                return redirect()->route('admin.dashboard');
            }

            RateLimiter::hit($throttleKey, 300);

            return back()->withInput($request->only('email'))->with('error', 'Invalid admin credentials.');
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $isAdminRole = $user && in_array($user->role, ['full', 'limited'], true);
        $validPassword = $isAdminRole && Hash::check($password, $user->password);

        if ($validPassword && $user->is_active === false) {
            RateLimiter::clear($throttleKey);

            return back()->withInput($request->only('email'))->with(
                'error',
                'Your administrator account has been disabled. Please contact a Primary Admin.'
            );
        }

        if ($validPassword && $user->is_active === true) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            $request->session()->forget([
                'manage_website_auth_user_id',
                'manage_website_auth_source',
                'manage_website_auth_email',
                'manage_website_auth_expires_at',
                'manage_website_return_to',
            ]);
            $request->session()->put('is_admin', true);
            $request->session()->put('admin_role', $user->role);
            $request->session()->put('admin_auth_source', 'database');
            $request->session()->put('admin_user_id', $user->id);
            $request->session()->put('admin_name', $user->name);
            $request->session()->put('admin_email', $user->email);
            $request->session()->put('admin_session_version', $user->session_version);
            $this->logAuthentication($request, 'Signed in');

            return redirect()->route('admin.dashboard');
        }

        RateLimiter::hit($throttleKey, 300);

        return back()->with('error', 'Invalid admin credentials.');
    }

    public function showForgotPasswordForm()
    {
        return view('admin.forgot-password');
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $admin = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereIn('role', ['full', 'limited'])
            ->where('is_active', true)
            ->exists();

        if ($admin) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'If an active administrator account matches that email, a password reset link has been sent.');
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('admin.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => AdminPasswordRules::rules(),
        ], AdminPasswordRules::messages());

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'session_version' => $user->session_version + 1,
            ])->save();
            ActivityLog::create([
                'user_id' => $user->id,
                'actor_name' => $user->name,
                'actor_email' => $user->email,
                'actor_role' => $user->role,
                'action' => 'Administrator password reset',
                'method' => 'PASSWORD',
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Administrator password reset completed. Existing sessions were revoked.',
            ]);
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('admin.login')->with('success', 'Password reset successfully. You can now sign in.')
            : back()->withInput($request->only('email'))->with('error', 'This reset link is invalid or has expired.');
    }

    public function logout(Request $request)
    {
        if ($request->session()->get('is_admin')) {
            $this->logAuthentication($request, 'Signed out');
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function logAuthentication(Request $request, string $action): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'limited'),
            'action' => $action,
            'method' => 'SESSION',
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $action.' to the admin panel.',
        ]);
    }

    private function hasPrimaryAdmin(): bool
    {
        return User::query()->where('role', 'full')->exists();
    }

    private function isTeamAdminSession(Request $request): bool
    {
        $adminUserId = $request->session()->get('admin_user_id');

        return $request->session()->get('is_admin', false)
            && $adminUserId !== null
            && User::query()->whereKey($adminUserId)->where('role', 'limited')->exists();
    }

    private function hasSetupKey(): bool
    {
        $key = config('admin.primary_admin_setup_key');

        return is_string($key) && strlen($key) >= 32;
    }
}

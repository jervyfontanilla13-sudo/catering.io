<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminPasswordVerifier
{
    public function verify(Request $request, string $password): bool
    {
        if ($request->session()->get('admin_auth_source') === 'emergency'
            && $request->session()->get('admin_role') === 'full') {
            $configuredPassword = config('admin.super_admin_password');

            return is_string($configuredPassword)
                && $configuredPassword !== ''
                && hash_equals($configuredPassword, $password);
        }

        $user = User::find($request->session()->get('admin_user_id'));

        return $user !== null && $this->verifyUser($user, $password);
    }

    public function verifyUser(User $user, string $password): bool
    {
        return $user->is_active === true && Hash::check($password, $user->password);
    }
}

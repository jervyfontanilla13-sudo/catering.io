<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function withSession(array $data)
    {
        if (($data['is_admin'] ?? false) === true) {
            if (! array_key_exists('admin_user_id', $data)) {
                $role = in_array($data['admin_role'] ?? null, ['full', 'limited'], true)
                    ? $data['admin_role']
                    : 'limited';
                $attributes = ['role' => $role, 'is_active' => true];
                if (isset($data['admin_name'])) {
                    $attributes['name'] = $data['admin_name'];
                }
                if (isset($data['admin_email'])) {
                    $attributes['email'] = $data['admin_email'];
                }
                $user = isset($data['admin_email'])
                    ? User::query()->where('email', $data['admin_email'])->first()
                    : null;
                $user ??= User::factory()->create($attributes);
                $data['admin_user_id'] = $user->id;
                $data['admin_session_version'] = $user->session_version;
                $data['admin_email'] ??= $user->email;
                $data['admin_name'] ??= $user->name;
            } elseif ($data['admin_user_id'] !== null && ! array_key_exists('admin_session_version', $data)) {
                $user = User::find($data['admin_user_id']);
                if ($user) {
                    $data['admin_session_version'] = $user->session_version;
                }
            }
        }

        return parent::withSession($data);
    }
}

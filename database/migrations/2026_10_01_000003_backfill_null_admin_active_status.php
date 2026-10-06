<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_active')) {
            DB::table('users')->whereNull('is_active')->update(['is_active' => true]);
        }
    }

    public function down(): void
    {
        // Existing accounts remain active if this compatibility migration is rolled back.
    }
};

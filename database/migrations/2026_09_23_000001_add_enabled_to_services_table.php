<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'is_enabled')) {
            Schema::table('services', function (Blueprint $table) {
                $table->boolean('is_enabled')->default(true)->after('is_featured');
            });
        }

        DB::table('services')->whereNull('is_enabled')->update(['is_enabled' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'is_enabled')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('is_enabled');
            });
        }
    }
};

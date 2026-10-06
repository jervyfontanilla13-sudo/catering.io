<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'total_cost')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->decimal('total_cost', 12, 2)->nullable()->after('estimated_budget');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'total_cost')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('total_cost');
            });
        }
    }
};

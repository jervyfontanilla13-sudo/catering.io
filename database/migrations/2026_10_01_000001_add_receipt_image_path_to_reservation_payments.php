<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_payments') && ! Schema::hasColumn('reservation_payments', 'receipt_image_path')) {
            Schema::table('reservation_payments', function (Blueprint $table): void {
                $table->string('receipt_image_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservation_payments') && Schema::hasColumn('reservation_payments', 'receipt_image_path')) {
            Schema::table('reservation_payments', function (Blueprint $table): void {
                $table->dropColumn('receipt_image_path');
            });
        }
    }
};
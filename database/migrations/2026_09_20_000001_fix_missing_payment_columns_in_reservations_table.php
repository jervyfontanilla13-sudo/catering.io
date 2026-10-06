<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'payment_status')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->string('payment_status')->default('Unpaid');
            });
        }

        if (! Schema::hasColumn('reservations', 'payment_type')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->string('payment_type')->nullable();
            });
        }

        if (! Schema::hasColumn('reservations', 'amount_paid')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->decimal('amount_paid', 10, 2)->default(0.00);
            });
        }

        if (! Schema::hasColumn('reservations', 'balance')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->decimal('balance', 10, 2)->default(0.00);
            });
        }

        DB::table('reservations')
            ->whereNull('payment_status')
            ->update(['payment_status' => 'Unpaid']);

        DB::table('reservations')
            ->whereNull('payment_type')
            ->update(['payment_type' => 'Unpaid']);

        DB::table('reservations')
            ->whereNull('amount_paid')
            ->update(['amount_paid' => 0.00]);

        DB::table('reservations')
            ->whereNull('balance')
            ->update(['balance' => 0.00]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_type', 'amount_paid', 'balance']);
        });
    }
};

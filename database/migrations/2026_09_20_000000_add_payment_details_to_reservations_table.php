<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'payment_status')) {
                $table->string('payment_status')->default('Unpaid')->after('status');
            }

            if (! Schema::hasColumn('reservations', 'payment_type')) {
                $table->string('payment_type')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('reservations', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->default(0.00)->after('payment_type');
            }

            if (! Schema::hasColumn('reservations', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0.00)->after('amount_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_type', 'amount_paid', 'balance']);
        });
    }
};

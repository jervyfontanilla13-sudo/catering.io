<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'reservation_code')) {
                $table->string('reservation_code')->nullable()->unique()->after('status');
            }
        });

        $reservations = DB::table('reservations')->whereNull('reservation_code')->get();

        foreach ($reservations as $reservation) {
            $code = 'RES-' . strtoupper(substr(md5(uniqid((string) $reservation->id, true)), 0, 8));
            DB::table('reservations')->where('id', $reservation->id)->update(['reservation_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['reservation_code']);
            $table->dropColumn('reservation_code');
        });
    }
};

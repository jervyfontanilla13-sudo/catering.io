<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_capacity_locks', function (Blueprint $table) {
            $table->date('event_date')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_capacity_locks');
    }
};

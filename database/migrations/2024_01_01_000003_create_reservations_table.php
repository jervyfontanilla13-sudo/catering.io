<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('contact_number');
            $table->string('email');
            $table->string('address');
            $table->string('event_type');
            $table->date('event_date');
            $table->string('event_time');
            $table->string('venue');
            $table->integer('guest_count');
            $table->decimal('estimated_budget', 12, 2)->default(0);
            $table->text('additional_services')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('additional_notes')->nullable();
            $table->string('status')->default('pending');
            $table->string('reservation_code')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

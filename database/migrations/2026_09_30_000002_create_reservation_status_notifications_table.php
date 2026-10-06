<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_status_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('notification_type');
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'notification_type'], 'rsn_reservation_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('reservation_payments')->nullOnDelete();
            $table->date('refund_date');
            $table->decimal('amount', 12, 2);
            $table->string('refund_method');
            $table->text('reason')->nullable();
            $table->string('status')->default('completed');
            $table->uuid('request_key')->unique();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'refund_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_refunds');
    }
};

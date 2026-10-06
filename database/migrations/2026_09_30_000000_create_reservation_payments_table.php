<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservation_payments')) {
            Schema::create('reservation_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $table->date('payment_date');
                $table->string('payment_type');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method');
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('recorded_by_name')->nullable();
                $table->timestamps();

                $table->index(['reservation_id', 'payment_date']);
            });
        }

        if (! Schema::hasColumn('reservations', 'payment_due_date')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->date('payment_due_date')->nullable()->after('balance');
            });
        }

        // Carry each existing running total into the ledger as one opening entry so
        // recalculating from payment history never changes what bookings already show.
        $now = now();
        DB::table('reservations')
            ->where('amount_paid', '>', 0)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('reservation_payments')
                ->whereColumn('reservation_payments.reservation_id', 'reservations.id'))
            ->orderBy('id')
            ->get(['id', 'amount_paid', 'payment_type', 'payment_status', 'updated_at'])
            ->each(fn ($reservation) => DB::table('reservation_payments')->insert([
                'reservation_id' => $reservation->id,
                'payment_date' => substr((string) ($reservation->updated_at ?? $now), 0, 10),
                'payment_type' => $reservation->payment_status === 'Fully Paid' || $reservation->payment_type === 'Full Payment' ? 'Full Payment' : 'Downpayment',
                'amount' => $reservation->amount_paid,
                'payment_method' => 'Other',
                'notes' => 'Opening balance carried over from the previous payment tracker.',
                'recorded_by_name' => 'System',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');

        if (Schema::hasColumn('reservations', 'payment_due_date')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('payment_due_date');
            });
        }
    }
};

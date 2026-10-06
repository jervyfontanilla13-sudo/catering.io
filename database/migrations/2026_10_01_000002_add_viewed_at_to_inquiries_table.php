<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // Decoupled from `status` on purpose: viewing an inquiry marks it read, but must not
            // silently change its status (the admin/status lifecycle is a separate, deliberate signal).
            $table->timestamp('viewed_at')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('activity_logs', function (Blueprint $table) { $table->string('actor_name')->nullable()->after('user_id'); $table->string('actor_email')->nullable()->after('actor_name'); $table->string('actor_role', 20)->nullable()->after('actor_email'); $table->string('method', 12)->nullable()->after('action'); $table->string('ip_address', 45)->nullable()->after('method'); $table->index(['actor_email', 'created_at']); }); }
    public function down(): void { Schema::table('activity_logs', function (Blueprint $table) { $table->dropIndex(['actor_email', 'created_at']); $table->dropColumn(['actor_name', 'actor_email', 'actor_role', 'method', 'ip_address']); }); }
};

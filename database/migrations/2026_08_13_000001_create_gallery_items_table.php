<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('gallery_items', function (Blueprint $table) { $table->id(); $table->string('title'); $table->text('description')->nullable(); $table->string('image_path'); $table->string('event_type')->nullable(); $table->boolean('is_featured')->default(false); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('gallery_items'); }
};

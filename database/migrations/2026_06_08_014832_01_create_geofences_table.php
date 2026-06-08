<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['circle', 'polygon'])->default('circle');
            $table->decimal('center_lat', 10, 6)->nullable();
            $table->decimal('center_lon', 10, 6)->nullable();
            $table->decimal('radius', 10, 2)->nullable();
            $table->json('coordinates')->nullable();
            $table->string('color', 20)->default('#ef4444');
            $table->boolean('is_active')->default(true);
            $table->boolean('alert_on_enter')->default(true);
            $table->boolean('alert_on_exit')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};

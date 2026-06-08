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
        Schema::create('gps_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('imei')->unique();
            $table->string('plate')->nullable();
            $table->enum('unit_type', ['patrol', 'covert'])->default('patrol');
            $table->string('sim_number')->nullable();
            $table->string('color', 20)->default('#3b82f6');
            $table->string('icon', 50)->default('car');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('last_lat', 10, 6)->nullable();
            $table->decimal('last_lon', 10, 6)->nullable();
            $table->decimal('last_speed', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_units');
    }
};

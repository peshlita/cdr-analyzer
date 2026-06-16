<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_vehicle_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')
                  ->constrained('gps_vehicles')->cascadeOnDelete();
            $table->decimal('lat', 10, 6);
            $table->decimal('lon', 10, 6);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->string('battery')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_vehicle_points');
    }
};

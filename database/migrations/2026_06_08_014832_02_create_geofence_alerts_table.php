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
        Schema::create('geofence_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained('geofences')->cascadeOnDelete();
            $table->foreignId('gps_unit_id')->constrained('gps_units')->cascadeOnDelete();
            $table->enum('alert_type', ['enter', 'exit']);
            $table->decimal('lat', 10, 6);
            $table->decimal('lon', 10, 6);
            $table->timestamp('triggered_at');
            $table->boolean('acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_alerts');
    }
};

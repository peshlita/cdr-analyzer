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
        Schema::create('gps_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gps_unit_id')->constrained('gps_units')->cascadeOnDelete();
            $table->decimal('lat', 10, 6);
            $table->decimal('lon', 10, 6);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->integer('satellites')->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->text('raw_data')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index('gps_unit_id');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_positions');
    }
};

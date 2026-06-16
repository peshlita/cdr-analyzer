<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('plate')->nullable();
            $table->enum('unit_type', ['patrol', 'covert'])->default('covert');
            $table->string('color', 20)->default('#3b82f6');
            $table->string('icon', 50)->default('fa-car');
            $table->enum('source', ['live', 'imported'])->default('imported');
            $table->foreignId('gps_unit_id')->nullable()
                  ->constrained('gps_units')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->enum('source_format', ['xls', 'kml', 'kmz', 'gpx'])->nullable();
            $table->integer('total_points')->default(0);
            $table->timestamp('date_from')->nullable();
            $table->timestamp('date_to')->nullable();
            $table->foreignId('imported_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['source', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_vehicles');
    }
};

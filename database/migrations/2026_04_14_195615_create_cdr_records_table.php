<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdr_records', function (Blueprint $table) {
            $table->id();
            $table->string('number_a')->nullable();
            $table->decimal('lat_a', 10, 6)->nullable();
            $table->decimal('lon_a', 10, 6)->nullable();
            $table->decimal('azimuth_a', 8, 2)->nullable();
            $table->string('imei_a')->nullable();
            $table->string('imsi_a')->nullable();
            $table->string('number_b')->nullable();
            $table->decimal('lat_b', 10, 6)->nullable();
            $table->decimal('lon_b', 10, 6)->nullable();
            $table->decimal('azimuth_b', 8, 2)->nullable();
            $table->string('imei_b')->nullable();
            $table->string('imsi_b')->nullable();
            $table->string('type')->nullable();
            $table->string('direction')->nullable();
            $table->integer('duration')->default(0);
            $table->date('date')->nullable();
            $table->time('hour')->nullable();
            $table->timestamps();

            $table->index('number_a');
            $table->index('number_b');
            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdr_records');
    }
};

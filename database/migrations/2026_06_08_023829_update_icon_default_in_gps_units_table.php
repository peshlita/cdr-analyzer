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
        Schema::table('gps_units', function (Blueprint $table) {
            $table->string('icon', 50)->default('fa-car')->change();
        });

        // Migrar valores existentes al formato fa-XXX
        \DB::table('gps_units')
            ->where('icon', 'not like', 'fa-%')
            ->update(['icon' => \DB::raw("CONCAT('fa-', icon)")]);
    }

    public function down(): void
    {
        Schema::table('gps_units', function (Blueprint $table) {
            $table->string('icon', 50)->default('car')->change();
        });
    }
};

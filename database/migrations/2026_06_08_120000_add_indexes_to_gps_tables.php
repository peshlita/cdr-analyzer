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
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->index(['gps_unit_id', 'received_at'], 'gps_positions_unit_received_idx');
        });

        Schema::table('gps_units', function (Blueprint $table) {
            $table->index(['is_active', 'last_seen_at'], 'gps_units_active_lastseen_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->dropIndex('gps_positions_unit_received_idx');
        });

        Schema::table('gps_units', function (Blueprint $table) {
            $table->dropIndex('gps_units_active_lastseen_idx');
        });
    }
};

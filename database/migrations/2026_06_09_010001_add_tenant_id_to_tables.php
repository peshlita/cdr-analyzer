<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas que reciben tenant_id. gps_imported_routes no existe en este
     * proyecto (se usan gps_vehicles / gps_vehicle_points), por eso no está.
     */
    private array $tables = [
        'users', 'gps_units', 'gps_positions', 'gps_vehicles',
        'gps_vehicle_points', 'geofences', 'geofence_alerts',
        'cdr_records', 'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (!Schema::hasTable($name) || Schema::hasColumn($name, 'tenant_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('tenant_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('tenants')
                      ->nullOnDelete();

                if ($name === 'gps_positions') {
                    $table->index(['tenant_id', 'received_at']);
                } elseif ($name === 'cdr_records') {
                    $table->index(['tenant_id']);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (!Schema::hasTable($name) || !Schema::hasColumn($name, 'tenant_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aislamiento por usuario (propietario). Agrega user_id a las tablas de datos
 * para que cada usuario vea solo lo suyo (los admins ven por institución).
 */
return new class extends Migration
{
    /** Tablas que ya tienen tenant_id y solo necesitan user_id. */
    private array $withTenant = [
        'cdr_records', 'gps_units', 'gps_positions', 'gps_vehicles',
        'gps_vehicle_points', 'geofences', 'geofence_alerts',
    ];

    public function up(): void
    {
        foreach ($this->withTenant as $name) {
            if (!Schema::hasTable($name) || Schema::hasColumn($name, 'user_id')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('user_id')->nullable()->after('tenant_id')
                      ->constrained('users')->nullOnDelete();
                $table->index(['user_id'], "{$name}_user_id_idx");
            });
        }

        // phone_contacts: no tenía tenant_id ni user_id; el unique global de
        // phone_number debe pasar a (user_id, phone_number).
        if (Schema::hasTable('phone_contacts')) {
            Schema::table('phone_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('phone_contacts', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')
                          ->constrained('tenants')->nullOnDelete();
                }
                if (!Schema::hasColumn('phone_contacts', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('tenant_id')
                          ->constrained('users')->nullOnDelete();
                }
            });
            // Cambiar el unique de phone_number → (user_id, phone_number).
            try { Schema::table('phone_contacts', fn (Blueprint $t) => $t->dropUnique(['phone_number'])); } catch (\Throwable $e) {}
            try { Schema::table('phone_contacts', fn (Blueprint $t) => $t->unique(['user_id', 'phone_number'])); } catch (\Throwable $e) {}
        }

        // settings: target_number pasa a ser por usuario. Se quita el unique de
        // key (habrá una fila por usuario) y se agrega user_id nullable.
        if (Schema::hasTable('settings') && !Schema::hasColumn('settings', 'user_id')) {
            try { Schema::table('settings', fn (Blueprint $t) => $t->dropUnique(['key'])); } catch (\Throwable $e) {}
            Schema::table('settings', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')
                      ->constrained('users')->nullOnDelete();
                $table->index(['key', 'user_id'], 'settings_key_user_idx');
            });
            // El target_number global heredado se elimina (ahora es por usuario)
            DB::table('settings')->where('key', 'target_number')->whereNull('user_id')->delete();
        }
    }

    public function down(): void
    {
        foreach ($this->withTenant as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'user_id')) {
                Schema::table($name, function (Blueprint $table) use ($name) {
                    try { $table->dropIndex("{$name}_user_id_idx"); } catch (\Throwable $e) {}
                    $table->dropConstrainedForeignId('user_id');
                });
            }
        }

        if (Schema::hasTable('phone_contacts')) {
            try { Schema::table('phone_contacts', fn (Blueprint $t) => $t->dropUnique(['user_id', 'phone_number'])); } catch (\Throwable $e) {}
            Schema::table('phone_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('phone_contacts', 'user_id'))   $table->dropConstrainedForeignId('user_id');
                if (Schema::hasColumn('phone_contacts', 'tenant_id')) $table->dropConstrainedForeignId('tenant_id');
            });
            try { Schema::table('phone_contacts', fn (Blueprint $t) => $t->unique(['phone_number'])); } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'user_id')) {
            Schema::table('settings', function (Blueprint $table) {
                try { $table->dropIndex('settings_key_user_idx'); } catch (\Throwable $e) {}
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};

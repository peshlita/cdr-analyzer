<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas normalizadas del rediseño de importación CDR. Es ADITIVA: las
 * columnas legacy (number_a, lat_a, type, date, hour, source_file, direction…)
 * se conservan para no romper los módulos downstream (Network/Analysis/Map/
 * Report/Dashboards). El nuevo valor direccional va en `flow` para no chocar
 * con la columna legacy `direction` (Incoming/Outgoing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdr_records', function (Blueprint $table) {
            if (!Schema::hasColumn('cdr_records', 'batch_id'))
                $table->string('batch_id', 36)->nullable()->index();
            if (!Schema::hasColumn('cdr_records', 'phone_main'))
                $table->string('phone_main', 20)->nullable()->index();
            if (!Schema::hasColumn('cdr_records', 'record_type'))
                $table->string('record_type', 30)->nullable()->index();
            if (!Schema::hasColumn('cdr_records', 'contact_number'))
                $table->string('contact_number', 50)->nullable()->index();
            if (!Schema::hasColumn('cdr_records', 'flow'))
                $table->string('flow', 10)->nullable(); // in | out | transit | data
            if (!Schema::hasColumn('cdr_records', 'call_datetime'))
                $table->timestamp('call_datetime')->nullable()->index();
            if (!Schema::hasColumn('cdr_records', 'lat'))
                $table->decimal('lat', 10, 6)->nullable();
            if (!Schema::hasColumn('cdr_records', 'lon'))
                $table->decimal('lon', 10, 6)->nullable();
            if (!Schema::hasColumn('cdr_records', 'raw_lat'))
                $table->string('raw_lat', 30)->nullable();
            if (!Schema::hasColumn('cdr_records', 'raw_lon'))
                $table->string('raw_lon', 30)->nullable();
            if (!Schema::hasColumn('cdr_records', 'imei'))
                $table->string('imei', 20)->nullable();
            if (!Schema::hasColumn('cdr_records', 'azimuth'))
                $table->integer('azimuth')->nullable();
        });

        // Índices compuestos para análisis de cruce (idempotentes).
        Schema::table('cdr_records', function (Blueprint $table) {
            foreach ([
                'cdr_phone_contact_idx'   => ['phone_main', 'contact_number'],
                'cdr_contact_datetime_idx'=> ['contact_number', 'call_datetime'],
            ] as $name => $cols) {
                try { $table->index($cols, $name); } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('cdr_records', function (Blueprint $table) {
            foreach (['cdr_phone_contact_idx', 'cdr_contact_datetime_idx'] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) {}
            }
            foreach (['batch_id', 'phone_main', 'record_type', 'contact_number', 'flow',
                      'call_datetime', 'lat', 'lon', 'raw_lat', 'raw_lon', 'imei', 'azimuth'] as $col) {
                if (Schema::hasColumn('cdr_records', $col)) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                }
            }
        });
    }
};

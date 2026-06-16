<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\CdrRecord;
use App\Models\Geofence;
use App\Models\GeofenceAlert;
use App\Models\GpsPosition;
use App\Models\GpsUnit;
use App\Models\GpsVehicle;
use App\Models\GpsVehiclePoint;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant de prueba ─────────────────────────
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Institución Demo', 'is_active' => true]
        );

        // ── Super Admin GLOBAL (sin tenant — ve todo) ─
        User::updateOrCreate(
            ['email' => 'admin@cdranalizer.local'],
            [
                'name'      => 'Administrador Global',
                'password'  => Hash::make('Admin2024!'),
                'role'      => 'super_admin',
                'tenant_id' => null,
                'is_active' => true,
            ]
        );

        // ── Admin de la institución demo ─────────────
        User::updateOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name'      => 'Admin Demo',
                'password'  => Hash::make('Demo2024!'),
                'role'      => 'super_admin',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );

        // ── Analista de la institución demo ──────────
        $analista = User::updateOrCreate(
            ['email' => 'analista@demo.local'],
            [
                'name'      => 'Analista Demo',
                'password'  => Hash::make('Analista2024!'),
                'role'      => 'analyst',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );

        // Reasignar el analista heredado (si existe) al tenant demo
        User::where('email', 'analista@cdranalizer.local')
            ->update(['tenant_id' => $tenant->id]);

        // Permisos del analista: solo COMINT habilitado
        $modules = ['comint', 'osint', 'incidencia', 'geoint', 'casos'];
        foreach ($modules as $slug) {
            UserModulePermission::updateOrCreate(
                ['user_id' => $analista->id, 'module_slug' => $slug],
                ['enabled' => $slug === 'comint']
            );
        }

        // ── Backfill: datos existentes sin tenant → demo ─
        // (Modelos con global scope; en consola no hay auth, el scope es no-op.)
        foreach ([GpsUnit::class, GpsPosition::class, GpsVehicle::class,
                  GpsVehiclePoint::class, Geofence::class, GeofenceAlert::class,
                  CdrRecord::class, AuditLog::class] as $model) {
            $model::whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);
        }

        // ── Configuración inicial ────────────────────
        Setting::updateOrCreate(['key' => 'institution_name'], ['value' => 'Mi Institución']);
        Setting::updateOrCreate(['key' => 'session_lifetime'], ['value' => '30']);
        Setting::updateOrCreate(['key' => 'force_2fa'], ['value' => '0']);

        $this->command->info('✓ Super Admin Global: admin@cdranalizer.local / Admin2024!');
        $this->command->info('✓ Admin Demo (tenant): admin@demo.local / Demo2024!');
        $this->command->info('✓ Analista Demo (tenant): analista@demo.local / Analista2024!');
    }
}

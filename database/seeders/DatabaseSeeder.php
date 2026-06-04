<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Administrador ──────────────────────
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@cdranalizer.local'],
            [
                'name'      => 'Administrador',
                'password'  => bcrypt('Admin2024!'),
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        // ── Analista de prueba ───────────────────────
        $analista = User::updateOrCreate(
            ['email' => 'analista@cdranalizer.local'],
            [
                'name'      => 'Analista Demo',
                'password'  => bcrypt('Analista2024!'),
                'role'      => 'analyst',
                'is_active' => true,
            ]
        );

        // Permisos del analista: solo COMINT habilitado
        $modules = ['comint', 'osint', 'incidencia', 'geoint', 'casos'];
        foreach ($modules as $slug) {
            UserModulePermission::updateOrCreate(
                ['user_id' => $analista->id, 'module_slug' => $slug],
                ['enabled' => $slug === 'comint']
            );
        }

        // ── Configuración inicial ────────────────────
        Setting::updateOrCreate(['key' => 'institution_name'], ['value' => 'Mi Institución']);
        Setting::updateOrCreate(['key' => 'session_lifetime'], ['value' => '30']);
        Setting::updateOrCreate(['key' => 'force_2fa'], ['value' => '0']);

        $this->command->info('✓ Super Admin: admin@cdranalizer.local / Admin2024!');
        $this->command->info('✓ Analista: analista@cdranalizer.local / Analista2024!');
    }
}

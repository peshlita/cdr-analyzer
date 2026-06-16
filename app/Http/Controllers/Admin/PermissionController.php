<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    const MODULES = [
        'comint'    => ['name' => 'COMINT', 'desc' => 'Análisis de CDRs', 'icon' => 'fas fa-phone-volume', 'color' => 'blue'],
        'osint'     => ['name' => 'OSINT',  'desc' => 'Búsqueda de Personas', 'icon' => 'fas fa-user-secret', 'color' => 'purple'],
        'incidencia'=> ['name' => 'INCIDENCIA', 'desc' => 'Incidencia Delictiva', 'icon' => 'fas fa-exclamation-triangle', 'color' => 'red'],
        'geoint'    => ['name' => 'GEOINT', 'desc' => 'GPS y Monitoreo', 'icon' => 'fas fa-satellite', 'color' => 'green'],
        'casos'     => ['name' => 'CASOS',  'desc' => 'Integración de Casos', 'icon' => 'fas fa-folder-open', 'color' => 'yellow'],
    ];

    public function show(User $user)
    {
        abort_unless($user->isManageableBy(auth()->user()), 403);

        $permissions = $user->modulePermissions->keyBy('module_slug');
        $modules = self::MODULES;
        return view('admin.users.permissions', compact('user', 'permissions', 'modules'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isManageableBy(auth()->user()), 403);

        $enabled = $request->input('modules', []);

        foreach (array_keys(self::MODULES) as $slug) {
            UserModulePermission::updateOrCreate(
                ['user_id' => $user->id, 'module_slug' => $slug],
                ['enabled' => in_array($slug, $enabled)]
            );
        }

        AuditLogger::log('update_permissions', "Permisos actualizados para: {$user->email}");

        return redirect()->route('admin.users.index')
            ->with('success', "Permisos de {$user->name} actualizados.");
    }
}

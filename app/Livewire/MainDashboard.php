<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\CdrRecord;
use App\Models\User;
use Livewire\Component;

class MainDashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        $allModules = [
            [
                'slug'         => 'comint',
                'label'        => 'COMINT',
                'icon'         => 'fas fa-phone-volume',
                'color'        => '#3b82f6',
                'url'          => '/comint/dashboard',
                'metric'       => CdrRecord::count(),
                'metric_label' => 'registros CDR',
                'ready'        => true,
            ],
            [
                'slug'         => 'osint',
                'label'        => 'OSINT',
                'icon'         => 'fas fa-search',
                'color'        => '#8b5cf6',
                'url'          => '/osint',
                'metric'       => null,
                'metric_label' => 'Próximamente',
                'ready'        => false,
            ],
            [
                'slug'         => 'incidencia',
                'label'        => 'Incidencia',
                'icon'         => 'fas fa-chart-bar',
                'color'        => '#ef4444',
                'url'          => '/incidencia',
                'metric'       => null,
                'metric_label' => 'Próximamente',
                'ready'        => false,
            ],
            [
                'slug'         => 'geoint',
                'label'        => 'GEOINT',
                'icon'         => 'fas fa-satellite',
                'color'        => '#10b981',
                'url'          => '/geoint',
                'metric'       => null,
                'metric_label' => 'Próximamente',
                'ready'        => false,
            ],
            [
                'slug'         => 'casos',
                'label'        => 'Casos',
                'icon'         => 'fas fa-folder-open',
                'color'        => '#f59e0b',
                'url'          => '/casos',
                'metric'       => null,
                'metric_label' => 'Próximamente',
                'ready'        => false,
            ],
        ];

        $modules = array_values(array_filter(
            $allModules,
            fn($m) => $user->hasModuleAccess($m['slug'])
        ));

        $recentActivity = AuditLog::with('user')
            ->latest()
            ->limit(10)
            ->get();

        $systemStats  = null;
        $recentLogins = collect();

        if ($user->isSuperAdmin()) {
            $systemStats = [
                'total_users'      => User::where('is_active', true)->count(),
                'online_users'     => User::where('last_login_at', '>=', now()->subMinutes(30))->count(),
                'enabled_modules'  => 5,
            ];
            $recentLogins = User::whereNotNull('last_login_at')
                ->orderByDesc('last_login_at')
                ->limit(5)
                ->get();
        }

        return view('livewire.main-dashboard', [
            'modules'        => $modules,
            'recentActivity' => $recentActivity,
            'systemStats'    => $systemStats,
            'recentLogins'   => $recentLogins,
            'currentTime'    => now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY, HH:mm'),
        ])->extends('layouts.app')->section('content');
    }
}

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — CDR-Analizer</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dark-bg':      '#0f172a',
                        'dark-sidebar': '#1e293b',
                        'dark-card':    '#1e293b',
                        'dark-border':  '#334155',
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @livewireStyles
    @stack('styles')
    <style>
        [x-cloak] { display: none !important; }
        body { background-color: #0f172a; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
        .nav-locked { pointer-events: none; opacity: 0.35; }
    </style>
</head>
<body class="h-full text-slate-100 antialiased" style="background-color:#0f172a;">

<div class="flex h-screen overflow-hidden">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="flex flex-col w-64 flex-shrink-0" style="background-color:#1e293b; border-right:1px solid #334155;">

        <!-- Logo -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-700">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color:#3b82f6;">
                <i class="fas fa-phone-volume text-white text-sm"></i>
            </div>
            <div>
                <p class="font-bold text-white text-sm leading-none">CDR-Analizer</p>
                <p class="text-xs text-slate-500 mt-0.5">Plataforma de Inteligencia</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto">

            @php
                $user = auth()->user();

                $isComintOpen = request()->is('dashboard')
                    ? false
                    : (request()->is('comint/dashboard')
                    || request()->is('upload*')
                    || request()->is('network*')
                    || request()->is('analysis*')
                    || request()->is('map*')
                    || request()->is('contacts*')
                    || request()->is('report*'));

                $hasComint = !$user || $user->hasModuleAccess('comint');

                $comintSubItems = [
                    ['url' => '/comint/dashboard', 'icon' => 'fas fa-chart-pie',        'label' => 'Dashboard COMINT', 'match' => 'comint/dashboard'],
                    ['url' => '/upload',            'icon' => 'fas fa-file-csv',          'label' => 'Importar CSV',      'match' => 'upload*'],
                    ['url' => '/network',           'icon' => 'fas fa-project-diagram',   'label' => 'Red de Llamadas',   'match' => 'network*'],
                    ['url' => '/analysis',          'icon' => 'fas fa-microscope',        'label' => 'Análisis',          'match' => 'analysis*'],
                    ['url' => '/map',               'icon' => 'fas fa-map-marked-alt',    'label' => 'Mapa GPS',          'match' => 'map*'],
                    ['url' => '/contacts',          'icon' => 'fas fa-address-book',      'label' => 'Contactos',         'match' => 'contacts*'],
                    ['url' => '/report',            'icon' => 'fas fa-file-pdf',          'label' => 'Reporte PDF',       'match' => 'report*'],
                ];

                $isGeointOpen = request()->is('geoint*');
                $hasGeoint    = $user && $user->hasModuleAccess('geoint');
                $geointAlerts = $hasGeoint ? \App\Models\GeofenceAlert::where('acknowledged', false)->count() : 0;

                $geointSubItems = [
                    ['url' => '/geoint',            'icon' => 'fas fa-map-marked-alt',  'label' => 'Mapa en Vivo',  'match' => 'geoint',           'badge' => 0],
                    ['url' => '/geoint/units',      'icon' => 'fas fa-car',             'label' => 'Unidades',      'match' => 'geoint/units*',    'badge' => 0],
                    ['url' => '/geoint/geofences',  'icon' => 'fas fa-draw-polygon',    'label' => 'Geocercas',     'match' => 'geoint/geofences*','badge' => 0],
                    ['url' => '/geoint/alerts',     'icon' => 'fas fa-bell',            'label' => 'Alertas',       'match' => 'geoint/alerts*',   'badge' => $geointAlerts],
                    ['url' => '/geoint/report',     'icon' => 'fas fa-file-pdf',        'label' => 'Reporte',       'match' => 'geoint/report*',   'badge' => 0],
                ];

                $otherModules = [
                    ['slug' => 'osint',      'url' => '/osint',      'icon' => 'fas fa-search',      'label' => 'OSINT',      'color' => '#8b5cf6'],
                    ['slug' => 'incidencia', 'url' => '/incidencia', 'icon' => 'fas fa-chart-bar',   'label' => 'Incidencia', 'color' => '#ef4444'],
                    ['slug' => 'casos',      'url' => '/casos',      'icon' => 'fas fa-folder-open', 'label' => 'Casos',      'color' => '#f59e0b'],
                ];
            @endphp

            <!-- ── GENERAL ── -->
            <div>
                <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2">General</p>
                @php $isDashActive = request()->is('dashboard'); @endphp
                <a href="/dashboard"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150
                          {{ $isDashActive ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                   @if($isDashActive) style="background-color:#3b82f6;" @endif>
                    <i class="fas fa-home w-4 text-center text-xs"></i>
                    <span>Dashboard General</span>
                </a>
            </div>

            <!-- ── MÓDULOS ── -->
            <div>
                <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2">
                    <i class="fas fa-th-large mr-1"></i> Módulos
                </p>

                <!-- COMINT acordeón -->
                <div x-data="{ open: {{ $isComintOpen ? 'true' : 'false' }} }" class="mb-1">
                    @if($hasComint)
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150
                                   {{ $isComintOpen ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                            @if($isComintOpen) style="background-color:#3b82f620;" @endif>
                        <i class="fas fa-phone-volume w-4 text-center text-xs" style="color:#3b82f6;"></i>
                        <span class="flex-1 text-left">COMINT</span>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200"
                           :class="open ? 'rotate-90' : ''"
                           style="color:#3b82f6;"></i>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="mt-1 ml-2 space-y-0.5"
                         x-cloak>
                        @foreach($comintSubItems as $item)
                            @php $isActive = request()->is($item['match']); @endphp
                            <a href="{{ $item['url'] }}"
                               class="flex items-center gap-2 pl-6 pr-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 border-l-2
                                      {{ $isActive
                                          ? 'text-white border-blue-500'
                                          : 'text-slate-400 hover:text-white hover:bg-slate-700 border-transparent' }}"
                               @if($isActive) style="background-color:#3b82f615;" @endif>
                                <i class="{{ $item['icon'] }} w-4 text-center flex-shrink-0"
                                   style="color:{{ $isActive ? '#3b82f6' : '#475569' }};"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    @else
                    <div class="nav-locked flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-600">
                        <i class="fas fa-phone-volume w-4 text-center text-xs"></i>
                        <span class="flex-1">COMINT</span>
                        <i class="fas fa-lock text-xs"></i>
                    </div>
                    @endif
                </div>

                <!-- GEOINT acordeón -->
                <div x-data="{ open: {{ $isGeointOpen ? 'true' : 'false' }} }" class="mb-1">
                    @if($hasGeoint)
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150
                                   {{ $isGeointOpen ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                            @if($isGeointOpen) style="background-color:#10b98120;" @endif>
                        <i class="fas fa-satellite w-4 text-center text-xs" style="color:#10b981;"></i>
                        <span class="flex-1 text-left">GEOINT</span>
                        @if($geointAlerts > 0)
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded-full text-white mr-1" style="background-color:#ef4444;">{{ $geointAlerts }}</span>
                        @endif
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200"
                           :class="open ? 'rotate-90' : ''"
                           style="color:#10b981;"></i>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="mt-1 ml-2 space-y-0.5"
                         x-cloak>
                        @foreach($geointSubItems as $item)
                            @php $isActive = request()->is($item['match']); @endphp
                            <a href="{{ $item['url'] }}"
                               class="flex items-center gap-2 pl-6 pr-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 border-l-2
                                      {{ $isActive
                                          ? 'text-white border-green-500'
                                          : 'text-slate-400 hover:text-white hover:bg-slate-700 border-transparent' }}"
                               @if($isActive) style="background-color:#10b98115;" @endif>
                                <i class="{{ $item['icon'] }} w-4 text-center flex-shrink-0"
                                   style="color:{{ $isActive ? '#10b981' : '#475569' }};"></i>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if($item['badge'] > 0)
                                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full text-white" style="background-color:#ef4444;">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    @else
                    <div class="nav-locked flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-600">
                        <i class="fas fa-satellite w-4 text-center text-xs"></i>
                        <span class="flex-1">GEOINT</span>
                        <i class="fas fa-lock text-xs"></i>
                    </div>
                    @endif
                </div>

                <!-- Otros módulos -->
                @foreach($otherModules as $mod)
                    @php $hasAccess = $user && $user->hasModuleAccess($mod['slug']); @endphp
                    @if($hasAccess)
                        @php $isActive = request()->is(ltrim($mod['url'], '/').'*') || request()->is(ltrim($mod['url'], '/')); @endphp
                        <a href="{{ $mod['url'] }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150
                                  {{ $isActive ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                           @if($isActive) style="background-color:{{ $mod['color'] }};" @endif>
                            <i class="{{ $mod['icon'] }} w-4 text-center text-xs"
                               @if(!$isActive) style="color:{{ $mod['color'] }};" @endif></i>
                            <span>{{ $mod['label'] }}</span>
                        </a>
                    @else
                        <div class="nav-locked flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-600">
                            <i class="{{ $mod['icon'] }} w-4 text-center text-xs"></i>
                            <span>{{ $mod['label'] }}</span>
                            <i class="fas fa-lock ml-auto text-xs"></i>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- ── ADMINISTRACIÓN (solo super_admin) ── -->
            @if($user && $user->isSuperAdmin())
            <div>
                <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2">
                    <i class="fas fa-shield-alt mr-1"></i> Administración
                </p>

                @php
                    $adminItems = [
                        ['url' => '/admin',          'icon' => 'fas fa-shield-alt',     'label' => 'Panel Admin'],
                        ['url' => '/admin/users',    'icon' => 'fas fa-users',          'label' => 'Usuarios'],
                        ['url' => '/admin/audit',    'icon' => 'fas fa-clipboard-list', 'label' => 'Auditoría'],
                        ['url' => '/admin/settings', 'icon' => 'fas fa-cog',            'label' => 'Configuración'],
                    ];
                @endphp

                @foreach($adminItems as $item)
                    @php
                        $isActive = request()->is(ltrim($item['url'], '/'))
                            || ($item['url'] !== '/admin' && request()->is(ltrim($item['url'], '/').'/*'));
                    @endphp
                    <a href="{{ $item['url'] }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150
                              {{ $isActive ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                       @if($isActive) style="background-color:#7f1d1d;" @endif>
                        <i class="{{ $item['icon'] }} w-4 text-center text-xs"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
            @endif

        </nav>

        <!-- Footer sidebar: usuario -->
        <div class="px-3 py-3 border-t border-slate-700">
            @auth
            <div class="flex items-center gap-3 px-2 py-2 rounded-lg" style="background-color:#0f172a;">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                    style="background-color:{{ auth()->user()->isSuperAdmin() ? '#dc2626' : '#3b82f6' }};">
                    {{ auth()->user()->initials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ auth()->user()->role_label }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-red-400 transition-colors" title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt text-sm"></i>
                    </button>
                </form>
            </div>
            @endauth

            @php $total = \App\Models\CdrRecord::count(); @endphp
            <p class="text-xs text-slate-600 mt-2 px-2">
                <i class="fas fa-database mr-1"></i>
                {{ number_format($total) }} registros CDR
            </p>
        </div>
    </aside>

    <!-- ═══════════ MAIN ═══════════ -->
    <div class="flex flex-col flex-1 overflow-hidden">

        <!-- Topbar -->
        <header class="flex items-center justify-between px-6 py-3 border-b border-slate-700 flex-shrink-0" style="background-color:#1e293b;">
            <div>
                <h1 class="text-base font-semibold text-white">@yield('page-title', 'Dashboard')</h1>
                <p class="text-xs text-slate-400">@yield('page-subtitle', '')</p>
            </div>
            <div class="flex items-center gap-3">
                @auth
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                    {{ auth()->user()->isSuperAdmin() ? 'bg-red-900 text-red-300' : 'bg-blue-900 text-blue-300' }}">
                    {{ auth()->user()->role_label }}
                </span>
                @endauth
                <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="fas fa-circle text-green-400 text-xs"></i>
                    <span>Sistema activo</span>
                </div>
            </div>
        </header>

        <!-- Flash messages -->
        @if(session('success'))
        <div class="mx-6 mt-4 px-4 py-3 rounded-lg text-sm text-green-300 flex items-center gap-2" style="background-color:#052e16; border:1px solid #14532d;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mx-6 mt-4 px-4 py-3 rounded-lg text-sm text-red-300 flex items-center gap-2" style="background-color:#450a0a; border:1px solid #7f1d1d;">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
        @endif

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>

</div>

@livewireScripts
@stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CDR Analyzer') — CDR Analyzer</title>

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dark-bg': '#0f172a',
                        'dark-sidebar': '#1e293b',
                        'dark-card': '#1e293b',
                        'dark-border': '#334155',
                    }
                }
            }
        }
    </script>

    <!-- Heroicons CDN (outline) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @livewireStyles
    @stack('styles')
    <style>
        [x-cloak] { display: none !important; }
        body { background-color: #0f172a; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    </style>
</head>
<body class="h-full text-slate-100 antialiased" style="background-color:#0f172a;">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="flex flex-col w-64 flex-shrink-0" style="background-color:#1e293b; border-right:1px solid #334155;">

        <!-- Logo -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color:#3b82f6;">
                <i class="fas fa-phone-volume text-white text-sm"></i>
            </div>
            <div>
                <p class="font-bold text-white text-sm leading-none">CDR Analyzer</p>
                <p class="text-xs text-slate-500 mt-0.5">(Call Detail Records)</p>

                <p class="font-bold text-slate-300 text-md">Análisis Forense</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @php
                $navItems = [
                    ['route' => '/',         'icon' => 'fas fa-chart-pie',     'label' => 'Dashboard'],
                    ['route' => '/upload',   'icon' => 'fas fa-file-csv',      'label' => 'Importar CSV'],
                    ['route' => '/network',  'icon' => 'fas fa-project-diagram','label' => 'Red de Llamadas'],
                    ['route' => '/analysis', 'icon' => 'fas fa-microscope',    'label' => 'Análisis'],
                    ['route' => '/map',      'icon' => 'fas fa-map-marked-alt', 'label' => 'Mapa GPS'],
                    ['route' => '/contacts', 'icon' => 'fas fa-address-book',  'label' => 'Contactos'],
                    ['route' => '/report',   'icon' => 'fas fa-file-pdf',      'label' => 'Reporte PDF'],
                ];
                $current = '/' . request()->path();
                if ($current === '//') $current = '/';
            @endphp

            @foreach($navItems as $item)
                @php
                    $isActive = ($item['route'] === '/' && request()->is('/'))
                        || ($item['route'] !== '/' && request()->is(ltrim($item['route'], '/').'*'));
                @endphp
                <a href="{{ $item['route'] }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150
                          {{ $isActive
                              ? 'text-white'
                              : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
                   @if($isActive) style="background-color:#3b82f6;" @endif>
                    <i class="{{ $item['icon'] }} w-4 text-center"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Footer info -->
        <div class="px-4 py-3 border-t border-slate-700">
            @php $total = \App\Models\CdrRecord::count(); @endphp
            <p class="text-xs text-slate-500">
                <i class="fas fa-database mr-1"></i>
                {{ number_format($total) }} registros CDR
            </p>
        </div>
    </aside>

    <!-- Main content -->
    <div class="flex flex-col flex-1 overflow-hidden">

        <!-- Topbar -->
        <header class="flex items-center justify-between px-6 py-3 border-b border-slate-700 flex-shrink-0" style="background-color:#1e293b;">
            <div>
                <h1 class="text-base font-semibold text-white">@yield('page-title', 'Dashboard')</h1>
                <p class="text-xs text-slate-400">@yield('page-subtitle', '')</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-400">
                <i class="fas fa-circle text-green-400 text-xs"></i>
                <span>Sistema activo</span>
            </div>
        </header>

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

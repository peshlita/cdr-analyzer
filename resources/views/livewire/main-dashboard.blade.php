@section('title', 'Dashboard General')
@section('page-title', 'Dashboard General')
@section('page-subtitle', 'Plataforma de Inteligencia — CDR-Analizer')

<div class="space-y-6" wire:poll.60s>

    {{-- Bienvenida --}}
    <div class="rounded-xl border border-slate-700 p-5 flex items-center justify-between"
         style="background-color:#1e293b;">
        <div>
            <h2 class="text-xl font-bold text-white">
                Bienvenido, {{ auth()->user()->name }}
            </h2>
            <p class="text-slate-400 text-sm mt-1 capitalize">
                {{ $currentTime }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-xs text-slate-500">Rol</p>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                    {{ auth()->user()->isSuperAdmin() ? 'bg-red-900 text-red-300' : 'bg-blue-900 text-blue-300' }}">
                    {{ auth()->user()->role_label }}
                </span>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center text-base font-bold text-white flex-shrink-0"
                 style="background-color:{{ auth()->user()->isSuperAdmin() ? '#dc2626' : '#3b82f6' }};">
                {{ auth()->user()->initials }}
            </div>
        </div>
    </div>

    {{-- Tarjetas de módulos --}}
    <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">
            <i class="fas fa-th-large mr-1"></i> Módulos Disponibles
        </p>

        @if(empty($modules))
        <div class="rounded-xl border border-slate-700 p-10 text-center" style="background-color:#1e293b;">
            <i class="fas fa-lock text-4xl text-slate-600 mb-3 block"></i>
            <p class="text-slate-400 text-sm">No tienes módulos asignados. Contacta al administrador.</p>
        </div>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach($modules as $mod)
            <a href="{{ $mod['url'] }}"
               class="rounded-xl border border-slate-700 p-4 block transition-all duration-150 hover:border-slate-500 hover:scale-[1.02]"
               style="background-color:#1e293b; border-top: 3px solid {{ $mod['color'] }};">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background-color:{{ $mod['color'] }}20;">
                        <i class="{{ $mod['icon'] }} text-xs" style="color:{{ $mod['color'] }};"></i>
                    </div>
                    <span class="text-white font-semibold text-sm leading-tight">{{ $mod['label'] }}</span>
                </div>
                @if($mod['ready'] && $mod['metric'] !== null)
                    <p class="text-2xl font-bold text-white">{{ number_format($mod['metric']) }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $mod['metric_label'] }}</p>
                @else
                    <div class="flex items-center gap-1.5 mt-2">
                        <i class="fas fa-clock text-xs text-slate-600"></i>
                        <p class="text-xs text-slate-500 italic">Próximamente</p>
                    </div>
                @endif
            </a>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Actividad reciente + Estado del sistema --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Actividad reciente --}}
        <div class="lg:col-span-2 rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                <i class="fas fa-clipboard-list text-blue-400"></i>
                Actividad Reciente
            </h3>

            @if($recentActivity->isEmpty())
            <div class="text-center py-10">
                <i class="fas fa-clipboard-list text-4xl text-slate-600 mb-3 block"></i>
                <p class="text-slate-500 text-sm">Sin actividad registrada aún</p>
            </div>
            @else
            <div class="space-y-2">
                @foreach($recentActivity as $log)
                <div class="flex items-start gap-3 px-3 py-2.5 rounded-lg" style="background-color:#0f172a;">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5"
                         style="background-color:#3b82f620; color:#3b82f6;">
                        {{ strtoupper(mb_substr($log->user_email ?? 'S', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-white text-xs font-medium">{{ $log->user_email ?? 'Sistema' }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded text-slate-300 uppercase tracking-wide"
                                  style="background-color:#334155; font-size:10px;">{{ $log->module }}</span>
                        </div>
                        <p class="text-slate-400 text-xs mt-0.5 truncate">
                            {{ $log->action }}{{ $log->description ? ' — ' . $log->description : '' }}
                        </p>
                    </div>
                    <span class="text-slate-500 text-xs flex-shrink-0 whitespace-nowrap">
                        {{ $log->created_at->locale('es')->diffForHumans() }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Estado del sistema / panel derecho --}}
        <div class="space-y-4">
            @if($systemStats)

            {{-- Stats sistema --}}
            <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
                <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-server text-green-400"></i>
                    Estado del Sistema
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs flex items-center gap-1.5">
                            <i class="fas fa-users text-slate-500"></i> Usuarios activos
                        </span>
                        <span class="text-white font-bold text-sm">{{ $systemStats['total_users'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs flex items-center gap-1.5">
                            <i class="fas fa-circle text-green-400 text-xs"></i> En línea (30 min)
                        </span>
                        <span class="text-green-400 font-bold text-sm">{{ $systemStats['online_users'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs flex items-center gap-1.5">
                            <i class="fas fa-th-large text-blue-400"></i> Módulos habilitados
                        </span>
                        <span class="text-blue-400 font-bold text-sm">{{ $systemStats['enabled_modules'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Últimos accesos --}}
            <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
                <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-sign-in-alt text-yellow-400"></i>
                    Últimos Accesos
                </h3>
                @if($recentLogins->isEmpty())
                <p class="text-slate-500 text-xs text-center py-3">Sin registros de acceso</p>
                @else
                <div class="space-y-2.5">
                    @foreach($recentLogins as $u)
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                             style="background-color:{{ $u->isSuperAdmin() ? '#dc262630' : '#3b82f630' }};
                                    color:{{ $u->isSuperAdmin() ? '#dc2626' : '#3b82f6' }};">
                            {{ $u->initials }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ $u->name }}</p>
                            <p class="text-slate-500 text-xs">
                                {{ $u->last_login_at?->locale('es')->diffForHumans() ?? 'Nunca' }}
                            </p>
                        </div>
                        <span class="text-xs px-1.5 py-0.5 rounded flex-shrink-0
                            {{ $u->isSuperAdmin() ? 'bg-red-900 text-red-300' : 'bg-blue-900 text-blue-300' }}">
                            {{ $u->role_label }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            @else
            {{-- Panel vacío para no-admins --}}
            <div class="rounded-xl border border-slate-700 p-8 text-center" style="background-color:#1e293b;">
                <i class="fas fa-chart-line text-4xl text-slate-600 mb-3 block"></i>
                <p class="text-slate-500 text-sm">Estadísticas del sistema disponibles para administradores</p>
            </div>
            @endif
        </div>
    </div>

</div>

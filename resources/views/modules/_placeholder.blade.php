<div class="flex flex-col items-center justify-center min-h-96 text-center px-4 py-12">

    <!-- Ícono -->
    <div class="w-24 h-24 rounded-3xl flex items-center justify-center mb-6 shadow-2xl"
        style="background-color:{{ $color }}20; border:2px solid {{ $color }}40;">
        <i class="{{ $icon }} text-4xl" style="color:{{ $color }};"></i>
    </div>

    <!-- Badge -->
    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold mb-5"
        style="background-color:{{ $color }}20; color:{{ $color }}; border:1px solid {{ $color }}40;">
        <i class="fas fa-clock"></i> PRÓXIMAMENTE
    </span>

    <!-- Nombre -->
    <h1 class="text-3xl font-bold text-white mb-2">Módulo {{ $name }}</h1>
    <p class="text-slate-400 text-sm mb-6 font-medium">{{ $full }}</p>

    <!-- Descripción -->
    <p class="text-slate-400 max-w-lg text-sm leading-relaxed mb-8">{{ $desc }}</p>

    <!-- Features -->
    <div class="grid grid-cols-2 gap-3 max-w-md w-full">
        @foreach($features as $feature)
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-300"
            style="background-color:#1e293b; border:1px solid #334155;">
            <i class="fas fa-check-circle text-xs" style="color:{{ $color }};"></i>
            {{ $feature }}
        </div>
        @endforeach
    </div>

    <p class="text-slate-600 text-xs mt-10">
        <i class="fas fa-info-circle mr-1"></i>
        Este módulo está en desarrollo. Contacta al administrador para más información.
    </p>
</div>

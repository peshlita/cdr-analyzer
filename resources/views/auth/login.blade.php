<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — CDR-Analizer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>body { background-color: #0f172a; }</style>
</head>
<body class="h-full flex items-center justify-center" style="background-color:#0f172a;">

<div class="w-full max-w-md px-4">

    <!-- Logo -->
    <div class="flex flex-col items-center mb-8">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background-color:#3b82f6;">
            <i class="fas fa-phone-volume text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white">CDR-Analizer</h1>
        <p class="text-slate-400 text-sm mt-1">Plataforma de Inteligencia</p>
    </div>

    <!-- Card -->
    <div class="rounded-2xl p-8 shadow-2xl" style="background-color:#1e293b; border:1px solid #334155;">

        <h2 class="text-lg font-semibold text-white mb-6">Iniciar Sesión</h2>

        @if ($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg text-sm text-red-300" style="background-color:#450a0a; border:1px solid #7f1d1d;">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 px-4 py-3 rounded-lg text-sm text-green-300" style="background-color:#052e16; border:1px solid #14532d;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-envelope mr-1 text-slate-400"></i> Correo electrónico
                </label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-4 py-2.5 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    style="background-color:#0f172a; border:1px solid #334155;"
                    placeholder="usuario@institución.gob">
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-lock mr-1 text-slate-400"></i> Contraseña
                </label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2.5 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    style="background-color:#0f172a; border:1px solid #334155;"
                    placeholder="••••••••">
            </div>

            <!-- Remember -->
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer">
                    <input type="checkbox" name="remember" style="accent-color:#3b82f6;">
                    Recordar sesión
                </label>
                @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-blue-400 hover:text-blue-300">
                    ¿Olvidaste tu contraseña?
                </a>
                @endif
            </div>

            <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition-colors hover:opacity-90"
                style="background-color:#3b82f6;">
                <i class="fas fa-sign-in-alt mr-2"></i> Iniciar Sesión
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-slate-600 mt-6">
        <i class="fas fa-shield-alt mr-1"></i>
        Sistema de uso exclusivo para personal autorizado
    </p>
</div>
</body>
</html>

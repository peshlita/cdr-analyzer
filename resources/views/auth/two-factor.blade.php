<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación 2FA — CDR-Analizer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>body { background-color: #0f172a; }</style>
</head>
<body class="h-full flex items-center justify-center" style="background-color:#0f172a;">

<div class="w-full max-w-md px-4">

    <div class="flex flex-col items-center mb-8">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background-color:#7c3aed;">
            <i class="fas fa-shield-alt text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white">Verificación en 2 pasos</h1>
        <p class="text-slate-400 text-sm mt-1">Ingresa el código de Google Authenticator</p>
    </div>

    <div class="rounded-2xl p-8 shadow-2xl" style="background-color:#1e293b; border:1px solid #334155;">

        @if ($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg text-sm text-red-300" style="background-color:#450a0a; border:1px solid #7f1d1d;">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-key mr-1 text-slate-400"></i> Código de 6 dígitos
                </label>
                <input type="text" name="code" required autofocus maxlength="6"
                    inputmode="numeric" pattern="[0-9]*"
                    class="w-full px-4 py-3 rounded-lg text-2xl text-center font-mono tracking-widest text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    style="background-color:#0f172a; border:1px solid #334155; letter-spacing:0.5em;"
                    placeholder="000000">
            </div>

            <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition-colors hover:opacity-90"
                style="background-color:#7c3aed;">
                <i class="fas fa-check-circle mr-2"></i> Verificar Código
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-sm text-slate-400 hover:text-slate-300">
                <i class="fas fa-arrow-left mr-1"></i> Volver al inicio de sesión
            </a>
        </div>
    </div>
</div>
</body>
</html>

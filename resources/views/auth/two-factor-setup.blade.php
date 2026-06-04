@extends('layouts.app')

@section('title', 'Configurar 2FA')
@section('page-title', 'Autenticación de Dos Factores')
@section('page-subtitle', 'Escanea el QR con Google Authenticator')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="rounded-xl p-6 shadow-lg" style="background-color:#1e293b; border:1px solid #334155;">

        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color:#7c3aed;">
                <i class="fas fa-shield-alt text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-white">Configurar Google Authenticator</h2>
                <p class="text-sm text-slate-400">Protege tu cuenta con verificación en 2 pasos</p>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg text-sm text-red-300" style="background-color:#450a0a; border:1px solid #7f1d1d;">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Pasos -->
        <div class="space-y-4 mb-6">
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background-color:#7c3aed;">1</span>
                <p class="text-sm text-slate-300 pt-1">Instala <strong class="text-white">Google Authenticator</strong> en tu teléfono (App Store o Google Play)</p>
            </div>
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background-color:#7c3aed;">2</span>
                <p class="text-sm text-slate-300 pt-1">Abre la app y toca <strong class="text-white">"+"</strong> → <strong class="text-white">"Escanear código QR"</strong></p>
            </div>
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background-color:#7c3aed;">3</span>
                <p class="text-sm text-slate-300 pt-1">Escanea el código QR de abajo:</p>
            </div>
        </div>

        <!-- QR Code -->
        <div class="flex justify-center mb-6">
            <div class="p-4 bg-white rounded-xl">
                <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR Code 2FA" class="w-48 h-48">
            </div>
        </div>

        <!-- Confirmar código -->
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-key mr-1"></i> Ingresa el código de la app para confirmar:
                </label>
                <input type="text" name="code" required maxlength="6" inputmode="numeric"
                    class="w-full px-4 py-3 rounded-lg text-xl text-center font-mono tracking-widest text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    style="background-color:#0f172a; border:1px solid #334155; letter-spacing:0.5em;"
                    placeholder="000000">
            </div>

            <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                style="background-color:#7c3aed;">
                <i class="fas fa-check mr-2"></i> Activar 2FA
            </button>
        </form>
    </div>
</div>
@endsection

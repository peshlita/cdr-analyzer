<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function showChallenge()
    {
        if (!session('two_factor_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.two-factor');
    }

    public function challenge(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = session('two_factor_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Código inválido. Inténtalo de nuevo.']);
        }

        auth()->login($user);
        session()->forget('two_factor_user_id');
        session(['two_factor_verified' => true]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        AuditLogger::log('login', '2FA verificado correctamente');

        return redirect()->intended(route('dashboard'));
    }

    public function showSetup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        if (!$user->two_factor_secret) {
            $secret = $google2fa->generateSecretKey();
            $user->update(['two_factor_secret' => $secret]);
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $qrCode = $this->generateQrCode($qrCodeUrl);

        return view('auth.two-factor-setup', compact('qrCode', 'user'));
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Código inválido. Escanea el QR e inténtalo de nuevo.']);
        }

        $user->update(['two_factor_enabled' => true]);
        session(['two_factor_verified' => true]);
        AuditLogger::log('2fa_enabled', '2FA activado por el usuario', null);

        return redirect()->route('dashboard')->with('success', '2FA activado correctamente.');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);

        $user = $request->user();
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret'  => null,
        ]);

        AuditLogger::log('2fa_disabled', '2FA desactivado por el usuario');

        return redirect()->route('dashboard')->with('success', '2FA desactivado.');
    }

    private function generateQrCode(string $url): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);
        return base64_encode($writer->writeString($url));
    }
}

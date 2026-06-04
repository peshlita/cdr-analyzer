<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'institution_name'   => 'required|string|max:255',
            'session_lifetime'   => 'required|integer|min:5|max:480',
            'force_2fa'          => 'nullable|boolean',
        ]);

        $force2fa = $request->boolean('force_2fa');

        Setting::updateOrCreate(['key' => 'institution_name'], ['value' => $data['institution_name']]);
        Setting::updateOrCreate(['key' => 'session_lifetime'], ['value' => $data['session_lifetime']]);
        Setting::updateOrCreate(['key' => 'force_2fa'], ['value' => $force2fa ? '1' : '0']);

        if ($force2fa) {
            // Marcar todos los analistas para que configuren 2FA
            User::where('role', 'analyst')->update(['two_factor_enabled' => true]);
        }

        AuditLogger::log('update_settings', 'Configuración general actualizada');

        return back()->with('success', 'Configuración guardada correctamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount(['users', 'gpsUnits'])
            ->orderBy('name')
            ->get();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:255',
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name']);
        // Garantizar unicidad del slug
        $base = $slug ?: 'tenant';
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        $tenant = Tenant::create([
            'name'          => $data['name'],
            'slug'          => $slug,
            'contact_email' => $data['contact_email'] ?? null,
            'is_active'     => true,
        ]);

        // Admin de la institución: super_admin CON tenant
        $admin = User::create([
            'name'      => $data['admin_name'],
            'email'     => $data['admin_email'],
            'password'  => $data['admin_password'],
            'role'      => 'super_admin',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        AuditLogger::log('create_tenant', "Institución creada: {$tenant->name} ({$tenant->slug}), admin {$admin->email}");

        return redirect()->route('admin.tenants.index')
            ->with('success', "Institución {$tenant->name} creada con su administrador.");
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'is_active'     => 'sometimes|boolean',
        ]);

        $tenant->update([
            'name'          => $data['name'],
            'contact_email' => $data['contact_email'] ?? null,
            'is_active'     => $request->boolean('is_active', $tenant->is_active),
        ]);

        AuditLogger::log('update_tenant', "Institución actualizada: {$tenant->name}");

        return back()->with('success', 'Institución actualizada correctamente.');
    }

    public function toggle(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);
        $estado = $tenant->is_active ? 'activada' : 'desactivada';

        AuditLogger::log('toggle_tenant', "Institución {$tenant->name} {$estado}");

        return back()->with('success', "Institución {$estado} correctamente.");
    }
}

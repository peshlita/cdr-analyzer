<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'last_login_at'      => 'datetime',
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Super Admin GLOBAL: super_admin sin tenant → ve todas las instituciones.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->role === 'super_admin' && !$this->tenant_id;
    }

    /**
     * Admin de una institución: super_admin con tenant asignado.
     */
    public function isTenantAdmin(): bool
    {
        return $this->role === 'super_admin' && (bool) $this->tenant_id;
    }

    /**
     * Limita una consulta de usuarios a los visibles para $admin:
     *  - admin global → todos.
     *  - admin de institución → solo los de su tenant.
     */
    public function scopeVisibleTo(Builder $query, User $admin): Builder
    {
        if ($admin->isGlobalAdmin()) {
            return $query;
        }

        return $query->where('tenant_id', $admin->tenant_id);
    }

    /**
     * ¿$admin puede gestionar (editar/permisos/reset) a este usuario?
     */
    public function isManageableBy(User $admin): bool
    {
        if ($admin->isGlobalAdmin()) {
            return true;
        }
        if ($admin->isTenantAdmin()) {
            return $this->tenant_id === $admin->tenant_id;
        }

        return false;
    }

    public function hasModuleAccess(string $module): bool
    {
        if ($this->isSuperAdmin()) return true;

        return $this->modulePermissions()
            ->where('module_slug', $module)
            ->where('enabled', true)
            ->exists();
    }

    public function modulePermissions()
    {
        return $this->hasMany(UserModulePermission::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(mb_substr($word, 0, 1));
        }
        return $initials ?: '?';
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'super_admin' => $this->tenant_id ? 'Admin Institución' : 'Admin Global',
            'analyst'     => 'Analista',
            default       => $this->role,
        };
    }
}

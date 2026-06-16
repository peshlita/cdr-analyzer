<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aislamiento de datos jerárquico (institución + propietario).
 *
 * Al CREAR un modelo asigna automáticamente:
 *   - tenant_id  = institución del usuario autenticado
 *   - user_id    = el usuario autenticado (si la tabla tiene esa columna)
 *
 * Al CONSULTAR aplica un global scope según el rol:
 *   - Super Admin GLOBAL  (super_admin, sin tenant)  → ve TODO.
 *   - Admin de institución (super_admin, con tenant)  → ve todo lo de SU tenant.
 *   - Analista / cualquier otro                        → ve SOLO lo suyo (user_id).
 *
 * Procesos de consola (TCP listener, simuladores, seeders) corren SIN usuario
 * autenticado: el scope no se aplica y deben asignar tenant_id/user_id
 * explícitamente y usar withoutGlobalScope('tenant') cuando filtren.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            $user = auth()->user();
            if (!$user) {
                return;
            }

            if (empty($model->tenant_id) && $user->tenant_id) {
                $model->tenant_id = $user->tenant_id;
            }

            // Asignar propietario (columna configurable; por defecto user_id).
            $col = $model->tenantOwnerColumn();
            if (empty($model->{$col}) && in_array($col, $model->getFillable(), true)) {
                $model->{$col} = $user->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $query) {
            $user = auth()->user();
            if (!$user) {
                return; // procesos de consola → sin filtro
            }

            // Super Admin global (sin tenant) ve todo.
            if ($user->role === 'super_admin' && !$user->tenant_id) {
                return;
            }

            $table = $query->getModel()->getTable();

            // Admin de institución: todo lo de su tenant (todos los usuarios).
            if ($user->role === 'super_admin' && $user->tenant_id) {
                $query->where("{$table}.tenant_id", $user->tenant_id);
                return;
            }

            // Analista / resto: SOLO sus propios registros.
            $col = $query->getModel()->tenantOwnerColumn();
            $query->where("{$table}.{$col}", $user->id);
        });
    }

    /** Columna de propietario para el aislamiento por usuario (override por modelo). */
    public function tenantOwnerColumn(): string
    {
        return 'user_id';
    }

    /**
     * Devuelve la cláusula SQL de aislamiento para inyectar en consultas RAW
     * (DB::select/DB::table con SQL crudo), que NO aplican el global scope.
     * Retorna ['sql' => ' AND tabla.col = ?', 'bindings' => [...]].
     */
    public static function ownershipSql(?string $table = null): array
    {
        $table = $table ?: (new static)->getTable();
        $user  = auth()->user();

        if (!$user) {
            return ['sql' => '', 'bindings' => []];
        }
        if ($user->role === 'super_admin' && !$user->tenant_id) {
            return ['sql' => '', 'bindings' => []];
        }
        if ($user->role === 'super_admin' && $user->tenant_id) {
            return ['sql' => " AND {$table}.tenant_id = ? ", 'bindings' => [$user->tenant_id]];
        }

        return ['sql' => " AND {$table}.user_id = ? ", 'bindings' => [$user->id]];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class GpsUnit extends Model
{
    protected $fillable = [
        'name', 'imei', 'plate', 'unit_type', 'sim_number',
        'color', 'icon', 'is_active',
        'last_seen_at', 'last_lat', 'last_lon', 'last_speed', 'notes',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
        'last_lat'     => 'float',
        'last_lon'     => 'float',
        'last_speed'   => 'float',
    ];

    // ── Relationships ───────────────────────────────────────

    public function positions()
    {
        return $this->hasMany(GpsPosition::class);
    }

    public function geofences()
    {
        return $this->belongsToMany(Geofence::class, 'geofence_alerts', 'gps_unit_id', 'geofence_id');
    }

    public function alerts()
    {
        return $this->hasMany(GeofenceAlert::class);
    }

    // ── Accessors ───────────────────────────────────────────

    public function getStatusAttribute(): string
    {
        if (!$this->last_seen_at) {
            return 'offline';
        }

        $minutesAgo = $this->last_seen_at->diffInMinutes(now());

        if ($minutesAgo < 5)  return 'online';
        if ($minutesAgo < 30) return 'idle';
        return 'offline';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->extended_status) {
            'online'    => '#10b981',
            'parked'    => '#3b82f6',
            'overnight' => '#8b5cf6',
            'idle'      => '#f59e0b',
            default     => '#6b7280',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->extended_status) {
            'online'    => 'En línea',
            'parked'    => 'Estacionado',
            'overnight' => 'Pernocta',
            'idle'      => 'Inactivo',
            default     => 'Offline',
        };
    }

    public function getExtendedStatusAttribute(): string
    {
        if (!$this->last_seen_at) {
            return 'offline';
        }

        $minutesAgo = (int) $this->last_seen_at->diffInMinutes(now());

        if ($minutesAgo > 120) return 'offline';
        if ($minutesAgo > 30)  return 'idle';

        // Sin movimiento
        if (($this->last_speed ?? 0) < 1) {
            $hour = (int) now()->format('H');
            $isNight = $hour >= 22 || $hour < 6;

            if ($isNight && $minutesAgo > 30) return 'overnight';
            if ($minutesAgo > 10)             return 'parked';
        }

        return 'online';
    }

    public function getExtendedStatusColorAttribute(): string
    {
        return $this->status_color;
    }

    public function getExtendedStatusLabelAttribute(): string
    {
        return $this->status_label;
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePatrol(Builder $query): Builder
    {
        return $query->where('unit_type', 'patrol');
    }

    public function scopeCovert(Builder $query): Builder
    {
        return $query->where('unit_type', 'covert');
    }
}

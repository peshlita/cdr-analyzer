<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GpsVehicle extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name', 'description', 'plate', 'unit_type', 'color', 'icon',
        'source', 'gps_unit_id', 'source_file', 'source_format',
        'total_points', 'date_from', 'date_to', 'imported_by', 'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'total_points' => 'integer',
        'date_from'    => 'datetime',
        'date_to'      => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function points()
    {
        return $this->hasMany(GpsVehiclePoint::class, 'vehicle_id');
    }

    public function gpsUnit()
    {
        return $this->belongsTo(GpsUnit::class, 'gps_unit_id');
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('source', 'live');
    }

    public function scopeImported(Builder $query): Builder
    {
        return $query->where('source', 'imported');
    }

    // ── Accessors ───────────────────────────────────────────

    public function getStatusAttribute(): string
    {
        if ($this->source === 'live') {
            return $this->gpsUnit?->extended_status ?? 'offline';
        }

        return 'imported';
    }

    public function getLastPointAttribute()
    {
        if ($this->source === 'live') {
            if (!$this->gps_unit_id) {
                return null;
            }

            return GpsPosition::where('gps_unit_id', $this->gps_unit_id)
                ->orderByDesc('received_at')
                ->first();
        }

        return GpsVehiclePoint::where('vehicle_id', $this->id)
            ->orderByDesc('recorded_at')
            ->first();
    }
}

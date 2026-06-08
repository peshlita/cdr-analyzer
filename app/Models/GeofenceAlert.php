<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeofenceAlert extends Model
{
    protected $fillable = [
        'geofence_id', 'gps_unit_id', 'alert_type',
        'lat', 'lon', 'triggered_at',
        'acknowledged', 'acknowledged_by', 'acknowledged_at',
    ];

    protected $casts = [
        'lat'              => 'float',
        'lon'              => 'float',
        'triggered_at'     => 'datetime',
        'acknowledged'     => 'boolean',
        'acknowledged_at'  => 'datetime',
    ];

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }

    public function unit()
    {
        return $this->belongsTo(GpsUnit::class, 'gps_unit_id');
    }

    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}

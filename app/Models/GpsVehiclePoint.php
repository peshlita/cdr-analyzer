<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GpsVehiclePoint extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_id', 'lat', 'lon', 'speed', 'heading', 'battery', 'recorded_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lon'         => 'float',
        'speed'       => 'float',
        'heading'     => 'float',
        'recorded_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(GpsVehicle::class, 'vehicle_id');
    }
}

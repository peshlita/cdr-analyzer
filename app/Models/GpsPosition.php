<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsPosition extends Model
{
    protected $fillable = [
        'gps_unit_id', 'lat', 'lon', 'speed', 'heading',
        'altitude', 'satellites', 'accuracy', 'raw_data', 'received_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lon'         => 'float',
        'speed'       => 'float',
        'heading'     => 'float',
        'altitude'    => 'float',
        'received_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(GpsUnit::class, 'gps_unit_id');
    }
}

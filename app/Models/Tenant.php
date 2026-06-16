<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'contact_email', 'logo', 'is_active', 'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function gpsUnits()
    {
        return $this->hasMany(GpsUnit::class);
    }

    public function geofences()
    {
        return $this->hasMany(Geofence::class);
    }
}

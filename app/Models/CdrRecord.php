<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CdrRecord extends Model
{
    protected $fillable = [
        'number_a', 'lat_a', 'lon_a', 'azimuth_a', 'imei_a', 'imsi_a',
        'number_b', 'lat_b', 'lon_b', 'azimuth_b', 'imei_b', 'imsi_b',
        'type', 'direction', 'duration', 'date', 'hour',
    ];

    protected $casts = [
        'date' => 'date',
        'lat_a' => 'float',
        'lon_a' => 'float',
        'azimuth_a' => 'float',
        'lat_b' => 'float',
        'lon_b' => 'float',
        'azimuth_b' => 'float',
        'duration' => 'integer',
    ];

    public function contactA()
    {
        return $this->belongsTo(PhoneContact::class, 'number_a', 'phone_number');
    }

    public function contactB()
    {
        return $this->belongsTo(PhoneContact::class, 'number_b', 'phone_number');
    }

    public static function getTargetNumber(): ?string
    {
        $fromSettings = \App\Models\Setting::get('target_number');
        if ($fromSettings) return $fromSettings;

        $result = self::selectRaw('number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')
            ->groupBy('number_a')
            ->orderByDesc('cnt')
            ->first();

        return $result?->number_a;
    }
}

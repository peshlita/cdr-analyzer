<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name', 'description', 'type',
        'center_lat', 'center_lon', 'radius',
        'coordinates', 'color',
        'is_active', 'alert_on_enter', 'alert_on_exit',
    ];

    protected $casts = [
        'center_lat'     => 'float',
        'center_lon'     => 'float',
        'radius'         => 'float',
        'coordinates'    => 'array',
        'is_active'      => 'boolean',
        'alert_on_enter' => 'boolean',
        'alert_on_exit'  => 'boolean',
    ];

    public function alerts()
    {
        return $this->hasMany(GeofenceAlert::class);
    }

    /**
     * Determine if a point is inside this geofence.
     * Supports circle and polygon types.
     */
    public function containsPoint(float $lat, float $lon): bool
    {
        if ($this->type === 'circle') {
            return $this->pointInCircle($lat, $lon);
        }

        if ($this->type === 'polygon' && !empty($this->coordinates)) {
            return $this->pointInPolygon($lat, $lon, $this->coordinates);
        }

        return false;
    }

    private function pointInCircle(float $lat, float $lon): bool
    {
        if (!$this->center_lat || !$this->center_lon || !$this->radius) {
            return false;
        }

        $earthRadius = 6371000; // metres
        $dLat = deg2rad($lat - $this->center_lat);
        $dLon = deg2rad($lon - $this->center_lon);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->center_lat)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;
        $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distance <= $this->radius;
    }

    private function pointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lon'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lon'];

            $intersect = (($yi > $lon) !== ($yj > $lon))
                && ($lat < ($xj - $xi) * ($lon - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CdrRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id',
        // Legacy (consumido por Network/Analysis/Map/Report/Dashboards)
        'number_a', 'lat_a', 'lon_a', 'azimuth_a', 'imei_a', 'imsi_a',
        'number_b', 'lat_b', 'lon_b', 'azimuth_b', 'imei_b', 'imsi_b',
        'type', 'direction', 'duration', 'date', 'hour', 'source_file',
        // Normalizado (rediseño de importación)
        'batch_id', 'phone_main', 'record_type', 'contact_number', 'flow',
        'call_datetime', 'lat', 'lon', 'raw_lat', 'raw_lon', 'imei', 'azimuth',
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
        'call_datetime' => 'datetime',
        'lat' => 'float',
        'lon' => 'float',
        'azimuth' => 'integer',
    ];

    public function batch()
    {
        return $this->belongsTo(CdrBatch::class, 'batch_id', 'batch_id');
    }

    // ── Scopes de análisis (sobre columnas normalizadas) ──
    public function scopeVoice($q)    { return $q->whereIn('record_type', ['VOZ_ENTRANTE', 'VOZ_SALIENTE', 'VOZ_TRANSITO']); }
    public function scopeMessages($q) { return $q->whereIn('record_type', ['MSG_ENTRANTE', 'MSG_SALIENTE']); }
    public function scopeData($q)     { return $q->where('record_type', 'DATOS'); }
    public function scopeWithLocation($q) { return $q->whereNotNull('lat')->whereNotNull('lon'); }
    public function scopeIncoming($q) { return $q->where('flow', 'in'); }
    public function scopeOutgoing($q) { return $q->where('flow', 'out'); }

    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration) return '—';
        $m = intdiv($this->duration, 60);
        $s = $this->duration % 60;
        return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
    }

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
        $fromSettings = \App\Models\Setting::getUser('target_number');
        if ($fromSettings) return $fromSettings;

        $result = self::selectRaw('number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')
            ->groupBy('number_a')
            ->orderByDesc('cnt')
            ->first();

        return $result?->number_a;
    }
}

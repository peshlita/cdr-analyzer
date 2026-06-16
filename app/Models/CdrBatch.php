<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CdrBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'imported_by', 'batch_id', 'name', 'phone_main',
        'filename', 'encoding', 'total_records', 'records_with_location',
        'voice_count', 'sms_count', 'data_count', 'date_from', 'date_to',
        'cross_analysis',
    ];

    protected $casts = [
        'date_from'      => 'datetime',
        'date_to'        => 'datetime',
        'cross_analysis' => 'array',
    ];

    /** El propietario de una sábana es imported_by (no user_id). */
    public function tenantOwnerColumn(): string
    {
        return 'imported_by';
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function records()
    {
        return $this->hasMany(CdrRecord::class, 'batch_id', 'batch_id');
    }
}

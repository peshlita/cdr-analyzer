<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneContact extends Model
{
    protected $fillable = [
        'phone_number', 'name', 'alias', 'notes', 'image_path',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->alias ?? $this->phone_number;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return asset('storage/' . $this->image_path);
    }
}

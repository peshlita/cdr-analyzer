<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModulePermission extends Model
{
    protected $fillable = ['user_id', 'module_slug', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

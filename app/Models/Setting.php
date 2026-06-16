<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'user_id'];

    /**
     * Lee una configuración GLOBAL (compartida por todo el sistema, user_id null).
     * Para configuración por usuario usar getUser().
     */
    public static function get(string $key, $default = null)
    {
        try {
            return static::where('key', $key)->whereNull('user_id')->value('value') ?? $default;
        } catch (\Exception) {
            return $default;
        }
    }

    public static function set(string $key, $value): void
    {
        try {
            static::updateOrCreate(['key' => $key, 'user_id' => null], ['value' => $value]);
        } catch (\Exception) {}
    }

    /**
     * Lee una configuración PROPIA del usuario autenticado (aislada por usuario).
     * Ej: target_number — cada investigador tiene el suyo.
     */
    public static function getUser(string $key, $default = null)
    {
        try {
            $uid = auth()->id();
            if (!$uid) return $default;
            return static::where('key', $key)->where('user_id', $uid)->value('value') ?? $default;
        } catch (\Exception) {
            return $default;
        }
    }

    public static function setUser(string $key, $value): void
    {
        try {
            $uid = auth()->id();
            if (!$uid) return;
            static::updateOrCreate(['key' => $key, 'user_id' => $uid], ['value' => $value]);
        } catch (\Exception) {}
    }
}

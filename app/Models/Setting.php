<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get($key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set($key, $value)
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getSecure($key, $default = null)
    {
        $value = static::where('key', $key)->value('value');
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Backward compatibility for previously plain-text values.
            return $value;
        }
    }

    public static function setSecure($key, $value)
    {
        if ($value === null || $value === '') {
            static::set($key, null);
            return;
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString((string) $value)]
        );
    }
}

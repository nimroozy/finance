<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function getDecodedValueAttribute(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->is_encrypted) {
            return Crypt::decryptString($this->value);
        }

        return $this->value;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->decoded_value;
    }

    public static function setValue(string $key, ?string $value, bool $encrypt = false): self
    {
        $stored = $encrypt && $value !== null
            ? Crypt::encryptString($value)
            : $value;

        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'is_encrypted' => $encrypt,
            ]
        );
    }
}

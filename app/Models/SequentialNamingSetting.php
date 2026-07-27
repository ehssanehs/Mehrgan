<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SequentialNamingSetting extends Model
{
    protected $fillable = [
        'prefix',
        'counter',
        'is_enabled',
    ];

    protected $casts = [
        'counter' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public static function getSettings(): self
    {
        $setting = self::first();
        if (!$setting) {
            $setting = self::create([
                'prefix' => 'server1u',
                'counter' => 0,
                'is_enabled' => false,
            ]);
        }
        return $setting;
    }

    public function getNextCounter(): int
    {
        // Atomic increment
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $locked = self::lockForUpdate()->first();
            if (!$locked) {
                $locked = self::create([
                    'prefix' => $this->prefix,
                    'counter' => 0,
                    'is_enabled' => $this->is_enabled,
                ]);
            }
            $locked->counter = $locked->counter + 1;
            $locked->save();
            return $locked->counter;
        });
    }

    public static function generateNextName(?string $fallback = null): string
    {
        $settings = self::getSettings();
        if (!$settings->is_enabled) {
            return $fallback ?? 'user-' . uniqid();
        }

        $counter = $settings->getNextCounter();
        return $settings->prefix . $counter;
    }

    public static function isEnabled(): bool
    {
        return (bool) self::getSettings()->is_enabled;
    }

    public static function currentCounter(): int
    {
        return self::getSettings()->counter;
    }

    public static function currentPrefix(): string
    {
        return self::getSettings()->prefix;
    }
}

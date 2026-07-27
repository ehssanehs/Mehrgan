<?php

namespace App\Services;

use App\Models\SequentialNamingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientNamingService
{
    /**
     * Generate a client name.
     *
     * If sequential naming is enabled, returns prefix + incremented counter.
     * Otherwise returns provided fallback or old pattern.
     *
     * @param int|null $userId User ID for fallback old pattern
     * @param int|null $orderId Order ID for fallback old pattern
     * @param string|null $customName If custom name provided, use it directly (takes precedence)
     * @return string
     */
    public static function generate(?int $userId = null, ?int $orderId = null, ?string $customName = null): string
    {
        if ($customName && trim($customName) !== '') {
            return trim($customName);
        }

        $settings = SequentialNamingSetting::getSettings();

        if ($settings->is_enabled) {
            try {
                return DB::transaction(function () use ($settings) {
                    // Lock row for update to prevent race conditions
                    $locked = SequentialNamingSetting::lockForUpdate()->first();
                    if (!$locked) {
                        $locked = SequentialNamingSetting::create([
                            'prefix' => 'server1u',
                            'counter' => 0,
                            'is_enabled' => true,
                        ]);
                    }
                    $locked->counter = $locked->counter + 1;
                    $locked->save();
                    $name = $locked->prefix . $locked->counter;
                    Log::info('Sequential client name generated', [
                        'name' => $name,
                        'prefix' => $locked->prefix,
                        'counter' => $locked->counter,
                    ]);
                    return $name;
                });
            } catch (\Exception $e) {
                Log::error('Failed to generate sequential name, falling back', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fallback to old pattern
            }
        }

        // Fallback old naming: user-{userId}-order-{orderId} or random
        if ($userId !== null && $orderId !== null) {
            return "user-{$userId}-order-{$orderId}";
        }

        return 'user-' . uniqid();
    }

    /**
     * Legacy method for backward compatibility - generate with user and order IDs
     */
    public static function generateForOrder(int $userId, int $orderId, ?string $customUsername = null): string
    {
        return self::generate($userId, $orderId, $customUsername);
    }

    /**
     * Check if sequential naming is enabled
     */
    public static function isEnabled(): bool
    {
        return SequentialNamingSetting::isEnabled();
    }

    /**
     * Update settings - handles prefix change logic (reset counter)
     */
    public static function updateSettings(bool $enabled, string $prefix): SequentialNamingSetting
    {
        $settings = SequentialNamingSetting::getSettings();
        $oldPrefix = $settings->prefix;

        // If prefix changed, reset counter to 0
        if ($oldPrefix !== $prefix) {
            $settings->counter = 0;
            Log::info('Sequential naming prefix changed, counter reset', [
                'old_prefix' => $oldPrefix,
                'new_prefix' => $prefix,
            ]);
        }

        $settings->prefix = $prefix;
        $settings->is_enabled = $enabled;
        $settings->save();

        return $settings;
    }

    public static function resetCounter(): SequentialNamingSetting
    {
        $settings = SequentialNamingSetting::getSettings();
        $settings->counter = 0;
        $settings->save();

        Log::info('Sequential naming counter reset', [
            'prefix' => $settings->prefix,
        ]);

        return $settings;
    }
}

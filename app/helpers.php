<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('escapeTelegramHTML')) {
    /**
     * Escapes text for Telegram's HTML parse mode.
     */
    function escapeTelegramHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}


if (!function_exists('setting')) {
    /**
     * Get a setting value from the database.
     * Uses cache for better performance.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    function setting($key, $default = null)
    {

        $settings = Cache::rememberForever('settings', function () {
            return Setting::all()->pluck('value', 'key');
        });


        return $settings->get($key, $default);
    }
}

if (!function_exists('getTelegramAdminChatIds')) {
    /**
     * Get Telegram admin chat IDs as an array.
     * Handles backward compatibility with old single-value format.
     *
     * @param \Illuminate\Support\Collection|null $settings Optional settings collection
     * @return array
     */
    function getTelegramAdminChatIds($settings = null): array
    {
        if ($settings === null) {
            $raw = setting('telegram_admin_chat_id');
        } else {
            $raw = $settings->get('telegram_admin_chat_id');
        }

        if (empty($raw)) {
            return [];
        }

        // New format: JSON array
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_filter(array_map('strval', $decoded));
        }

        // Old format: single numeric string
        if (is_numeric($raw)) {
            return [(string) $raw];
        }

        // Fallback: comma/space separated
        $parts = preg_split('/[,\s،]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        return !empty($parts) ? array_map('trim', $parts) : [];
    }
}

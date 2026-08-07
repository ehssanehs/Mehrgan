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

        // New format: JSON array. Also handles accidentally double-encoded arrays.
        $decoded = Setting::decodeArrayValue($raw);
        if (! empty($decoded)) {
            return array_filter(array_map('strval', $decoded));
        }

        // Old format: single numeric string
        if (is_numeric($raw)) {
            return [(string) $raw];
        }

        // Fallback: comma/space separated
        $parts = preg_split('/[,\s،]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        return !empty($parts) ? array_map('trim', $parts) : [];
    }
}

if (!function_exists('to_jalali_date')) {
    /**
     * Convert a Gregorian date/Carbon/string/DateTime into a formatted Jalali (Shamsi/Persian) date string.
     *
     * @param mixed $date       Accepts Carbon, DateTimeInterface, string, or null.
     * @param string $format    Format tokens: Y m d H i s (year, month, day, hour, minute, second).
     * @param string|null $null Value returned when $date is empty/null.
     * @return string|null
     */
    function to_jalali_date($date, string $format = 'Y/m/d', ?string $null = 'نامحدود')
    {
        if (empty($date)) {
            return $null;
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                $dt = $date;
            } else {
                $dt = new \DateTimeImmutable((string) $date);
            }
        } catch (\Throwable $e) {
            return $null;
        }

        $gy = (int) $dt->format('Y');
        $gm = (int) $dt->format('n');
        $gd = (int) $dt->format('j');

        // Gregorian -> Jalali algorithm (Roozbeh Pournader / Mohammad Toossi)
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
              + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        $tokens = [
            'Y' => (string) $jy,
            'y' => substr(str_pad((string) ($jy % 100), 2, '0', STR_PAD_LEFT), -2),
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'H' => $dt->format('H'),
            'i' => $dt->format('i'),
            's' => $dt->format('s'),
        ];

        // Simple char-by-char replacement so tokens don't collide with earlier substitutions.
        $out = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $ch = $format[$i];
            $out .= $tokens[$ch] ?? $ch;
        }
        return $out;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VlessParserService
{
    const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    const VLESS_URI_REGEX = '/^vless:\/\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})@/i';

    /**
     * Validate if string is a valid UUID v4 (or any UUID)
     */
    public static function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match(self::UUID_REGEX, trim($uuid));
    }

    /**
     * Extract UUID from a single VLESS URI
     *
     * @param string $vlessUri
     * @return string|null UUID or null if invalid
     */
    public static function extractUuidFromVless(string $vlessUri): ?string
    {
        $vlessUri = trim($vlessUri);

        // Quick check
        if (!Str::startsWith(strtolower($vlessUri), 'vless://')) {
            return null;
        }

        // Try regex extraction
        if (preg_match(self::VLESS_URI_REGEX, $vlessUri, $matches)) {
            $uuid = $matches[1];
            if (self::isValidUuid($uuid)) {
                return strtolower($uuid);
            }
        }

        // Fallback: use parse_url
        try {
            $parsed = parse_url($vlessUri);
            if ($parsed && isset($parsed['user'])) {
                $uuid = $parsed['user'];
                // User may contain uuid:password or just uuid
                // VLESS typically user is uuid
                if (self::isValidUuid($uuid)) {
                    return strtolower($uuid);
                }
            }

            // Some clients encode as vless://uuid@... parse_url user might be in 'user' or host?
            // Additional fallback: extract between vless:// and @
            $between = Str::between($vlessUri, 'vless://', '@');
            // Remove any path query that might have been included due to missing @?
            if ($between && !Str::contains($between, '/') && !Str::contains($between, '?') && !Str::contains($between, '#')) {
                if (self::isValidUuid($between)) {
                    return strtolower($between);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse VLESS URI', [
                'uri' => Str::limit($vlessUri, 100),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Parse subscription content (plain or base64) and extract VLESS URIs
     *
     * @param string $content Raw subscription content (may be base64 encoded)
     * @return array List of ['uri' => string, 'uuid' => string]
     */
    public static function parseSubscriptionContent(string $content): array
    {
        $content = trim($content);
        if (empty($content)) {
            return [];
        }

        // Try base64 decode - subscription often base64 encoded
        $decoded = base64_decode($content, true);
        // Also try non-strict with trimmed whitespace
        if ($decoded === false) {
            // Try with whitespace removed and padding fixed
            $cleaned = preg_replace('/\s+/', '', $content);
            // Add padding if needed
            $mod = strlen($cleaned) % 4;
            if ($mod !== 0) {
                $cleaned .= str_repeat('=', 4 - $mod);
            }
            $decoded = base64_decode($cleaned, true);
        }

        $workingContent = $content;
        if ($decoded !== false && self::containsVless($decoded)) {
            $workingContent = $decoded;
            Log::info('Subscription content was base64 decoded');
        } elseif ($decoded !== false && preg_match('/^(vless|vmess|trojan|ss):\/\//m', $decoded)) {
            $workingContent = $decoded;
        }

        $lines = preg_split('/\r\n|\r|\n/', $workingContent);
        $results = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Only process VLESS for now, but could support other types
            if (Str::startsWith(strtolower($line), 'vless://')) {
                $uuid = self::extractUuidFromVless($line);
                if ($uuid) {
                    $results[] = [
                        'uri' => $line,
                        'uuid' => $uuid,
                    ];
                } else {
                    Log::warning('Found VLESS line but failed to extract UUID', [
                        'line' => Str::limit($line, 200),
                    ]);
                }
            }
        }

        return $results;
    }

    public static function containsVless(string $content): bool
    {
        return (bool) preg_match('/vless:\/\//i', $content);
    }

    /**
     * Detect input type: vless uri or subscription url
     *
     * @return string 'vless' or 'url' or 'invalid'
     */
    public static function detectInputType(string $input): string
    {
        $input = trim($input);
        if (Str::startsWith(strtolower($input), 'vless://')) {
            return 'vless';
        }

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($input);
            if (isset($parsed['scheme']) && in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
                return 'url';
            }
        }

        return 'invalid';
    }
}

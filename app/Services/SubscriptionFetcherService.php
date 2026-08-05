<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionFetcherService
{
    /**
     * Fetch subscription URL content with SSRF protection
     *
     * @param string $url
     * @return array ['success' => bool, 'content' => string|null, 'error' => string|null]
     */
    public static function fetch(string $url): array
    {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'content' => null,
                'error' => 'Invalid URL format.',
            ];
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return [
                'success' => false,
                'content' => null,
                'error' => 'Only HTTP and HTTPS URLs are allowed.',
            ];
        }

        $host = $parsed['host'] ?? '';

        // SSRF Protection: block private IPs, localhost, etc.
        $ssrfCheck = self::isBlockedHost($host);
        if ($ssrfCheck['blocked']) {
            Log::warning('SSRF attempt blocked', [
                'url' => $url,
                'host' => $host,
                'reason' => $ssrfCheck['reason'],
            ]);
            return [
                'success' => false,
                'content' => null,
                'error' => 'This URL is not allowed for security reasons.',
            ];
        }

        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 15,
                'connect_timeout' => 10,
            ])
                ->withHeaders([
                    'User-Agent' => 'Mehrgan-Importer/1.0',
                    'Accept' => 'text/plain, */*',
                ])
                ->get($url);

            if (!$response->successful()) {
                $status = $response->status();
                Log::warning('Failed to fetch subscription URL', [
                    'url' => $url,
                    'status' => $status,
                    'body' => Str::limit($response->body(), 500),
                ]);

                if ($status === 404) {
                    return [
                        'success' => false,
                        'content' => null,
                        'error' => 'Subscription URL not found (404).',
                    ];
                }

                if ($status === 401 || $status === 403) {
                    return [
                        'success' => false,
                        'content' => null,
                        'error' => 'Unauthorized access to subscription URL.',
                    ];
                }

                return [
                    'success' => false,
                    'content' => null,
                    'error' => "Failed to fetch subscription (HTTP {$status}).",
                ];
            }

            $content = trim($response->body());
            if (empty($content)) {
                return [
                    'success' => false,
                    'content' => null,
                    'error' => 'Subscription URL returned empty content.',
                ];
            }

            return [
                'success' => true,
                'content' => $content,
                'error' => null,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection timeout fetching subscription', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'content' => null,
                'error' => 'Connection timeout while fetching subscription URL.',
            ];
        } catch (\Exception $e) {
            Log::error('Exception fetching subscription URL', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'content' => null,
                'error' => 'Failed to fetch subscription URL: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if host is blocked for SSRF
     */
    public static function isBlockedHost(string $host): array
    {
        $host = strtolower(trim($host));

        // Block empty
        if (empty($host)) {
            return ['blocked' => true, 'reason' => 'Empty host'];
        }

        // Block localhost variations
        $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array($host, $blockedHosts)) {
            return ['blocked' => true, 'reason' => 'Localhost blocked'];
        }

        // Check if host is IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return ['blocked' => true, 'reason' => 'Private/reserved IP blocked'];
            }
            // Additional check for specific ranges
            if (self::isPrivateIp($host)) {
                return ['blocked' => true, 'reason' => 'Private IP range'];
            }
        }

        // Block internal domains?
        // For now allow any public domain, but block if contains invalid chars
        if (preg_match('/[^a-z0-9\.\-]/i', $host) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Allow punycode etc? Simplified
        }

        // Attempt DNS resolution to check if resolves to private IP
        // This is optional and may fail in some environments, so we try but don't block on failure
        try {
            $ips = gethostbynamel($host);
            if ($ips) {
                foreach ($ips as $ip) {
                    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return ['blocked' => true, 'reason' => "Resolves to private IP {$ip}"];
                    }
                    if (self::isPrivateIp($ip)) {
                        return ['blocked' => true, 'reason' => "Resolves to private IP {$ip}"];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore DNS errors, allow
            Log::debug('DNS resolution check failed', ['host' => $host, 'error' => $e->getMessage()]);
        }

        return ['blocked' => false, 'reason' => null];
    }

    protected static function isPrivateIp(string $ip): bool
    {
        // IPv4 private ranges
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
        ];

        foreach ($privateRanges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }

        // IPv6 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (str_starts_with($ip, '::') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd') || $ip === '::1') {
                return true;
            }
        }

        return false;
    }

    protected static function ipInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        if ($ip === false || $subnet === false) {
            return false;
        }
        $mask = -1 << (32 - (int) $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }
}

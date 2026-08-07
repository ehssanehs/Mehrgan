<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\PasarGuardService;
use Illuminate\Support\Facades\Log;
use Modules\MultiServer\Models\Server;

class PanelSearchService
{
    /**
     * Search for a client by UUID across all configured panels
     *
     * @param string $uuid
     * @return array|null [
     *   'type' => 'xui'|'marzban',
     *   'server' => Server|null,
     *   'server_id' => int|null,
     *   'client' => array (XUI client) or 'user' => array (Marzban user),
     *   'inbound' => array|null (XUI inbound),
     *   'subscription_link' => string|null,
     *   'details' => array
     * ]
     */
    public static function searchByUuid(string $uuid): ?array
    {
        $uuid = strtolower(trim($uuid));

        // First, search in MultiServer servers if module exists
        if (class_exists('Modules\\MultiServer\\Models\\Server')) {
            $servers = Server::where('is_active', true)->get();

            foreach ($servers as $server) {
                try {
                    if ($server->type === 'xui') {
                        $result = self::searchXuiServer($server, $uuid);
                        if ($result) {
                            return $result;
                        }
                    } elseif ($server->type === 'marzban') {
                        $result = self::searchMarzbanServer($server, $uuid);
                        if ($result) {
                            return $result;
                        }
                    } elseif ($server->type === 'pasarguard') {
                        $result = self::searchPasarGuardServer($server, $uuid);
                        if ($result) {
                            return $result;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to search server for UUID', [
                        'server_id' => $server->id,
                        'server_name' => $server->name,
                        'uuid' => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        // Fallback: search in legacy settings (old single-server config)
        try {
            $settings = Setting::all()->pluck('value', 'key');

            // Try XUI legacy
            $xuiHost = $settings->get('xui_host');
            $xuiUser = $settings->get('xui_user');
            $xuiPass = $settings->get('xui_pass');

            if ($xuiHost && $xuiUser && $xuiPass) {
                $result = self::searchXuiLegacy($xuiHost, $xuiUser, $xuiPass, $uuid, $settings);
                if ($result) {
                    return $result;
                }
            }

            // Try Marzban legacy
            $marzbanHost = $settings->get('marzban_host');
            $marzbanUser = $settings->get('marzban_sudo_username');
            $marzbanPass = $settings->get('marzban_sudo_password');
            $marzbanNode = $settings->get('marzban_node_hostname');

            if ($marzbanHost && $marzbanUser && $marzbanPass) {
                $result = self::searchMarzbanLegacy($marzbanHost, $marzbanUser, $marzbanPass, $marzbanNode, $uuid);
                if ($result) {
                    return $result;
                }
            }

            // Try PasarGuard legacy
            $pasarguardHost = $settings->get('pasarguard_host');
            $pasarguardUser = $settings->get('pasarguard_username');
            $pasarguardPass = $settings->get('pasarguard_password');
            $pasarguardNode = $settings->get('pasarguard_node_hostname');

            if ($pasarguardHost && $pasarguardUser && $pasarguardPass) {
                $result = self::searchPasarGuardLegacy($pasarguardHost, $pasarguardUser, $pasarguardPass, $pasarguardNode, $uuid);
                if ($result) {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to search legacy settings for UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected static function searchXuiServer(Server $server, string $uuid): ?array
    {
        $xuiService = new XUIService($server->full_host, $server->username, $server->password);

        if (!$xuiService->login()) {
            Log::warning('XUI login failed for server', [
                'server_id' => $server->id,
                'server_name' => $server->name,
            ]);
            return null;
        }

        $found = $xuiService->findClientByUuid($uuid);
        if (!$found) {
            return null;
        }

        $client = $found['client'];
        $inbound = $found['inbound'];

        // Build subscription link (prefer the real /sub/ link when one can be
        // derived from the server settings, even for 'single'-type servers).
        $subscriptionLink = self::buildXuiSubscriptionLink($server, $client, $inbound, true);

        // Build details
        $details = [
            'uuid' => $uuid,
            'email' => $client['email'] ?? null,
            'totalGB' => $client['totalGB'] ?? 0,
            'expiryTime' => $client['expiryTime'] ?? 0,
            'enable' => $client['enable'] ?? true,
            'subId' => $client['subId'] ?? null,
            'inbound_id' => $inbound['id'] ?? null,
            'inbound_remark' => $inbound['remark'] ?? null,
            'inbound_protocol' => $inbound['protocol'] ?? 'vless',
            'inbound_port' => $inbound['port'] ?? null,
            'traffic_used' => 0, // Will be fetched if available
            'status' => $client['enable'] ?? true ? 'active' : 'disabled',
        ];

        // Try to get traffic stats from multiple sources (different XUI versions)
        $trafficFound = false;
        $inboundStats = $xuiService->getInboundWithClientStats($inbound['id'] ?? 0);
        if ($inboundStats && isset($inboundStats['clientStats'])) {
            foreach ($inboundStats['clientStats'] as $stat) {
                if (($stat['email'] ?? '') === ($client['email'] ?? '')) {
                    $details['traffic_used'] = ($stat['up'] ?? 0) + ($stat['down'] ?? 0);
                    $details['traffic_up'] = $stat['up'] ?? 0;
                    $details['traffic_down'] = $stat['down'] ?? 0;
                    $trafficFound = true;
                    break;
                }
            }
        }
        // Fallback: try listing all inbounds and looking for clientStats there
        if (!$trafficFound) {
            try {
                $allInbounds = $xuiService->getInbounds();
                foreach ($allInbounds as $ib) {
                    if (($ib['id'] ?? null) != ($inbound['id'] ?? null)) continue;
                    $clientStats = $ib['clientStats'] ?? [];
                    foreach ($clientStats as $stat) {
                        if (($stat['email'] ?? '') === ($client['email'] ?? '')) {
                            $details['traffic_used'] = ($stat['up'] ?? 0) + ($stat['down'] ?? 0);
                            $details['traffic_up'] = $stat['up'] ?? 0;
                            $details['traffic_down'] = $stat['down'] ?? 0;
                            $trafficFound = true;
                            break 2;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::debug('XUI fallback traffic fetch failed', ['error' => $e->getMessage()]);
            }
        }
        // Also check if client itself has traffic fields (some forks store directly)
        if (!$trafficFound) {
            if (isset($client['up']) || isset($client['down'])) {
                $details['traffic_used'] = ($client['up'] ?? 0) + ($client['down'] ?? 0);
                $details['traffic_up'] = $client['up'] ?? 0;
                $details['traffic_down'] = $client['down'] ?? 0;
            }
        }

        // Enrich the details with the authoritative "get client" API record
        // (getClientTraffics/{email}). The client copy embedded in the inbound
        // settings misses or zeroes out totalGB/expiryTime on some panel forks,
        // which made imported subscriptions display an unlimited expiry/volume.
        self::enrichXuiDetailsFromClientRecord($xuiService, $client, $details, $trafficFound);

        return [
            'type' => 'xui',
            'server' => $server,
            'server_id' => $server->id,
            'client' => $client,
            'inbound' => $inbound,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    /**
     * Merge the authoritative getClientTraffics record of a client into the
     * details array. Only empty/zero values are filled so real values already
     * read from the inbound settings are never overwritten.
     */
    protected static function enrichXuiDetailsFromClientRecord(XUIService $xuiService, array $client, array &$details, bool &$trafficFound): void
    {
        $email = $client['email'] ?? ($details['email'] ?? null);
        if (empty($email)) {
            return;
        }

        try {
            $record = $xuiService->getClientTrafficsByEmail((string) $email);
            if (!is_array($record)) {
                return;
            }

            // `total` (bytes) in the client record == `totalGB` in the settings copy.
            if (empty($details['totalGB']) && isset($record['total']) && is_numeric($record['total'])) {
                $details['totalGB'] = (int) $record['total'];
            }

            if (empty($details['expiryTime']) && !empty($record['expiryTime'])) {
                $details['expiryTime'] = $record['expiryTime'];
            }

            if (!$trafficFound && (isset($record['up']) || isset($record['down']))) {
                $details['traffic_up'] = (int) ($record['up'] ?? 0);
                $details['traffic_down'] = (int) ($record['down'] ?? 0);
                $details['traffic_used'] = $details['traffic_up'] + $details['traffic_down'];
                $trafficFound = true;
            }

            if (isset($record['enable'])) {
                $details['enable'] = (bool) $record['enable'];
                $details['status'] = $details['enable'] ? 'active' : 'disabled';
            }

            if (empty($details['subId']) && !empty($record['subId'])) {
                $details['subId'] = $record['subId'];
            }
        } catch (\Exception $e) {
            Log::debug('Could not enrich XUI details from client record', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function searchMarzbanServer(Server $server, string $uuid): ?array
    {
        $host = $server->full_host;
        $marzbanService = new MarzbanService(
            $host,
            $server->username,
            $server->password,
            $server->marzban_node_hostname ?? $host
        );

        if (!$marzbanService->login()) {
            Log::warning('Marzban login failed for server', [
                'server_id' => $server->id,
            ]);
            return null;
        }

        $user = $marzbanService->findUserByUuid($uuid);
        if (!$user) {
            return null;
        }

        // Now that the username is known, re-read the complete record through
        // the "get user" API. The paginated /api/users list can omit or
        // zero-out fields (expire / data_limit / subscription_url) on some
        // panel versions, which made imported subscriptions display an
        // unlimited expiry/volume.
        $user = self::fetchCompleteUserRecord($marzbanService, $user);

        $subscriptionLink = $marzbanService->generateSubscriptionLink($user);

        $details = [
            'uuid' => $uuid,
            'username' => $user['username'] ?? null,
            'email' => $user['username'] ?? null,
            'expire' => $user['expire'] ?? 0,
            'data_limit' => $user['data_limit'] ?? 0,
            'used_traffic' => $user['used_traffic'] ?? 0,
            'status' => $user['status'] ?? 'active',
            'proxies' => $user['proxies'] ?? [],
            'inbounds' => $user['inbounds'] ?? [],
            'subscription_url' => $user['subscription_url'] ?? null,
        ];

        return [
            'type' => 'marzban',
            'server' => $server,
            'server_id' => $server->id,
            'user' => $user,
            'client' => $user, // for generic handling
            'inbound' => null,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    protected static function searchXuiLegacy(string $host, string $user, string $pass, string $uuid, $settings): ?array
    {
        $xuiService = new XUIService($host, $user, $pass);
        if (!$xuiService->login()) {
            return null;
        }

        $found = $xuiService->findClientByUuid($uuid);
        if (!$found) {
            return null;
        }

        $client = $found['client'];
        $inbound = $found['inbound'];

        // Build subscription link using legacy settings. When a subscription URL
        // base is configured we prefer the real /sub/ link (also for 'single'
        // link type) so imported accounts receive a subscription link.
        $linkType = $settings->get('xui_link_type', 'single');
        $subId = $client['subId'] ?? null;
        $subBase = $settings->get('xui_subscription_url_base');
        $subscriptionLink = null;

        if ($subId && ($linkType === 'subscription' || !empty($subBase))) {
            $base = !empty($subBase) ? $subBase : $host;
            if ($base) {
                $subscriptionLink = rtrim($base, '/') . '/sub/' . $subId;
            }
        }

        if (!$subscriptionLink) {
            // Build VLESS single link
            $streamSettings = json_decode($inbound['streamSettings'] ?? '{}', true);
            $parsedUrl = parse_url($host);
            $serverAddr = $parsedUrl['host'] ?? 'localhost';
            $port = $inbound['port'] ?? 443;
            $params = http_build_query(array_filter([
                'type' => $streamSettings['network'] ?? null,
                'security' => $streamSettings['security'] ?? null,
            ]));
            $remark = $inbound['remark'] ?? 'Account';
            $subscriptionLink = "vless://{$uuid}@{$serverAddr}:{$port}?{$params}#" . urlencode($client['email'] ?? $remark);
        }

        $details = [
            'uuid' => $uuid,
            'email' => $client['email'] ?? null,
            'totalGB' => $client['totalGB'] ?? 0,
            'expiryTime' => $client['expiryTime'] ?? 0,
            'subId' => $client['subId'] ?? null,
            'inbound_id' => $inbound['id'] ?? null,
        ];

        // Read the authoritative values (expire / traffic) through the
        // "get client" API (see searchXuiServer above for the reason).
        $trafficFound = false;
        self::enrichXuiDetailsFromClientRecord($xuiService, $client, $details, $trafficFound);

        return [
            'type' => 'xui',
            'server' => null,
            'server_id' => null,
            'client' => $client,
            'inbound' => $inbound,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    protected static function searchMarzbanLegacy(string $host, string $user, string $pass, ?string $nodeHostname, string $uuid): ?array
    {
        $service = new MarzbanService($host, $user, $pass, $nodeHostname ?? $host);
        if (!$service->login()) {
            return null;
        }

        $foundUser = $service->findUserByUuid($uuid);
        if (!$foundUser) {
            return null;
        }

        // Re-read the complete record through the "get user" API (see
        // searchMarzbanServer above for the reason).
        $foundUser = self::fetchCompleteUserRecord($service, $foundUser);

        $subscriptionLink = $service->generateSubscriptionLink($foundUser);

        $details = [
            'uuid' => $uuid,
            'username' => $foundUser['username'] ?? null,
            'expire' => $foundUser['expire'] ?? 0,
            'data_limit' => $foundUser['data_limit'] ?? 0,
            'used_traffic' => $foundUser['used_traffic'] ?? 0,
            'status' => $foundUser['status'] ?? 'active',
            'subscription_url' => $foundUser['subscription_url'] ?? null,
        ];

        return [
            'type' => 'marzban',
            'server' => null,
            'server_id' => null,
            'user' => $foundUser,
            'client' => $foundUser,
            'inbound' => null,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    protected static function searchPasarGuardServer(Server $server, string $uuid): ?array
    {
        $host = $server->full_host;
        $pasarguardService = new PasarGuardService(
            $host,
            $server->username,
            $server->password,
            $server->pasarguard_node_hostname ?? $host
        );

        if (!$pasarguardService->login()) {
            Log::warning('PasarGuard login failed for server', [
                'server_id' => $server->id,
            ]);
            return null;
        }

        $user = $pasarguardService->findUserByUuid($uuid);
        if (!$user) {
            return null;
        }

        // Re-read the complete record through the "get user" API. PasarGuard's
        // paginated /api/users list can omit expire / data_limit /
        // subscription_url on some versions, which made imported
        // subscriptions display an unlimited expiry/volume.
        $user = self::fetchCompleteUserRecord($pasarguardService, $user);

        $subscriptionLink = $pasarguardService->generateSubscriptionLink($user);

        $details = [
            'uuid' => $uuid,
            'username' => $user['username'] ?? null,
            'email' => $user['username'] ?? null,
            'expire' => $user['expire'] ?? 0,
            'data_limit' => $user['data_limit'] ?? 0,
            'used_traffic' => $user['used_traffic'] ?? 0,
            'status' => $user['status'] ?? 'active',
            'proxies' => $user['proxies'] ?? [],
            'inbounds' => $user['inbounds'] ?? [],
            'subscription_url' => $user['subscription_url'] ?? null,
        ];

        return [
            'type' => 'pasarguard',
            'server' => $server,
            'server_id' => $server->id,
            'user' => $user,
            'client' => $user, // for generic handling
            'inbound' => null,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    protected static function searchPasarGuardLegacy(string $host, string $user, string $pass, ?string $nodeHostname, string $uuid): ?array
    {
        $service = new PasarGuardService($host, $user, $pass, $nodeHostname ?? $host);
        if (!$service->login()) {
            return null;
        }

        $foundUser = $service->findUserByUuid($uuid);
        if (!$foundUser) {
            return null;
        }

        // Re-read the complete record through the "get user" API (see
        // searchPasarGuardServer above for the reason).
        $foundUser = self::fetchCompleteUserRecord($service, $foundUser);

        $subscriptionLink = $service->generateSubscriptionLink($foundUser);

        $details = [
            'uuid' => $uuid,
            'username' => $foundUser['username'] ?? null,
            'expire' => $foundUser['expire'] ?? 0,
            'data_limit' => $foundUser['data_limit'] ?? 0,
            'used_traffic' => $foundUser['used_traffic'] ?? 0,
            'status' => $foundUser['status'] ?? 'active',
            'subscription_url' => $foundUser['subscription_url'] ?? null,
        ];

        return [
            'type' => 'pasarguard',
            'server' => null,
            'server_id' => null,
            'user' => $foundUser,
            'client' => $foundUser,
            'inbound' => null,
            'subscription_link' => $subscriptionLink,
            'details' => $details,
        ];
    }

    /**
     * Re-read the complete user record through the panel's "get user" API
     * (GET /api/user/{username}) once the username has been resolved from the
     * UUID search. The paginated users list can omit or zero-out important
     * fields (expire / data_limit / used_traffic / subscription_url) on some
     * Marzban / PasarGuard versions, which made imported subscriptions
     * display an unlimited expiry/volume. The single-user endpoint always
     * returns the authoritative values, including the subscription_url used
     * to build the subscription link stored on the order.
     *
     * Falls back to the already fetched record when the endpoint is not
     * reachable, so the import never breaks because of an extra request.
     *
     * @param MarzbanService|PasarGuardService $service
     */
    protected static function fetchCompleteUserRecord($service, array $user): array
    {
        $username = $user['username'] ?? null;
        if (empty($username)) {
            return $user;
        }

        try {
            $fullUser = $service->getUser((string) $username);
            if (is_array($fullUser) && !empty($fullUser['username'])) {
                return array_merge($user, $fullUser);
            }
        } catch (\Exception $e) {
            Log::debug('Could not re-fetch complete user record, using list record', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    protected static function buildXuiSubscriptionLink(Server $server, array $client, array $inbound, bool $preferSubscription = false): string
    {
        $linkType = $server->link_type ?? 'single';
        $uuid = $client['id'] ?? '';
        $subId = $client['subId'] ?? null;

        // Build a real /sub/ subscription link whenever we can.
        //  - 'subscription' servers: always (generate a subId if missing, the
        //    panel supports subscription links natively).
        //  - imports (preferSubscription): whenever the client already owns a real
        //    subId. 3x-ui serves /sub/{subId} for ANY client that has a subId, so
        //    even a "single" link-type server yields a proper subscription link
        //    the user can paste into their VPN app. This is what makes VLESS-link
        //    imports store and show a subscription link in "My Services" instead of
        //    a single raw vless:// config.
        $buildSubscription = ($linkType === 'subscription')
            || ($preferSubscription && $subId);

        if ($linkType === 'subscription' && !$subId) {
            $subId = \Illuminate\Support\Str::random(16);
            $buildSubscription = true;
        }

        if ($buildSubscription) {
            $subDomain = $server->subscription_domain ?? parse_url($server->full_host, PHP_URL_HOST);
            $subPort = $server->subscription_port ?? 443;
            $subPath = $server->subscription_path ?? '/sub/';
            $isHttps = $server->is_https ?? true;
            $scheme = $isHttps ? 'https' : 'http';

            $base = $subDomain;
            if (!str_starts_with($base, 'http')) {
                $base = "{$scheme}://{$base}";
                if ($subPort && $subPort != 443 && $subPort != 80) {
                    // Only add port if not already in base
                    if (!str_contains($subDomain, ':')) {
                        $base .= ":{$subPort}";
                    }
                }
            }

            return rtrim($base, '/') . $subPath . $subId;
        }

        // Legacy single-server setups store the subscription URL base as a global
        // setting. When importing, prefer that link — but only when the base host
        // matches this server's panel host, so we never hand out another server's
        // subscription link.
        if ($preferSubscription && $subId && $linkType !== 'tunnel') {
            try {
                $legacyBase = \App\Models\Setting::all()->pluck('value', 'key')->get('xui_subscription_url_base');
                if (!empty($legacyBase)) {
                    $normalized = preg_match('#^[a-z][a-z0-9+.-]*://#i', $legacyBase) ? $legacyBase : 'http://' . $legacyBase;
                    $baseHost = parse_url($normalized, PHP_URL_HOST);
                    $serverHost = parse_url($server->full_host, PHP_URL_HOST);
                    if ($baseHost && $serverHost && strtolower($baseHost) === strtolower($serverHost)) {
                        return rtrim($legacyBase, '/') . '/sub/' . $subId;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Failed to read xui_subscription_url_base setting', ['error' => $e->getMessage()]);
            }
        }

        if ($linkType === 'tunnel') {
            $tunnelAddress = $server->tunnel_address ?? $server->ip_address;
            $tunnelPort = $server->tunnel_port ?? 443;
            $streamSettings = is_string($inbound['streamSettings'] ?? '') ? json_decode($inbound['streamSettings'], true) : ($inbound['streamSettings'] ?? []);
            $type = $streamSettings['network'] ?? 'tcp';
            $params = http_build_query(array_filter([
                'type' => $type,
                'security' => $server->tunnel_is_https ? 'tls' : 'none',
                'sni' => $server->tunnel_is_https ? $tunnelAddress : null,
            ]));
            $remark = $server->name ?? 'Account';
            return "vless://{$uuid}@{$tunnelAddress}:{$tunnelPort}?{$params}#" . urlencode($client['email'] ?? $remark);
        } else {
            // Single VLESS link
            $parsed = parse_url($server->full_host);
            $serverAddress = !empty($inbound['listen']) ? $inbound['listen'] : ($parsed['host'] ?? $server->ip_address);
            $port = $inbound['port'] ?? 443;
            $streamSettings = is_string($inbound['streamSettings'] ?? '') ? json_decode($inbound['streamSettings'], true) : ($inbound['streamSettings'] ?? []);
            $paramsArray = [
                'type' => $streamSettings['network'] ?? 'tcp',
                'security' => $streamSettings['security'] ?? 'none',
            ];
            if (isset($streamSettings['wsSettings']['path'])) {
                $paramsArray['path'] = $streamSettings['wsSettings']['path'];
            }
            if (isset($streamSettings['tlsSettings']['serverName'])) {
                $paramsArray['sni'] = $streamSettings['tlsSettings']['serverName'];
            }
            $params = http_build_query(array_filter($paramsArray));
            $remark = $client['email'] ?? $server->name ?? 'Account';
            return "vless://{$uuid}@{$serverAddress}:{$port}?{$params}#" . urlencode($remark);
        }
    }
}

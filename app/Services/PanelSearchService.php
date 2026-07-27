<?php

namespace App\Services;

use App\Models\Setting;
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

        // Build subscription link
        $subscriptionLink = self::buildXuiSubscriptionLink($server, $client, $inbound);

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

        // Try to get traffic stats
        $inboundStats = $xuiService->getInboundWithClientStats($inbound['id'] ?? 0);
        if ($inboundStats && isset($inboundStats['clientStats'])) {
            foreach ($inboundStats['clientStats'] as $stat) {
                if (($stat['email'] ?? '') === ($client['email'] ?? '')) {
                    $details['traffic_used'] = ($stat['up'] ?? 0) + ($stat['down'] ?? 0);
                    $details['traffic_up'] = $stat['up'] ?? 0;
                    $details['traffic_down'] = $stat['down'] ?? 0;
                    break;
                }
            }
        }

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

        // Build subscription link using legacy settings
        $linkType = $settings->get('xui_link_type', 'single');
        $subscriptionLink = null;

        if ($linkType === 'subscription') {
            $subBase = $settings->get('xui_subscription_url_base', $host);
            $subId = $client['subId'] ?? null;
            if ($subBase && $subId) {
                $subscriptionLink = rtrim($subBase, '/') . '/sub/' . $subId;
            }
        } else {
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

        $subscriptionLink = $service->generateSubscriptionLink($foundUser);

        $details = [
            'uuid' => $uuid,
            'username' => $foundUser['username'] ?? null,
            'expire' => $foundUser['expire'] ?? 0,
            'data_limit' => $foundUser['data_limit'] ?? 0,
            'used_traffic' => $foundUser['used_traffic'] ?? 0,
            'status' => $foundUser['status'] ?? 'active',
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

    protected static function buildXuiSubscriptionLink(Server $server, array $client, array $inbound): string
    {
        $linkType = $server->link_type ?? 'single';
        $uuid = $client['id'] ?? '';
        $subId = $client['subId'] ?? \Illuminate\Support\Str::random(16);

        if ($linkType === 'subscription') {
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
        } elseif ($linkType === 'tunnel') {
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

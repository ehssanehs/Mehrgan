<?php

namespace Modules\Reseller\Services\Vpn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;

class PasarGuardService implements VpnServiceInterface
{
    protected ?string $token = null;

    protected function login(VpnServer $server): bool
    {
        try {
            if ($this->token) {
                return true;
            }

            $response = Http::asForm()->timeout(15)->post($server->api_url . '/api/admin/token', [
                'username' => $server->username,
                'password' => $server->password,
            ]);

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->token = $response->json()['access_token'];
                return true;
            }
            
            Log::error("PasarGuard Login Failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error("PasarGuard Login Error: " . $e->getMessage());
        }
        return false;
    }

    private function normalizeGroupIds(mixed $groupIds): array
    {
        if (is_string($groupIds)) {
            $decoded = json_decode($groupIds, true);
            $groupIds = is_array($decoded)
                ? $decoded
                : preg_split('/[,\s]+/', $groupIds, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($groupIds)) {
            $groupIds = $groupIds === null ? [] : [$groupIds];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $groupIds),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * PasarGuard does not have an implicit default group. Query every group
     * that the reseller admin can use so newly-created accounts have inbound
     * tags and produce actual VLESS/VMess/etc. configurations.
     */
    private function getAllGroupIds(VpnServer $server): ?array
    {
        $endpoints = [
            ['/api/groups/simple', ['all' => 'true']],
            ['/api/groups', ['offset' => 0, 'limit' => 1000]],
        ];

        foreach ($endpoints as [$path, $query]) {
            try {
                $response = Http::withToken($this->token)
                    ->timeout(15)
                    ->get($server->api_url . $path, $query);

                if ($response->status() === 401) {
                    $this->token = null;
                    if ($this->login($server)) {
                        $response = Http::withToken($this->token)
                            ->timeout(15)
                            ->get($server->api_url . $path, $query);
                    }
                }

                if (!$response->successful()) {
                    Log::warning('PasarGuard reseller group endpoint failed.', [
                        'url' => $server->api_url . $path,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    continue;
                }

                $data = $response->json();
                $groups = is_array($data) ? ($data['groups'] ?? $data) : [];
                if (!is_array($groups)) {
                    continue;
                }

                $ids = [];
                foreach ($groups as $group) {
                    if (is_array($group)
                        && isset($group['id'])
                        && (int) $group['id'] > 0
                        && !($group['is_disabled'] ?? false)) {
                        $ids[] = (int) $group['id'];
                    }
                }

                return array_values(array_unique($ids));
            } catch (\Exception $e) {
                Log::warning('PasarGuard reseller group lookup failed.', [
                    'url' => $server->api_url . $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    public function createAccount(VpnServer $server, VpnProduct $product, string $username, ?string $uuid = null): array
    {
        if (!$this->login($server)) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            $expire = $product->period_days > 0 ? time() + ($product->period_days * 86400) : 0;
            
            $protocol = strtolower($product->protocol ?? '');
            $proxies = [];
            
            if (!empty($protocol) && $protocol !== 'other') {
                $proxies[$protocol] = new \stdClass();
            } else {
                $proxies['shadowsocks'] = new \stdClass();
                $proxies['vless'] = new \stdClass();
                $proxies['vmess'] = new \stdClass();
            }

            $payload = [
                'username' => $username,
                'proxy_settings' => $proxies,
                'expire' => $expire,
                'data_limit' => (int) $product->traffic_limit,
                'data_limit_reset_strategy' => 'no_reset',
                'status' => 'active',
                'note' => 'Reseller: ' . ($product->name ?? 'Unknown'),
            ];

            if (!empty($server->config['pasarguard_overrides'])) {
                $payload = array_merge($payload, $server->config['pasarguard_overrides']);
            }

            $groupIds = $this->normalizeGroupIds($payload['group_ids'] ?? []);
            if (!$groupIds && array_key_exists('group_id', $payload)) {
                $groupIds = $this->normalizeGroupIds($payload['group_id']);
            }
            unset($payload['group_id']);

            if (!$groupIds) {
                $groupIds = $this->getAllGroupIds($server);
                if ($groupIds === null) {
                    return [
                        'success' => false,
                        'error' => 'Unable to load PasarGuard groups; account creation was stopped to avoid an empty subscription.',
                    ];
                }
                if (!$groupIds) {
                    return [
                        'success' => false,
                        'error' => 'No enabled PasarGuard groups exist. Configure a group with inbound tags first.',
                    ];
                }
            }
            $payload['group_ids'] = $groupIds;

            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post($server->api_url . '/api/user', $payload);

            if ($response->status() === 401) {
                $this->token = null;
                if ($this->login($server)) {
                    $response = Http::withToken($this->token)
                        ->timeout(15)
                        ->post($server->api_url . '/api/user', $payload);
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                $subPath = trim($data['subscription_url'] ?? '');
                if (preg_match("~^(?:f|ht)tps?://~i", $subPath)) {
                    $subLink = $subPath;
                } else {
                    $subBase = !empty($server->sub_url) ? rtrim($server->sub_url, '/') : rtrim($server->api_url, '/');
                    if (!str_starts_with($subPath, '/') && !empty($subPath)) {
                        $subPath = '/' . $subPath;
                    }
                    $subLink = $subBase . $subPath;
                }

                return [
                    'success' => true,
                    'data' => [
                        'username' => $data['username'] ?? $username,
                        'uuid' => null,
                        'subscription_url' => $subLink,
                        'raw' => $data
                    ]
                ];
            }

            Log::error("PasarGuard Create Failed: " . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            Log::error("PasarGuard Create Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): bool
    {
        if (!$this->login($server)) return false;

        try {
            $response = Http::withToken($this->token)->delete($server->api_url . "/api/user/{$identifier}");
            if ($response->status() === 401) {
                $this->token = null;
                if ($this->login($server)) {
                    $response = Http::withToken($this->token)->delete($server->api_url . "/api/user/{$identifier}");
                }
            }
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("PasarGuard Delete Exception: " . $e->getMessage());
            return false;
        }
    }

    public function getAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): ?array
    {
        if (!$this->login($server)) return null;

        try {
            $response = Http::withToken($this->token)->get($server->api_url . "/api/user/{$identifier}");
            if ($response->status() === 401) {
                $this->token = null;
                if ($this->login($server)) {
                    $response = Http::withToken($this->token)->get($server->api_url . "/api/user/{$identifier}");
                }
            }
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function renewAccount(VpnServer $server, string $identifier, VpnProduct $product, int $daysToAdd, ?int $trafficLimit = null): bool
    {
        if (!$this->login($server)) return false;

        $user = $this->getAccount($server, $identifier, $product);
        if (!$user) {
            Log::error("PasarGuard Renew: Account not found on server.");
            return false;
        }

        $currentExpiry = $user['expire'] ?? 0;
        
        if ($currentExpiry < time()) {
            $currentExpiry = time();
        }
        
        $newExpiry = $currentExpiry + ($daysToAdd * 86400);

        $payload = [
            'expire' => $newExpiry,
            'status' => 'active',
        ];

        // Repair accounts that were created before group auto-assignment was
        // implemented, without replacing a valid existing group selection.
        if (!$this->normalizeGroupIds($user['group_ids'] ?? [])) {
            $groupIds = $this->getAllGroupIds($server);
            if (!$groupIds) {
                Log::error('PasarGuard Renew: user has no groups and no groups could be assigned.', [
                    'identifier' => $identifier,
                    'groups_available' => $groupIds !== null,
                ]);
                return false;
            }
            $payload['group_ids'] = $groupIds;
        }

        if ($trafficLimit !== null) {
            $payload['data_limit'] = $trafficLimit * 1024 * 1024 * 1024;
        }

        try {
            $response = Http::withToken($this->token)
                ->put($server->api_url . "/api/user/{$identifier}", $payload);

            if ($response->status() === 401) {
                $this->token = null;
                if ($this->login($server)) {
                    $response = Http::withToken($this->token)
                        ->put($server->api_url . "/api/user/{$identifier}", $payload);
                }
            }

            if ($response->successful()) {
                // Reset traffic usage
                Http::withToken($this->token)->post($server->api_url . "/api/user/{$identifier}/reset");
                
                return true;
            }

            Log::error("PasarGuard Renew Failed: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("PasarGuard Renew Exception: " . $e->getMessage());
            return false;
        }
    }
}

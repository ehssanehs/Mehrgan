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

            $response = Http::asForm()->post($server->api_url . '/api/admin/token', [
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

    public function createAccount(VpnServer $server, VpnProduct $product, string $username, ?string $uuid = null): array
    {
        if (!$this->login($server)) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            $expire = $product->period_days > 0 ? time() + ($product->period_days * 86400) : 0;
            
            $inboundTags = array_map('trim', explode(',', $product->remote_id));
            
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
                'group_id' => 0,
                'group_ids' => [0],
                'status' => 'active',
                'note' => 'Reseller: ' . ($product->name ?? 'Unknown'),
            ];

            if (!empty($server->config['pasarguard_overrides'])) {
                 $payload = array_merge($payload, $server->config['pasarguard_overrides']);
            }

            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post($server->api_url . '/api/user', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                $subPath = $data['subscription_url'] ?? ''; 
                $subLink = $server->sub_url . $subPath;

                return [
                    'success' => true,
                    'data' => [
                        'username' => $data['username'],
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

        if ($trafficLimit !== null) {
            $payload['data_limit'] = $trafficLimit * 1024 * 1024 * 1024;
        }

        try {
            $response = Http::withToken($this->token)
                ->put($server->api_url . "/api/user/{$identifier}", $payload);

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

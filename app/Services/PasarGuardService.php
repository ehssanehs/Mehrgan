<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasarGuardService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $nodeHostname;
    protected ?string $accessToken = null;

    public function __construct(string $baseUrl, string $username, string $password, string $nodeHostname)
    {
        // Remove /dashboard if present
        $baseUrl = str_ireplace('/dashboard', '', $baseUrl);
        $this->baseUrl = rtrim($baseUrl, '/');
        
        $this->username = $username;
        $this->password = $password;

        // Clean node hostname
        $nodeHostname = trim($nodeHostname);
        
        // Remove any leading slashes
        $nodeHostname = ltrim($nodeHostname, '/');
        
        // Remove trailing slashes
        $nodeHostname = rtrim($nodeHostname, '/');
        
        // Ensure scheme exists
        if (!preg_match("~^(?:f|ht)tps?://~i", $nodeHostname)) {
            $nodeHostname = "https://" . $nodeHostname;
        }

        // Double check for duplicate scheme
        while (str_starts_with($nodeHostname, 'https:///')) {
             $nodeHostname = str_replace('https:///', 'https://', $nodeHostname);
        }

        $this->nodeHostname = $nodeHostname;
    }

    public function login(): bool
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/admin/token', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->accessToken = $response->json()['access_token'];
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('PasarGuard Login Exception:', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function createUser(array $userData): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) {
                return ['detail' => 'Authentication failed'];
            }
        }

        try {
            // Prepare proxies (PasarGuard supports vless, vmess, shadowsocks, trojan, etc.)
            $proxies = $userData['proxies'] ?? [];
            if (empty($proxies)) {
                $proxies = [
                    'shadowsocks' => new \stdClass(),
                    'vless' => new \stdClass(),
                    'vmess' => new \stdClass(),
                ];
            } else {
                $temp = [];
                foreach ((array)$proxies as $key => $val) {
                    if (is_numeric($key)) {
                        $temp[strtolower($val)] = new \stdClass();
                    } else {
                        $temp[$key] = empty($val) ? new \stdClass() : $val;
                    }
                }
                $proxies = $temp;
            }

            // Prepare inbounds/groups
            $inbounds = $userData['inbounds'] ?? [];
            if (is_array($inbounds) && empty($inbounds)) {
                $inbounds = new \stdClass();
            }

            $groupIds = $userData['group_ids'] ?? [];

            $payload = [
                'username' => $userData['username'],
                'proxies' => $proxies,
                'inbounds' => $inbounds,
                'expire' => $userData['expire'],
                'data_limit' => (int)$userData['data_limit'],
                'data_limit_reset_strategy' => 'no_reset',
            ];

            // Add group_ids if provided (PasarGuard uses groups)
            if (!empty($groupIds)) {
                $payload['group_ids'] = $groupIds;
            }

            // Manually encode to JSON to ensure correct types
            $jsonPayload = json_encode($payload);
            
            Log::info('PasarGuard Create User Payload:', [
                'url' => $this->baseUrl . '/api/user',
                'json_payload' => $jsonPayload,
            ]);

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($this->baseUrl . '/api/user');

            Log::info('PasarGuard Create User Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();

        } catch (\Exception $e) {
            Log::error('PasarGuard Create User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function updateUser(string $username, array $userData): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $payload = [
                'expire' => $userData['expire'],
                'data_limit' => $userData['data_limit'],
            ];

            if (isset($userData['proxies'])) {
                $proxies = $userData['proxies'];
                $temp = [];
                foreach ((array)$proxies as $key => $val) {
                    if (is_numeric($key)) {
                        $temp[strtolower($val)] = new \stdClass();
                    } else {
                        $temp[$key] = empty($val) ? new \stdClass() : $val;
                    }
                }
                $payload['proxies'] = $temp;
            }

            if (isset($userData['inbounds'])) {
                $payload['inbounds'] = $userData['inbounds'];
            }

            if (isset($userData['group_ids'])) {
                $payload['group_ids'] = $userData['group_ids'];
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->put($this->baseUrl . "/api/user/{$username}", $payload);

            Log::info('PasarGuard Update User Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('PasarGuard Update User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getUser(string $username): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->baseUrl . "/api/user/{$username}");

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::error('PasarGuard Get User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function resetUserTraffic(string $username): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl . "/api/user/{$username}/reset");

            Log::info('PasarGuard Reset User Traffic Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('PasarGuard Reset User Traffic Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getUsers(int $offset = 0, int $limit = 100): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) {
                return null;
            }
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->baseUrl . "/api/users", [
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('PasarGuard getUsers failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('PasarGuard getUsers Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getAllUsers(): array
    {
        $allUsers = [];
        $offset = 0;
        $limit = 100;

        while (true) {
            $data = $this->getUsers($offset, $limit);
            if (!$data) {
                break;
            }

            $users = $data['users'] ?? $data ?? [];
            if (empty($users) || !is_array($users)) {
                break;
            }

            $allUsers = array_merge($allUsers, $users);

            if (count($users) < $limit) {
                break;
            }

            $offset += $limit;

            if ($offset > 10000) {
                Log::warning('PasarGuard getAllUsers safety break at offset 10000');
                break;
            }
        }

        return $allUsers;
    }

    /**
     * Find user by UUID (searching in proxies)
     */
    public function findUserByUuid(string $uuid): ?array
    {
        $uuid = strtolower(trim($uuid));

        if (!$this->accessToken) {
            if (!$this->login()) {
                return null;
            }
        }

        $users = $this->getAllUsers();

        foreach ($users as $user) {
            // Check proxies for matching UUID
            $proxies = $user['proxies'] ?? [];
            foreach ($proxies as $protocol => $proxyData) {
                if (isset($proxyData['id']) && strtolower(trim($proxyData['id'])) === $uuid) {
                    Log::info('Found PasarGuard user by UUID', [
                        'uuid' => $uuid,
                        'username' => $user['username'] ?? 'unknown',
                        'protocol' => $protocol,
                    ]);
                    return $user;
                }
                if (isset($proxyData['password']) && strtolower(trim($proxyData['password'])) === $uuid) {
                    return $user;
                }
            }

            // Also check links
            $links = $user['links'] ?? [];
            foreach ($links as $link) {
                if (str_contains(strtolower($link), $uuid)) {
                    return $user;
                }
            }
        }

        Log::info('PasarGuard user not found by UUID', ['uuid' => $uuid]);
        return null;
    }

    /**
     * Get user details including traffic and subscription
     */
    public function getUserDetailed(string $username): ?array
    {
        return $this->getUser($username);
    }

    public function generateSubscriptionLink(array $userApiResponse): string
    {
        if (!isset($userApiResponse['subscription_url'])) {
            // Fallback: try to construct from links if present
            if (isset($userApiResponse['links']) && !empty($userApiResponse['links'])) {
                return $userApiResponse['links'][0] ?? '';
            }
            return '';
        }

        $subscriptionUrl = trim($userApiResponse['subscription_url']);
        
        // If PasarGuard returns a full URL
        if (preg_match("~^(?:f|ht)tps?://~i", $subscriptionUrl)) {
            return $subscriptionUrl;
        }
        
        // If it starts with /http...
        if (preg_match("~^/(?:f|ht)tps?://~i", $subscriptionUrl)) {
             return ltrim($subscriptionUrl, '/');
        }
        
        // Ensure one slash between host and path
        if (!str_starts_with($subscriptionUrl, '/')) {
            $subscriptionUrl = '/' . $subscriptionUrl;
        }

        $base = rtrim($this->nodeHostname, '/');

        return $base . $subscriptionUrl;
    }

    /**
     * Disable a user by setting expiry to now and data_limit to 0.
     */
    public function disableUser(string $username): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $payload = [
                'expire' => now()->timestamp,
                'data_limit' => 0,
                'data_limit_reset_strategy' => 'no_reset',
            ];

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->put($this->baseUrl . "/api/user/{$username}", $payload);

            Log::info('PasarGuard Disable User Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('PasarGuard Disable User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }
}

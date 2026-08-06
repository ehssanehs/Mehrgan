<?php

namespace Tests\Unit;

use App\Services\PasarGuardService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasarGuardServiceTest extends TestCase
{
    public function test_pasarguard_create_user_payload_structure()
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/user' => Http::response([
                'username' => 'testuser',
                'subscription_url' => '/sub/testuser',
            ], 200),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->createUser([
            'username' => 'testuser',
            'expire' => 1700000000,
            'data_limit' => 1073741824,
        ]);

        $this->assertNotNull($response);
        $this->assertEquals('testuser', $response['username']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/api/user') && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);
                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && !isset($data['group_id'])
                    && !isset($data['group_ids'])
                    && ($data['status'] ?? null) === 'active'
                    && ($data['data_limit_reset_strategy'] ?? null) === 'no_reset';
            }
            return true;
        });
    }

    public function test_pasarguard_create_user_with_group_ids()
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/user' => Http::response([
                'username' => 'testuser',
                'subscription_url' => '/sub/testuser',
            ], 200),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->createUser([
            'username' => 'testuser',
            'expire' => 1700000000,
            'data_limit' => 1073741824,
            'group_ids' => [1, 2],
        ]);

        $this->assertNotNull($response);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/api/user') && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);
                return isset($data['group_ids']) && $data['group_ids'] === [1, 2]
                    && !isset($data['group_id']);
            }
            return true;
        });
    }

    public function test_pasarguard_subscription_link_generation()
    {
        $service = new PasarGuardService('https://panel.example.com:2053', 'admin', 'password', 'sub.example.com');
        $link = $service->generateSubscriptionLink(['subscription_url' => '/sub/abc123xyz']);
        $this->assertEquals('https://sub.example.com/sub/abc123xyz', $link);

        // Fallback to baseUrl when nodeHostname is empty
        $service2 = new PasarGuardService('https://panel.example.com:2053', 'admin', 'password', '');
        $link2 = $service2->generateSubscriptionLink(['subscription_url' => '/sub/abc123xyz']);
        $this->assertEquals('https://panel.example.com:2053/sub/abc123xyz', $link2);

        // Full URL
        $link3 = $service2->generateSubscriptionLink(['subscription_url' => 'https://custom.com/sub/abc123xyz']);
        $this->assertEquals('https://custom.com/sub/abc123xyz', $link3);
    }

    public function test_pasarguard_update_user_payload_structure()
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/user/testuser' => Http::response(['success' => true], 200),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->updateUser('testuser', [
            'expire' => 1700000000,
            'data_limit' => 2147483648,
            'proxies' => ['vless' => new \stdClass()],
        ]);

        $this->assertNotNull($response);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/api/user/testuser')) {
                $data = $request->data();
                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds']);
            }
            return true;
        });
    }
}

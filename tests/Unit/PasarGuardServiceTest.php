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
            if (str_contains($request->url(), '/api/user')) {
                $data = json_decode($request->body(), true);
                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && isset($data['group_id']) && $data['group_id'] === 0
                    && isset($data['group_ids']) && $data['group_ids'] === [0];
            }
            return true;
        });
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

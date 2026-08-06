<?php

namespace Modules\Reseller\tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Services\Vpn\PasarGuardService;
use Tests\TestCase;

class PasarGuardServiceTest extends TestCase
{
    public function test_reseller_create_account_payload_structure()
    {
        Http::fake([
            'https://server.example.com:2053/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://server.example.com:2053/api/user' => Http::response([
                'username' => 'reseller_user',
                'subscription_url' => '/sub/reseller_user',
            ], 200),
        ]);

        $server = new VpnServer([
            'name' => 'Test Server',
            'type' => 'pasarguard',
            'ip_address' => 'server.example.com',
            'port' => 2053,
            'is_https' => true,
            'username' => 'admin',
            'password' => 'secret',
            'api_path' => '/api',
            'is_active' => true,
        ]);

        $product = new VpnProduct([
            'name' => '10 GB Plan',
            'protocol' => 'vless',
            'period_days' => 30,
            'traffic_limit' => 10737418240,
        ]);

        $service = new PasarGuardService();
        $result = $service->createAccount($server, $product, 'reseller_user');

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/api/user') && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);
                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && !isset($data['group_id'])
                    && !isset($data['group_ids'])
                    && ($data['status'] ?? null) === 'active';
            }
            return true;
        });
    }
}

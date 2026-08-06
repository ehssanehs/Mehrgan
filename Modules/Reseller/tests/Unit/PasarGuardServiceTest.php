<?php

namespace Modules\Reseller\tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Services\Vpn\PasarGuardService;
use Tests\TestCase;

class PasarGuardServiceTest extends TestCase
{
    public function test_reseller_create_account_assigns_all_pasarguard_groups(): void
    {
        Http::fake([
            'https://server.example.com:2053/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://server.example.com:2053/api/groups/simple*' => Http::response([
                'groups' => [
                    ['id' => 10, 'name' => 'Resellers'],
                    ['id' => 11, 'name' => 'Global'],
                ],
                'total' => 2,
            ], 200),
            'https://server.example.com:2053/api/user' => Http::response([
                'username' => 'reseller_user',
                'group_ids' => [10, 11],
                'subscription_url' => '/sub/reseller_user',
            ], 201),
        ]);

        [$server, $product] = $this->makeServerAndProduct();

        $service = new PasarGuardService();
        $result = $service->createAccount($server, $product, 'reseller_user');

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://server.example.com:2053/api/user' && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);

                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && !isset($data['group_id'])
                    && ($data['group_ids'] ?? null) === [10, 11]
                    && ($data['status'] ?? null) === 'active';
            }

            return false;
        });
    }

    public function test_reseller_create_account_uses_configured_group_override(): void
    {
        Http::fake([
            'https://server.example.com:2053/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://server.example.com:2053/api/user' => Http::response([
                'username' => 'reseller_user',
                'group_ids' => [22],
                'subscription_url' => '/sub/reseller_user',
            ], 201),
        ]);

        [$server, $product] = $this->makeServerAndProduct([
            'pasarguard_overrides' => ['group_ids' => '22, 22, 0'],
        ]);

        $service = new PasarGuardService();
        $result = $service->createAccount($server, $product, 'reseller_user');

        $this->assertTrue($result['success']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/groups'));
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://server.example.com:2053/api/user'
                && $request->method() === 'POST'
                && (json_decode($request->body(), true)['group_ids'] ?? null) === [22];
        });
    }

    public function test_reseller_does_not_create_account_when_pasarguard_has_no_groups(): void
    {
        Http::fake([
            'https://server.example.com:2053/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://server.example.com:2053/api/groups/simple*' => Http::response(['groups' => [], 'total' => 0], 200),
        ]);

        [$server, $product] = $this->makeServerAndProduct();

        $service = new PasarGuardService();
        $result = $service->createAccount($server, $product, 'reseller_user');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No enabled PasarGuard groups', $result['error']);
        Http::assertNotSent(fn (Request $request): bool =>
            $request->url() === 'https://server.example.com:2053/api/user' && $request->method() === 'POST'
        );
    }

    private function makeServerAndProduct(array $config = []): array
    {
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
            'config' => $config,
        ]);

        $product = new VpnProduct([
            'name' => '10 GB Plan',
            'protocol' => 'vless',
            'period_days' => 30,
            'traffic_limit' => 10737418240,
        ]);

        return [$server, $product];
    }
}
